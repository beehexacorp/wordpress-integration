# Connect to HexaSync: one-click WooCommerce connection and log push

Status: approved by the owner, 2026-11-26. Covers both sides: the WordPress plugin `hexasyncintegration`
and HexaSync (`hexasync-saas` gateway and services, `dashboard-v2`).

## 1. Why

Today a store owner connects WooCommerce to HexaSync by hand
([hexasync.vn guide](https://www.hexasync.vn/huong-dan/ket-noi/woocommerce/)):

1. They create a REST API key in WooCommerce.
2. They paste the URL, the consumer key and the secret into HexaSync.
3. They pick the webhook events, using a browser that is *not* logged in to wp-admin.

The plugin installed on their store only shows logs, and nothing on HexaSync sends it any. Logs
can only be pushed with a WordPress Application Password, which nobody sets up.

The goal is one button in wp-admin that does three things:

- connects the store to a HexaSync workspace;
- lets HexaSync push sync logs and notifications back to the store;
- shows the store owner that they are connected.

The store owner never copies a key or creates a password.

## 2. Goals, non-goals, anti-goals

**Goals**

- A **Connect to HexaSync** button in the plugin. One sign-in to HexaSync and one **Approve** on
  WooCommerce's own screen end in a working WooCommerce credential in HexaSync, with webhooks
  created and log push turned on.
- HexaSync pushes task results to the plugin's existing log endpoint with the same WooCommerce key.
- The plugin shows the connection: workspace, a link to HexaSync, and a **Disconnect** button.
- Disconnecting, or the key being revoked, is noticed by HexaSync and handled through flows that
  already exist.
- The `templateId` register link and the manual key entry keep working.

**Non-goals**

- Creating profiles from the plugin. The button connects the store (the credential, which the
  guide calls the WooCommerce Connector App). Profiles and templates are still set up in HexaSync.
- Sites without WooCommerce. The plugin is useful only for WooCommerce stores (approach C was
  dropped for this reason).
- Any background call from the plugin to HexaSync before the store owner clicks Connect.

**Anti-goals** (failure even if it works)

- A store connected to the wrong workspace, or a workspace connected to a store its user does not
  control.
- Logs pushed to a site that does not run the plugin. Installing the plugin counts as asking for
  logs (owner, 2026-11-26, Q4). The plugin's email alerts are off until the owner turns them on.
- A plugin that phones home without opt-in, which breaks WordPress.org guideline 7 and gets the
  plugin closed.
- Removing the plugin silently breaking the store's syncs.

## 3. Approaches considered

| | How | Trade-off |
|---|---|---|
| A | The button only opens `integrations/register?templateId=…&store_url=…` | Little work, but the owner still copies keys and there is still no log channel |
| **B (chosen)** | HexaSync sends the owner to WooCommerce's built-in `/wc-auth/v1/authorize`. WooCommerce creates a Read/Write key and POSTs it to HexaSync. The same key authenticates the log push | One approval. More HexaSync work: an anonymous, state-keyed callback, webhooks created from the server, and a log-push worker |
| C | Like B, plus a WordPress Application Password through `authorize-application.php` for logs | Works without WooCommerce, but two approvals, and HexaSync holds an admin's password |

The owner picked B on 2026-11-26.

## 4. The flow

```
wp-admin (plugin)          Browser                    HexaSync                       Store (WooCommerce)
     |  Connect ------------>  app.hexasync.com/connect/woocommerce?store_url=&return=…
     |                          sign in, pick workspace, confirm store + events
     |                          ----> POST connect-sessions (JWT) -> state, wc-auth URL
     |                          <---- 302 to store /wc-auth/v1/authorize?…&user_id=<state>
     |                          owner clicks Approve ---------------------------------> creates key
     |                                                    <---- POST callback (keys, user_id=state)
     |                                                    verify keys, register credential, 200
     |                          <---- 302 return_url = app.hexasync.com/callback/woocommerce?state=
     |                          page waits for the session to finish
     |                                                    async: create webhooks (wc/v3/webhooks)
     |  <-------------------------------------------------- POST beehexa/v1/hexasync/connection
     |  <---------------------- 302 to wp-admin/admin.php?page=hexasync&hexasync_connected=1
     |  shows "Connected to <workspace>"
     |  <-------------------------------------------------- POST …/log (each task result, later)
```

### 4.1 Plugin: the button

On the HexaSync admin page (today the connection guide), a user with `manage_woocommerce` sees
**Connect to HexaSync** when there is no connection and WooCommerce is active. The button is a
plain link (GET, new tab not needed):

```
https://app.hexasync.com/connect/woocommerce
  ?store_url=<home_url()>
  &admin_url=<admin_url('admin.php?page=hexasync')>
  &plugin_version=<BEEHEXA_VERSION>
  &wc_version=<WC_VERSION>
```

Nothing is sent before the click, and the link carries nothing secret. The query is a hint that
HexaSync shows back to the owner for confirmation, never something it trusts (see §6).

### 4.2 HexaSync: the entry page (dashboard-v2)

New route `/connect/woocommerce`:

1. If the user is not signed in, run the normal sign-in, then come back with the same query.
2. If the user has more than one workspace, they pick one.
3. A confirmation screen shows:
   - the normalized store URL;
   - the workspace;
   - the webhook events, the same list as step 5 of today's guide, all on by default;
   - the text "WooCommerce will ask you to approve a Read/Write API key for HexaSync".

   If this workspace already has a credential for this store URL, the screen says
   **Re-authorize**, and the flow updates that credential instead of creating a second one.
4. The page calls `POST /api/v3/woocommerce/connect-sessions` with the user's JWT and capability
   `gateway:credentials:register`. The body is `{ storeUrl, adminUrl, events[], existingCredentialId? }`.
5. The API returns `{ state, authorizeUrl }`, and the browser goes to `authorizeUrl`.

### 4.3 HexaSync: connect session (gateway)

Naming: this design calls the connect session's token the **connect state**. It is not the
`{state}` in the credential's webhook ingress `/api/v3/messages/{state}/`, which this document
calls the **ingress state**.

`connect-sessions` stores a row with:

- `state`: 32 random bytes, base64url;
- user, workspace, normalized `storeUrl`, `adminUrl`, events, `existingCredentialId`;
- `status`: pending, keys_received, registered, completed or failed;
- `expiresAt` = now + 15 min;
- `error`.

It returns the WooCommerce authorize URL. The URL uses the query form, which also works without
pretty permalinks:

```
<storeUrl>/?wc-auth-version=1&wc-auth-route=authorize
  &app_name=HexaSync
  &scope=read_write
  &user_id=<state>
  &return_url=https://app.hexasync.com/callback/woocommerce?state=<state>
  &callback_url=https://<gateway>/api/v3/woocommerce/auth-callback/<state>
```

WooCommerce requires `callback_url` to be HTTPS. It POSTs the keys there server-to-server with a
60-second timeout. When the response is not 200, WooCommerce **deletes the key it just made** and
shows the owner an error.

### 4.4 HexaSync: the callback (gateway, anonymous)

`POST /api/v3/woocommerce/auth-callback/{state}`. WooCommerce sends this body (fields fixed by
`WC_Auth::create_keys`):

```json
{ "key_id": 12, "user_id": "<state>", "consumer_key": "ck_…", "consumer_secret": "cs_…", "key_permissions": "read_write" }
```

The endpoint:

1. Loads the session by the `state` in the path. Rejects the call unless the session exists,
   is `pending`, is not expired, and `user_id` equals the state. The state is consumed (set to
   `keys_received`) in the same transaction, so a replay fails.
2. *(Runs after the response, see step 5.)* **Proves the keys belong to the store** the owner confirmed. It calls
   `GET <storeUrl>/wp-json/wc/v3/system_status` with the keys. Over HTTPS that is Basic auth;
   over HTTP it is OAuth 1.0a, whichever the existing Woo connector already uses. It then checks
   `environment.site_url` / `home_url` against `storeUrl`. Anyone who learned a state can POST to
   this URL, but cannot make keys that work on the confirmed store.
3. *(After the response.)* Calls `GET <storeUrl>/wp-json/beehexa/v1/hexasync/health` with the same keys:
   - `200` means the plugin (1.2.7+) is there, so `logPush = true`. From 1.3.0 the body also
     carries `version`, which is stored as `pluginVersion`;
   - `404 rest_no_route` means no plugin, so `logPush = false`.
4. Registers the credential through the same code path as `credential/register`, running as the
   session's user and workspace. This is a service-side call into the handler's upsert, not an
   HTTP call needing a JWT. Credential metadata: `storeUrl`, `keyId`, `keyPermissions`,
   `logPush`, `pluginVersion`. With `existingCredentialId`, it updates that credential's keys.
5. Answers `200 {}` right after step 1 and storing the keys encrypted on the session. Steps 2 to 4
   then run in the background.

   **Decision (author's call, 2026-11-26).** WooCommerce sends the keys from inside the PHP
   request in which the owner clicked Approve. A host with one PHP worker, or with session locks,
   would stall a call back into the same store until WooCommerce's 60-second timeout. WooCommerce
   would then delete the key and the connect would fail. So no call goes to the store before the
   200.

   Until step 2 passes:
   - the credential is not registered;
   - no webhook is created;
   - no log is pushed;
   - nothing is synced.

   When step 2 fails, the session becomes `failed` and the keys are dropped. Those keys work only
   on whatever store issued them, so HexaSync holds nothing usable. The owner's key stays on the
   store; the return page says so and links to WooCommerce → Settings → Advanced → REST API.
   When steps 2 to 4 pass, the session becomes `registered`.

Everything slower runs after the response, as `AfterCredentialRegister` actions (§4.6).

### 4.5 HexaSync: the return page (dashboard-v2)

Route `/callback/woocommerce?state=…`. WooCommerce sends the browser here after the callback
returned 200; it adds `success=1&user_id=<state>`. The page:

1. polls `GET /api/v3/woocommerce/connect-sessions/{state}` (JWT, same user) until the status
   is `completed` or `failed`, at most about 60 s;
2. on `completed`, shows a short "Connected" and redirects to `adminUrl` with
   `&hexasync_connected=1`;
3. on `failed` or a timeout, shows the error with a **Try again** button and a link to the
   manual guide.

When WooCommerce redirects with `success=0`, the owner clicked Deny. The page says so and offers
the way back to wp-admin.

### 4.6 HexaSync: after the credential is registered

These are the Woo system's `AfterCredentialRegister` actions (HookKind 19):

1. **Webhooks.**
   - The credential already has its webhook URL `/api/v3/messages/{state}/`
     (`WebhookAutoBindOnCredentialCreate`).
   - For each chosen event, `POST <storeUrl>/wp-json/wc/v3/webhooks` creates
     `{ name: "HexaSync <topic>", topic, delivery_url, secret }`. The secret is per credential and
     stored encrypted.
   - On re-authorize, webhooks named `HexaSync …` whose delivery URL is this credential's are
     updated, not duplicated.
   - This server-side call is what removes today's "use a browser not logged in to wp-admin" step.
2. **Connection push**, only when `logPush`: `POST <storeUrl>/wp-json/beehexa/v1/hexasync/connection`
   with the keys (§5.2). This tells the plugin what it is connected to.
3. Session `completed`.

If webhook creation or the connection push fails, the credential stays registered. The session
still completes with a warning, which the return page shows, and HexaSync retries the step. The
store is connected either way.

### 4.7 HexaSync: log push

A new outbound sink. When a task execution history record is written (`hexasync.service.history`),
and the profile uses a Woo credential with `logPush = true`, HexaSync queues one delivery:

`POST <storeUrl>/wp-json/beehexa/v1/hexasync/log`, authenticated with the credential's keys:

```json
{ "log": {
  "profile_id": "…", "profile_name": "…", "task_id": "…", "task_name": "…",
  "task_status": "Completed|Failed|…", "action_type": "Automatic|Manual",
  "message": "…", "error": "…", "reference_info": "Order #1001", "item_id": "…",
  "log_detail_id": "…", "executed_at": "Nov 26, 2026 21:38:42", "execute_at": "…",
  "retry_count": 0, "push_note": "…"
} }
```

These are the fields the plugin already stores (`docs/rest-api.md`). `profile_name` and
`task_name` are required.

Delivery rules:

- Run from a queue, never inline in the sync. Retry 5 times with backoff (1 min to 1 h).
- `404 rest_no_route`: the plugin was removed or deactivated. Set `logPush = false` and stop.
  Sync continues. A later health check, or a re-authorize, turns it back on.
- `401`/`403`: the key was revoked or lost its rights. Stop the push. The sync itself hits the
  same 401 and goes through the existing re-authorize path.
- **Which results are pushed** (Q1). The owner says HexaSync seems to push only errors today and
  asked for a suggestion. Approved 2026-11-26:
  - **Failed** task results: what is pushed today.
  - **Recovered**: a `Completed` result for an item (`item_id` / `reference_info`) whose last
    pushed result was `Failed`. The store then sees that order #1001 went through on retry,
    instead of a failure that stays open forever. HexaSync keeps the last pushed status per
    credential and item to decide this.
  - Nothing else by default. Successful results with no earlier failure are the bulk of the
    traffic and add little in wp-admin.
  - Later, if asked for: a per-profile "All results" switch.

Push to stores connected by hand: a store that installs the plugin 1.2.7+ can get logs with the
keys HexaSync already holds, with no new connection. A daily health check per Woo credential
(the same call as §4.4 step 3) turns `logPush` on when it finds the plugin. This makes log push
useful before the button ships.

## 5. Plugin side

### 5.1 Authentication (done, branch 1.2.7, not released)

- The filter `woocommerce_rest_is_request_to_rest_api` lets WooCommerce authenticate
  `beehexa/v1` routes with a consumer key. WooCommerce checks the key, its Read/Write permission
  against the method, and its last-access time, as for its own routes.
- A `ck_` user name is not tried as a WordPress login, so it is not counted as a failed login.
- `check_permission` accepts `manage_options` or `manage_woocommerce`, because a key belongs to
  the user who approved it, often a shop manager.
- Tested on WP 7.2 / PHP 8.6 / WooCommerce 11.3.1:
  - read and write keys work, and a read-only key gets 401 on POST;
  - editor keys get 403;
  - the key is refused on core routes;
  - `wc/v3` and Application Passwords are unchanged.

### 5.2 New REST route: connection

`beehexa/v1/hexasync/connection`, with the same permission callback:

- `POST` (HexaSync, after connect or re-authorize). The body has `workspace_id`,
  `workspace_name`, `credential_id`, `credential_name`, `dashboard_url`, `key_id`, `webhook_url`
  and `signing_secret`. It is stored in option `hexasync_connection`, which is not autoloaded.
  `key_id` must match the key that authenticated the request. WooCommerce does not expose that
  key, so the plugin looks it up itself: it hashes the request's consumer key (the Basic auth
  user, or `oauth_consumer_key`) with `wc_api_hash()` and finds its row in
  `woocommerce_api_keys`. That way one key cannot claim another.
- **Re-authorize** (author's call). When the `POST` carries the same `credential_id` as the stored
  connection but a new `key_id`, the plugin deletes the old key from `woocommerce_api_keys`.
  That key was created by the earlier HexaSync approval and is no longer used; without this,
  every re-authorize leaves a valid, unused Read/Write key behind. The plugin deletes no other key.
- **Another workspace connects the same store** (author's call):
  - The plugin shows one connection, the latest `POST`. The admin page says "Connected to
    <workspace>".
  - Logs are accepted from any valid key, because authentication is per key. The log table
    already records `profile_name`.
  - Before creating a connect session, HexaSync checks for other workspaces with a credential for
    the same normalized store URL. If it finds one, the confirmation screen warns: "This store is
    also connected to another HexaSync workspace".
  - Disconnect acts on the displayed connection only.
- `GET` returns the stored connection without `signing_secret`. HexaSync uses it for support
  and checks.
- `DELETE` (HexaSync, when the credential is deleted there) removes the option. The admin page
  then shows the button again.

### 5.3 Admin page

The HexaSync top-level page:

- **Not connected:** the Connect button, a line saying what it does ("You will approve a
  Read/Write WooCommerce API key for HexaSync"), and the existing manual guide below it.
- **Connected:** workspace name, credential name, **Open in HexaSync** (`dashboard_url`), the
  time of the last log received, and **Disconnect**.
- `?hexasync_connected=1` only shows a success notice when the option exists. The plugin never
  trusts the query.
- **WooCommerce inactive:** a notice that the button needs WooCommerce, plus the manual guide.

### 5.4 Disconnect

The plugin's job is only to **tell HexaSync first**. What HexaSync then does is HexaSync's
business; the plugin neither knows nor depends on it (owner, 2026-11-26).

Disconnect is a POST form with a nonce and the `manage_woocommerce` check. It runs these steps in
order:

1. **Notify HexaSync.** POST a signed event to `webhook_url`, the credential's ingress
   `/api/v3/messages/{ingress state}/`:
   - body: `{"event":"app.uninstalled","store_url":…,"key_id":…}`;
   - header: `X-HexaSync-Signature: base64(HMAC-SHA256(body, signing_secret))`;
   - timeout: 15 s.
2. **If HexaSync answered 2xx:**
   - delete WooCommerce key `key_id` from `woocommerce_api_keys`, after checking that it is the
     key in the connection;
   - delete the option;
   - show "Disconnected".
3. **If HexaSync did not answer 2xx:** keep the connection and show the error, with two choices:
   - **Try again**;
   - **Disconnect anyway**: delete the key and the option without the notice. HexaSync then only
     finds out through its own calls failing with 401.

   *(Author's call: deleting the key after the notice, not before, means HexaSync hears about
   the disconnect before it starts seeing 401s.)*

HexaSync side, for reference only, not a plugin concern: the Woo webhook validator (HookKind 1)
checks the signature and emits `gateway-webhook|uninstall-app` with the credential's
`x-webhook-identifier`. That runs the existing `PLATFORM_APP_UNINSTALLED` → UNINSTALL_APP request
flow (Q3).

### 5.5 Uninstall and deactivate

- **Deactivating** the plugin changes nothing on HexaSync. Log pushes get 404 and stop (§4.7).
  Syncs continue.
- **Deleting** the plugin (`uninstall.php`) removes `hexasync_connection` with the other
  options. It does **not** delete the WooCommerce key: the key belongs to the sync, not to the
  plugin, and removing it would silently stop the store's syncs (anti-goal).

## 6. Security

| Threat | Defence |
|---|---|
| An attacker's link connects the victim's workspace to the attacker's store | HexaSync never trusts `store_url` from the query. The owner confirms store and workspace on HexaSync (§4.2), and the keys must work on that store (§4.4 step 2) |
| Forged or replayed POST to the anonymous callback | 256-bit one-time connect state, 15-minute expiry, path state = `user_id`, consumed on first use. Nothing is registered until the keys are verified against the confirmed store (in the background, §4.4 step 5) |
| Keys sent for a different store | `system_status` site URL must match the confirmed `storeUrl` |
| The key is over-privileged | `read_write` is what the sync already needs; the key belongs to the approving user. The log routes require `manage_options` or `manage_woocommerce` |
| One key overwriting another's connection | `POST /connection` must carry the `key_id` of the authenticating key |
| Fake "uninstalled" event | HMAC with a per-connection `signing_secret` that only HexaSync and the plugin know |
| Keys in logs | The callback body and the keys are never logged. Credentials are stored encrypted, like the other connectors |
| HTTP stores | WooCommerce only accepts Basic auth over HTTPS. Over HTTP, HexaSync must sign with OAuth 1.0a for every call: sync, webhooks, connection and logs. If the Woo connector does not do OAuth 1.0a today, the button requires HTTPS and says so |
| WordPress.org guideline 7 | Nothing leaves the site until the owner clicks Connect. Disconnect is the owner's action |

## 7. Work per side

**Plugin**

| # | Work | Release |
|---|---|---|
| P1 | WooCommerce key auth on `beehexa/v1` (done) | 1.2.7 |
| P2 | `connection` route (POST/GET/DELETE), option | 1.3.0 |
| P3 | Admin page states, Connect button, success notice | 1.3.0 |
| P4 | Disconnect (signed event, delete key, delete option) | 1.3.0 |
| P5 | `uninstall.php` removes `hexasync_connection`; readme, FAQ, `docs/rest-api.md`, screenshots | 1.3.0 |

**HexaSync**

| # | Work | Where |
|---|---|---|
| H1 | Log-push sink, queue, retry, `logPush` flag, daily health check | history service + gateway credential metadata |
| H2 | `connect-sessions` create/read API, session table | gateway V3 |
| H3 | Anonymous `auth-callback/{state}`: verify, register credential as session user | gateway V3 |
| H4 | `AfterCredentialRegister` for Woo: webhooks, connection push | gateway / worker (Woo connector) |
| H5 | Woo webhook validator: `X-WC-Webhook-Signature` and `app.uninstalled` → `uninstall-app` | gateway |
| H6 | `/connect/woocommerce` and `/callback/woocommerce` pages | dashboard-v2 |
| H7 | Use the same wc-auth step as an `authorizationSteps` option in `credentials/register`, so the `templateId` link gets one-click too | dashboard-v2 + Woo connector metadata |
| H8 | Guide on hexasync.vn: one-click section first, manual steps kept | hexasync.web.vn |

## 8. Rollout order

1. **Plugin 1.2.7**: release P1. It is harmless alone: nothing calls the new path yet.
2. **H1**: log push for stores connected by hand that run 1.2.7+. Logs reach stores with no UI
   change.
3. **H2 to H6**, behind a feature flag, tested end to end against a staging store.
4. **Plugin 1.3.0** (P2 to P5), released when H2 to H6 are live. The button is hidden until
   then, or the release waits.
5. **H7, H8**.

## 9. Left to the builder

- Where the Woo connector is defined. It is not in `hexasync-saas` metadata or `dashboard-v2`;
  it is likely in the database or the worker. H3, H4 and H7 attach there.
- How the callback runs the credential upsert without a JWT. It needs a service-side entry into
  `CredentialRegisterHookHandler` with the session's user and workspace, and the same ACL checks
  done at session creation.
- Store URL normalization: scheme, `www`, trailing slash, subdirectory installs. Use one function
  on both the session and the `system_status` check.
- Queue technology and backoff values for H1, following how the services already queue outbound
  work.

## 10. Open questions for the owner

- **Q1. Which task results are pushed?** Decided 2026-11-26: failures plus recoveries (§4.7).
- **Q2. Does the button connect only the store?** Decided 2026-11-26: it connects only the store.
  Profiles are set up in HexaSync.
- **Q3. What does Disconnect do?** Decided 2026-11-26.
  - **Plugin:** notifies HexaSync first. Once HexaSync has the notice, the plugin deletes the
    key and its connection (§5.4). What HexaSync does next is not the plugin's concern.
  - **HexaSync:** runs the full UNINSTALL_APP flow, with emails and data deleted after 7 days.
- **Q4. Log push to stores connected by hand?** Decided 2026-11-26: installing the plugin is
  enough. The daily health check turns push on, H1 can ship before the button, and 1.2.7 needs
  no further change. Email alerts stay off until the owner enables them.
