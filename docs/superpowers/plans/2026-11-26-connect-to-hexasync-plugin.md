# Connect to HexaSync (plugin side) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Plugin 1.3.0 gets a Connect to HexaSync button, a `connection` REST route that HexaSync
fills after connecting, a connected-state admin panel, and a notify-first Disconnect. Before that,
1.2.7 (WooCommerce-key auth, already written) is released.

**Architecture:** The connection is one non-autoloaded option, `hexasync_connection`, owned by
a small store class. HexaSync writes it through `POST beehexa/v1/hexasync/connection`,
authenticated with the WooCommerce key; WooCommerce key auth on `beehexa/v1` was added in 1.2.7.
The admin page reads the option to show the button or the connection. Disconnect signs an event
to HexaSync and, only once HexaSync answers 2xx, deletes the WooCommerce key and the option.

**Tech Stack:** plain PHP 7.4+ WordPress plugin (WP 6.2+, `%i` placeholders), WooCommerce
`woocommerce_api_keys` table, WP REST API. No Composer and no PHPUnit: tests are bash + curl +
WP-CLI scripts against a throwaway Docker site, in `tools/` (which is not shipped to SVN).

**Spec:** `docs/superpowers/specs/2026-11-26-connect-to-hexasync-design.md` (approved
2026-11-26). Read §4.1, §5 and §6 before starting.

**Scope:** plugin only (spec §7 P1–P5, rollout steps 1 and 4). HexaSync work (H1–H8) gets its
own plan in `hexasync-saas` / `dashboard-v2`, once the Woo connector's location is known
(spec §9).

## Global Constraints

- Never touch `master`. 1.2.7 is committed on branch `1.2.7`; 1.3.0 work goes on branch `1.3.0`,
  created from `1.2.7`.
- **Commit, push and `svn commit` only when the owner says so in that message.** Each "Commit"
  step below means: stage the files and stop, unless the owner has said to commit.
- The version is in 3 places that must agree: the `Version:` header and `BEEHEXA_VERSION` in
  `hexasyncintegration.php`, and `Stable tag:` in `readme.txt`.
- There is no autoloader: every new class file is added to the `require_once` list in
  `hexasyncintegration.php`. Namespaces are `Beehexa\<Folder>` mirroring `src/<Folder>/`.
- Plugin Check must pass: escape all output, `wp_unslash` and sanitize all input, keep the
  `phpcs:ignore WordPress.DB...` comments on `$wpdb` queries.
- Admin strings use text domain `hexasyncintegration`. Run `php tools/i18n/build.php` and give
  every new string a Vietnamese `msgstr`. REST responses stay English.
- Nothing is sent to HexaSync before the owner clicks Connect. Disconnect is the owner's action
  (WordPress.org guideline 7).
- Capability: `manage_woocommerce` for the Connect and Disconnect UI. REST routes use the shared
  check `manage_options || manage_woocommerce`.
- The HexaSync app URL is `https://app.hexasync.com`, filterable through `hexasync_app_url` for
  staging.

## Review Focus

1. **HTTP store, OAuth 1.0a key.** `POST /connection` signed with OAuth (no Basic header) must
   still find its `key_id` from `oauth_consumer_key`. → Task 2, test `t_request_key_id_oauth`.
2. **Connection written with an Application Password** instead of a WooCommerce key must be
   refused (403 `hexasync_key_required`), because `key_id` cannot be proven. → Task 2,
   `t_connection_rejects_app_password`.
3. **A second workspace connects** (different `credential_id`). The connection is replaced and
   the first workspace's key is **not** deleted. → Task 2, `t_other_credential_keeps_old_key`.
4. **HexaSync down or answering 5xx on Disconnect.** The connection and key stay, the error is
   shown, and "Disconnect anyway" still works. → Task 4, `t_disconnect_notify_fails_keeps_all`
   and `t_disconnect_force`.
5. **The stored key was already deleted by hand** in WooCommerce settings. Disconnect still
   completes. → Task 4, `t_disconnect_key_already_gone`.

---

### Task 0: Release 1.2.7 (already written on branch `1.2.7`)

**Files:** already modified on branch `1.2.7`:
- `hexasyncintegration.php`, `readme.txt`
- `src/API/JsonBasicAuth.php`, `src/API/HexaSyncLogAPI.php`
- `docs/rest-api.md`
- the spec

- [ ] **Step 1:** In `CLAUDE.md`, under Architecture → Auth, add this paragraph: "Since 1.2.7,
  `allow_woocommerce_keys()` (filter `woocommerce_rest_is_request_to_rest_api`) lets a
  WooCommerce consumer key authenticate `beehexa/v1`. A `ck_` user name skips the WordPress
  login path. Routes accept `manage_options` or `manage_woocommerce`." Replace "All routes
  require `manage_options`" accordingly.
- [ ] **Step 2:** Run Plugin Check:
  `wp plugin check hexasyncintegration --exclude-directories=docs,tools,.git --exclude-files=CLAUDE.md,.gitignore`.
  Expected: `Success: Checks complete. No errors found.`
- [ ] **Step 3: Commit** on `1.2.7` (only when told). Message:
  "Release 1.2.7: WooCommerce keys authenticate the log API".
- [ ] **Step 4: Publish** (only when told):
  - copy the shipped files to `../hexasyncintegration-svn/trunk`;
  - `svn cp trunk tags/1.2.7`;
  - `svn commit`;
  - check `https://api.wordpress.org/plugins/info/1.0/hexasyncintegration.json` shows `1.2.7`.

### Task 1: Test harness

**Files:**
- Create: `tools/test-env/Dockerfile`: `FROM php:8.3-cli`, `docker-php-ext-install mysqli`.
  The build arg `PHP_IMAGE` lets a PHP 8.6 image be used instead.
- Create: `tools/test-env/up.sh`. It:
  - creates network `hxtest`, MariaDB 10.11 `hxtest-db`, and `hxtest-wp` serving
    `php -S 0.0.0.0:8973` on `127.0.0.1:8973`;
  - downloads WordPress (latest) and WooCommerce (latest stable, from downloads.wordpress.org)
    into `tools/test-env/.site/`, which is gitignored;
  - mounts this repo read-only at `wp-content/plugins/hexasyncintegration`;
  - installs the site with `WP_ENVIRONMENT_TYPE=local`;
  - activates both plugins;
  - creates users `shopmgr` (shop_manager) and `editor1` (editor);
  - copies `tools/test-env/mu/test-https.php`, which sets `$_SERVER['HTTPS']='on'`;
  - starts `hxtest-fakehs` (§ below).
- Create: `tools/test-env/down.sh`: removes the 3 containers and the network.
- Create: `tools/test-env/fake-hexasync.php`, a router script for `php -S` in container
  `hxtest-fakehs:8080`. It appends every request (method, path, headers, body) as one JSON line
  to `/data/requests.jsonl` and answers with the status in `/data/status` (default 200).
- Create: `tools/test-env/lib.sh`, which defines:
  - `wp_cli …`;
  - `make_key <login> <perm>`, which echoes `key_id ck cs` and inserts the key the way
    `WC_Auth::create_keys` does;
  - `req <method> <path> <auth> [body]`, which sets `$STATUS` and `$BODY`;
  - `assert_eq <name> <expected> <actual>`, which prints `PASS name` or `FAIL name` and sets the
    exit code.
- Create: `tools/test-env/run.sh`, which sources `lib.sh` and runs every `tests/t_*.sh`.
- Create: `tools/test-env/tests/t_auth.sh`, porting the 1.2.7 checks:
  - admin read/write key: health 200, POST log 201;
  - read-only key: POST 401;
  - shop manager key: POST 201;
  - editor key: 403;
  - wrong secret: 401;
  - key on `/wp/v2/users/me`: 401;
  - Application Password: 200;
  - no `wp_login_failed` entries.
- Modify: `.gitignore`: add `tools/test-env/.site/`.

- [ ] **Step 1:** Write the scripts above, then run `tools/test-env/up.sh`. Expected: it ends
  with `ready http://127.0.0.1:8973`.
- [ ] **Step 2:** Run `tools/test-env/run.sh`. Expected: every `t_auth` line is `PASS` and the
  exit code is 0, because branch 1.2.7 already implements this.
- [ ] **Step 3:** Point the plugin mount at branch `1.2.6` (`git stash` or a worktree), rerun,
  and expect the key tests to `FAIL`. This proves the harness detects the change. Restore.
- [ ] **Step 4:** Create branch `1.3.0` from `1.2.7`. **Commit** (only when told):
  "Test harness for the REST API against WooCommerce".

### Task 2: Connection store and `connection` REST route

**Files:**
- Create: `src/API/Permissions.php`
- Create: `src/Connection/ConnectionStore.php`
- Create: `src/Connection/WooKeys.php`
- Create: `src/API/ConnectionAPI.php`
- Modify: `src/API/HexaSyncLogAPI.php`:
  - `check_permission()` delegates to `Permissions::can_use_api()`;
  - `health_check()` adds `"version": BEEHEXA_VERSION`. This is additive, and HexaSync's daily
    check records it.
- Modify: `hexasyncintegration.php`: require the 4 files and hook
  `rest_api_init → (new ConnectionAPI())->register_routes()`.
- Modify: `uninstall.php`: add `hexasync_connection` to the option list.
- Test: `tools/test-env/tests/t_connection.sh`

**Interfaces (produced):**
- `Beehexa\API\Permissions::can_use_api(): bool`, which is
  `manage_options || manage_woocommerce`.
- `Beehexa\Connection\ConnectionStore`:
  - `const OPTION = 'hexasync_connection'`;
  - `static get(): ?array`;
  - `static save(array $c): void`, which calls `update_option(OPTION, $c, false)` and adds
    `connected_at` = `time()`;
  - `static delete(): void`;
  - `static public_view(array $c): array`, which is `$c` without `signing_secret`.
- Stored keys: `workspace_id`, `workspace_name`, `credential_id`, `credential_name`,
  `dashboard_url`, `key_id` (int), `webhook_url`, `signing_secret`, `connected_at` (int).
- `Beehexa\Connection\WooKeys`:
  - `static request_key_id(): ?int`. It hashes the request's consumer key with `wc_api_hash()`
    and selects `key_id` from `{prefix}woocommerce_api_keys`. The key is the Basic user
    `PHP_AUTH_USER` when it starts with `ck_`, otherwise `oauth_consumer_key` from `$_GET`,
    `$_POST` or the `Authorization: OAuth …` header (parse with the same rules as
    `WC_REST_Authentication::parse_header`). Returns null when there is none or WooCommerce is
    inactive.
  - `static delete_key(int $key_id): bool`. It deletes the row (`phpcs:ignore` DB comments) and
    returns whether a row went.
- REST, all under `beehexa/v1/hexasync`, with permission `Permissions::can_use_api`:

| Method | Path | Success | Errors |
|---|---|---|---|
| `POST` | `/connection` | `200 {"data": public_view}` | `400 hexasync_invalid_connection` (message names the field); `403 hexasync_key_required` (no key); `403 hexasync_key_mismatch` (`key_id` ≠ `request_key_id()`) |
| `GET` | `/connection` | `200 {"data": public_view}` | `404 hexasync_not_connected` |
| `DELETE` | `/connection` | `200 {"deleted": true}` | Removes the option only, never a key. Idempotent |

- POST validation:
  - all 8 fields are present;
  - `key_id` is a positive int;
  - `dashboard_url` and `webhook_url` pass `wp_http_validate_url` and are `https`, except that
    `http` is allowed when `wp_get_environment_type() === 'local'`;
  - `signing_secret` is at least 32 characters.

  Sanitize the strings with `sanitize_text_field`, and the URLs with `esc_url_raw`.
- Re-authorize: when the stored `credential_id` equals the new one and the stored `key_id`
  differs, call `WooKeys::delete_key($old_key_id)` before saving. For a different
  `credential_id`, delete nothing.

- [ ] **Step 1: Write the failing tests** in `t_connection.sh`. Use `$K1`, an admin read/write
  key, and a 40-character secret:

| Test | Request | Expect |
|---|---|---|
| `t_connection_get_empty` | GET | 404 `hexasync_not_connected` |
| `t_connection_post_ok` | POST valid body, `key_id` = `$K1_ID` | 200; `.data.workspace_name` = `"Acme"`; no `signing_secret` in body |
| `t_connection_get_after` | GET | 200; `.data.credential_id` = `"cred-1"` |
| `t_connection_option_not_autoloaded` | `wp option get-autoload hexasync_connection` | `off` (WP 6.6+) or `no` |
| `t_connection_key_mismatch` | POST with `key_id` = `$K1_ID+999` | 403 `hexasync_key_mismatch` |
| `t_connection_rejects_app_password` | POST with admin Application Password | 403 `hexasync_key_required` |
| `t_connection_bad_url` | POST, `webhook_url` = `"ftp://x"` | 400 |
| `t_connection_short_secret` | POST, 10-character secret | 400 |
| `t_reauthorize_deletes_old_key` | Make `$K2`; POST `cred-1` with `key_id` = `$K2_ID`, authenticated by `$K2` | 200; `$K1` row gone; `$K2` row present |
| `t_other_credential_keeps_old_key` | Make `$K3`; POST `cred-2` authenticated by `$K3` | 200; `$K2` row still present; GET shows `cred-2` |
| `t_request_key_id_oauth` | `wp eval`: set `$_GET['oauth_consumer_key']=$K3_ck`, call `WooKeys::request_key_id()` | `$K3_ID` |
| `t_connection_rest_route_form` | GET `/?rest_route=/beehexa/v1/hexasync/connection` with `$K3` | 200 |
| `t_connection_delete` | DELETE | 200; GET → 404; `$K3` row still present |
| `t_health_version` | GET `/health` | `.version` = the header version |
| `t_uninstall_removes_option` | `wp eval` `define('WP_UNINSTALL_PLUGIN',1); include uninstall.php`, after a POST | `wp option get hexasync_connection` fails |

- [ ] **Step 2:** Run `tools/test-env/run.sh`. Expected: the `t_connection_*` tests FAIL (404
  `rest_no_route`).
- [ ] **Step 3:** Implement the four classes and the bootstrap wiring per the Interfaces above.
- [ ] **Step 4:** Run `tools/test-env/run.sh`. Expected: all PASS, `t_auth` included.
- [ ] **Step 5:** In `docs/rest-api.md`, add a "Connection" section with a POST/GET/DELETE
  example and the error codes from the table, plus the new `version` field in the health sample.
- [ ] **Step 6: Commit** (only when told): "Connection REST route for HexaSync".

### Task 3: Admin panel and Connect button

**Files:**
- Create: `src/UI/ConnectPanel.php`
- Modify: `src/UI/AdminConnectionGuidePage.php`: call `ConnectPanel::render()` right after the
  `<h1>`, and keep the manual guide below it under a heading "Connect manually".
- Modify: `hexasyncintegration.php`: require the file.
- Test: `tools/test-env/tests/t_panel.sh`

**Interfaces:**
- Consumes: `ConnectionStore::get()`, `ConnectionStore::public_view()`.
- Produces:
  - `Beehexa\UI\ConnectPanel::app_url(): string`, which is
    `untrailingslashit(apply_filters('hexasync_app_url', 'https://app.hexasync.com'))`;
  - `ConnectPanel::connect_url(): string`, which is
    `app_url() . '/connect/woocommerce?' . http_build_query([...])` with these keys, in this
    order:
    - `store_url` = `home_url('/')`;
    - `admin_url` = `admin_url('admin.php?page=hexasync')`;
    - `plugin_version` = `BEEHEXA_VERSION`;
    - `wc_version` = `WC_VERSION`.
  - `ConnectPanel::render(): void`.
- Panel states, in order of precedence:
  1. **WooCommerce inactive** (`! class_exists('WooCommerce')`): notice "Connecting with one click
     needs WooCommerce. Install and activate WooCommerce, or follow the manual steps below."
  2. **User lacks `manage_woocommerce`:** show nothing.
  3. **Not connected:**
     - Primary button "Connect to HexaSync" (`button button-primary`, `href` = `connect_url()`,
       `target` unset).
     - Text: "You will sign in to HexaSync, choose a workspace, then approve a Read/Write
       WooCommerce API key for HexaSync on this site."
  4. **Connected:**
     - "Connected to %1$s (%2$s)" for workspace and credential name.
     - "Connected on %s", with `date_i18n(get_option('date_format'), connected_at)`.
     - "Last log received: %s", the newest `created_at` from a new repository method below, or
       "none yet".
     - "Open in HexaSync" (`dashboard_url`, `target="_blank" rel="noopener"`).
     - Disconnect form (Task 4).
- Notices:
  - `?hexasync_connected=1` **and** the option exists: success notice "Your store is connected
    to HexaSync."
  - `?hexasync_connected=1` with no option: no success notice (never trust the query).
- Modify: `src/Repository/HexaSyncLogRepository.php`: add `getLatestCreatedAt(): ?string`, a
  single `SELECT MAX(created_at)` through `prepare()` with `%i` and the usual phpcs comments.

- [ ] **Step 1: Write the failing tests** in `t_panel.sh`. Render the page through `wp eval`
  as user admin, run `ConnectPanel::render()` and capture the output:

| Test | Setup | Expect (grep on HTML) |
|---|---|---|
| `t_panel_connect_button` | no connection | contains `Connect to HexaSync` and `https://app.hexasync.com/connect/woocommerce?store_url=http%3A%2F%2F127.0.0.1%3A8973%2F&amp;admin_url=` |
| `t_panel_app_url_filter` | `add_filter('hexasync_app_url', fn() => 'https://staging.hexasync.com/')` | link starts `https://staging.hexasync.com/connect/woocommerce?` |
| `t_panel_connected` | POST a connection (Task 2 helper) | contains `Connected to Acme`, `Open in HexaSync`, no `Connect to HexaSync` |
| `t_panel_last_log` | POST one log, then render | does not contain `none yet` |
| `t_panel_query_not_trusted` | no connection, `$_GET['hexasync_connected']=1` | no `Your store is connected` |
| `t_panel_shop_manager` | render as `shopmgr` | contains `Connect to HexaSync` |
| `t_panel_editor_hidden` | render as `editor1` | no `Connect to HexaSync` |
| `t_panel_no_woo` | deactivate WooCommerce, render, reactivate | contains `needs WooCommerce` |

- [ ] **Step 2:** Run `run.sh`. Expected: the `t_panel_*` tests FAIL (class not found).
- [ ] **Step 3:** Implement `ConnectPanel` and `getLatestCreatedAt()`. Follow the inline
  card style of `AdminConnectionGuidePage`, escape every output, and print markup-bearing
  strings through `wp_kses`.
- [ ] **Step 4:** Run `run.sh`. Expected: all PASS.
- [ ] **Step 5:** Run `php tools/i18n/build.php`, add `vi` translations for each
  `UNTRANSLATED (vi)` line in `languages/hexasyncintegration-vi.po`, and rerun. Expected: no
  `UNTRANSLATED` lines.
- [ ] **Step 6:** Visual check: open `http://127.0.0.1:8973/wp-admin/admin.php?page=hexasync` in
  the browser pane in both states and take a screenshot of each.
- [ ] **Step 7: Commit** (only when told): "Connect to HexaSync button and connection panel".

### Task 4: Disconnect

**Files:**
- Create: `src/Connection/Disconnect.php`
- Modify: `src/UI/ConnectPanel.php`: add the Disconnect form and the failure state.
- Modify: `hexasyncintegration.php`: require the file and hook
  `admin_post_hexasync_disconnect → Disconnect::handle_request`.
- Test: `tools/test-env/tests/t_disconnect.sh`

**Interfaces:**
- Consumes: `ConnectionStore::get()`, `ConnectionStore::delete()`, `WooKeys::delete_key()`.
- Produces:
  - `Beehexa\Connection\Disconnect::notify(array $c): true|\WP_Error`. It POSTs to
    `$c['webhook_url']` with `wp_remote_post`:
    - timeout 15;
    - body `wp_json_encode(['event' => 'app.uninstalled', 'store_url' => home_url('/'), 'key_id' => $c['key_id']])`;
    - headers `Content-Type: application/json` and
      `X-HexaSync-Signature: base64_encode(hash_hmac('sha256', $body, $c['signing_secret'], true))`.

    A 2xx answer returns `true`. Anything else returns `WP_Error('hexasync_notify_failed', <status or transport message>)`.
  - `Disconnect::run(bool $force): true|\WP_Error`:
    - no connection → `WP_Error('hexasync_not_connected')`;
    - otherwise, unless `$force`, call `notify()` and return its error if it failed;
    - then `WooKeys::delete_key(key_id)`, whose `false` result is ignored, then
      `ConnectionStore::delete()`, and return `true`.
  - `Disconnect::handle_request(): void`:
    - `check_admin_referer('hexasync_disconnect')` and `current_user_can('manage_woocommerce')`,
      or `wp_die` with 403;
    - `$force` = `! empty($_POST['force'])`;
    - on success, `wp_safe_redirect(admin_url('admin.php?page=hexasync&hexasync_disconnected=1'))`;
    - on error, set transient `hexasync_disconnect_error_<user_id>` (300 s) to the error message
      and redirect to `admin.php?page=hexasync`.
- Panel additions:
  - **Connected:**
    - form `POST admin-post.php`, `action=hexasync_disconnect`, nonce;
    - button "Disconnect" (`button`), with `onclick` confirm "Disconnect this store from
      HexaSync? HexaSync will be told first; then this site's HexaSync API key is deleted."
  - **The error transient exists:**
    - error notice "HexaSync could not be told about the disconnect: %s";
    - buttons "Try again" (same form) and "Disconnect anyway" (same form with `force=1`);
    - then delete the transient.
  - **`?hexasync_disconnected=1` and no option:** notice "Disconnected from HexaSync."

- [ ] **Step 1: Write the failing tests** in `t_disconnect.sh`:
  - each test first POSTs a connection with
    `webhook_url = http://hxtest-fakehs:8080/api/v3/messages/abc/` (allowed because the
    environment is local) and a known secret;
  - drive `Disconnect::run()` through `wp eval`;
  - read the fake receiver's `requests.jsonl`.

| Test | Setup | Expect |
|---|---|---|
| `t_disconnect_ok` | fake status 200 | `run(false)` true; one request, `event` = `app.uninstalled`, `key_id` matches; key row gone; option gone |
| `t_disconnect_signature` | same | header `X-HexaSync-Signature` = `base64(hmac_sha256(body, secret))`, recomputed in bash with `openssl dgst -sha256 -hmac … -binary \| base64` |
| `t_disconnect_notify_fails_keeps_all` | fake status 500 | `run(false)` is WP_Error `hexasync_notify_failed`; key row present; option present |
| `t_disconnect_unreachable` | stop `hxtest-fakehs` | same as above, no PHP fatal; restart the container |
| `t_disconnect_force` | fake status 500 | `run(true)` true; no request recorded; key and option gone |
| `t_disconnect_key_already_gone` | delete the key row first, fake 200 | `run(false)` true; option gone |
| `t_disconnect_not_connected` | no option | WP_Error `hexasync_not_connected` |
| `t_disconnect_requires_nonce` | `curl` POST `admin-post.php?action=hexasync_disconnect` with an admin cookie but no nonce | 403, option still present |

- [ ] **Step 2:** Run `run.sh`. Expected: the `t_disconnect_*` tests FAIL.
- [ ] **Step 3:** Implement `Disconnect` and the panel additions.
- [ ] **Step 4:** Run `run.sh`. Expected: all PASS.
- [ ] **Step 5:** Run `php tools/i18n/build.php` and translate. Then, in the browser pane:
  1. click Disconnect with the fake receiver at 500, and screenshot the error state;
  2. click "Disconnect anyway", and screenshot the result.
- [ ] **Step 6: Commit** (only when told): "Disconnect notifies HexaSync before deleting the key".

### Task 5: 1.3.0 docs, version and release check

**Files:**
- Modify: `readme.txt`:
  - Stable tag `1.3.0`;
  - a description bullet "One-click connection";
  - a new FAQ "What does Connect to HexaSync do?" covering: sign in, approve a Read/Write key,
    HexaSync creates the webhooks and sends logs, and Disconnect tells HexaSync and then deletes
    the key;
  - the FAQ "How does HexaSync authenticate" mentions the key comes from Connect;
  - a privacy line: after Connect, the site sends HexaSync a disconnect notice when you click
    Disconnect, and nothing else;
  - the 1.3.0 changelog.
- Modify: `hexasyncintegration.php`: `Version: 1.3.0`, `BEEHEXA_VERSION` `1.3.0`.
- Modify: `CLAUDE.md`:
  - Architecture: the `Connection/` classes, the `connection` route, the
    `hexasync_connection` option in the Uninstall list, the `hexasync_app_url` filter;
  - Commands: `tools/test-env/up.sh`, `run.sh`, `down.sh`.

- [ ] **Step 1:** Make the edits.
- [ ] **Step 2:** Run `tools/test-env/run.sh`. Expected: all PASS. Then run Plugin Check as in
  Task 0 Step 2. Expected: no errors.
- [ ] **Step 3:** Run `php -l` on every PHP file with PHP 8.6 (`PHP_IMAGE=wp72-php86`) and with
  PHP 7.4 (`php:7.4-cli`). Expected: no syntax errors, which confirms the 7.4 floor.
- [ ] **Step 4: Commit** (only when told): "Release 1.3.0: Connect to HexaSync".
- [ ] **Step 5: Hold the release.** Publish 1.3.0 only after HexaSync H2–H6 are live (spec §8
  step 4) and one end-to-end connect against a real HTTPS staging store has passed. Then follow
  Task 0 Step 4 with `1.3.0`, only when told.
