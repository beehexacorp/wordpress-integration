# HexaSync REST API

Internal reference, not shipped to WordPress.org.

HexaSync writes logs through the plugin's REST API. You can call it yourself to test the connection or to send logs from another system.

* Base URL: `https://example.com/wp-json/beehexa/v1/hexasync` (replace `example.com` with your site).
* Authentication (1.2.7+): on a WooCommerce store, the store's WooCommerce REST API key with Read/Write permission, belonging to an administrator or shop manager: `curl -u "ck_…:cs_…"` over HTTPS, OAuth 1.0a over HTTP, as for `wc/v3`. WooCommerce checks the key. Otherwise, HTTP Basic Auth for a WordPress administrator with an Application Password (Users → Profile → Application Passwords) rather than the account password. Always use HTTPS.
* Request bodies are JSON, with the log fields wrapped in a `log` object.

In the examples, `APP_PASSWORD` is the Application Password, with or without its spaces.

Check the connection:

```bash
curl -u "USERNAME:APP_PASSWORD" https://example.com/wp-json/beehexa/v1/hexasync/health
```

```json
{"status":"ok","message":"HexaSync API is running","version":"1.3.0"}
```

Create a log (`profile_name` and `task_name` are required):

```bash
curl -u "USERNAME:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"log":{
        "profile_id":"7f3c2a10-0000-4000-8000-000000000001",
        "profile_name":"WooCommerce to QuickBooks",
        "task_id":"7f3c2a10-0000-4000-8000-000000000002",
        "task_name":"Sync orders",
        "task_status":"Failed",
        "action_type":"Automatic",
        "log_detail_id":"7f3c2a10-0000-4000-8000-000000000003",
        "reference_info":"Order #1045",
        "item_id":"1045",
        "message":"Order could not be created in QuickBooks",
        "error":"Customer not found",
        "executed_at":"Nov 20, 2026 09:15:02",
        "retry_count":1}}' \
  https://example.com/wp-json/beehexa/v1/hexasync/log
```

The response is `201 Created` with the stored log, including its `log_id`:

```json
{"log_id":"1","profile_name":"WooCommerce to QuickBooks","task_name":"Sync orders","task_status":"Failed", ... ,"created_at":"2026-11-20 07:57:52","updated_at":"2026-11-20 07:57:52"}
```

Other optional fields are `execute_at` and `push_note`. `profile_id` and `log_detail_id` are needed for the "View on HexaSync" link on the Logs page, and `item_id` is what triggers an email notification.

Update a log (send only the fields to change; empty values are ignored, so a field cannot be cleared):

```bash
curl -u "USERNAME:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"log":{"task_status":"Completed","retry_count":2}}' \
  https://example.com/wp-json/beehexa/v1/hexasync/log/1
```

Get one log, which returns `{"data":{...}}`:

```bash
curl -u "USERNAME:APP_PASSWORD" https://example.com/wp-json/beehexa/v1/hexasync/log/1
```

List logs, newest first. Filter by `profile_name` or `task_name` (partial match; if both are given, only `profile_name` is used) and page with `page` and `per_page` (default 10):

```bash
curl -u "USERNAME:APP_PASSWORD" "https://example.com/wp-json/beehexa/v1/hexasync/log?profile_name=QuickBooks&page=1&per_page=20"
```

```json
{"data":[{"log_id":"1", ... }],"total":1,"pages":1,"current_page":1}
```

Delete a log:

```bash
curl -u "USERNAME:APP_PASSWORD" -X DELETE https://example.com/wp-json/beehexa/v1/hexasync/log/1
```

```json
{"message":"Log deleted successfully"}
```

Errors: `400` when the `log` object or a required field is missing, `401` for missing or wrong credentials (`rest_forbidden`, `incorrect_password` or `invalid_username`), `403` when the user is neither an administrator nor a user who can manage WooCommerce (shop manager), and `404` with `{"error":"Log not found"}` for an unknown `log_id`.

## Connection

HexaSync calls this route after the store owner connects with one click, to tell the plugin which workspace it is connected to. It needs a WooCommerce REST API key (not an Application Password), and `key_id` must be the key that authenticates the request.

```bash
curl -u "ck_…:cs_…" \
  -H "Content-Type: application/json" \
  -d '{"workspace_id":"ws-1","workspace_name":"Acme","credential_id":"cred-1","credential_name":"My store",
       "dashboard_url":"https://app.hexasync.com/ws-1","key_id":12,
       "webhook_url":"https://app.hexasync.com/hooks/abc","signing_secret":"<at least 32 characters>"}' \
  https://example.com/wp-json/beehexa/v1/hexasync/connection
```

`POST` returns `200 {"data": {...}}` with the stored connection and `connected_at`, without `signing_secret`. `GET` returns the same, and `DELETE` returns `200 {"deleted": true}` (it removes the stored connection only, never a key, and can be repeated).

| Status | Code | When |
|---|---|---|
| 400 | `hexasync_invalid_connection` | A field is missing or invalid (the message names it). `dashboard_url` and `webhook_url` must be `https`; `signing_secret` needs 32+ characters |
| 403 | `hexasync_key_required` | The request was not authenticated with a WooCommerce key |
| 403 | `hexasync_key_mismatch` | `key_id` is not the key that authenticated the request |
| 404 | `hexasync_not_connected` | `GET` with no stored connection |

Re-authorizing (the same `credential_id` with a new `key_id`) saves the new connection, then deletes the earlier key if WooCommerce created it for a HexaSync approval (its description starts with `HexaSync - API`) and it belongs to the same user as the new key; any other key is left alone. A different `credential_id` deletes nothing.

The key must be the one WooCommerce authenticated: a request whose user was identified by an Application Password or account password gets `hexasync_key_required` even if it also carries `consumer_key` or `oauth_consumer_key`, and the key must belong to the authenticated user.
