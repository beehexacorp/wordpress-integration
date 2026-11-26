# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A WordPress plugin (`hexasyncintegration`, published on WordPress.org by Beehexa) that turns a WordPress site into a log sink for the HexaSync integration platform. HexaSync pushes sync-transaction logs to the site over REST; the plugin stores them in a custom table, shows them in wp-admin, and emails a digest when new ones arrive. It does not sync WooCommerce data itself: that goes through WooCommerce's own REST API keys, which the "Connection guide" admin page tells the store owner how to create.

## Commands

There is no build step, no Composer/npm and no linter config. The plugin is plain PHP loaded straight from this directory by the surrounding WordPress install (`defaultwp.local`). Tests are a Docker harness in `tools/test-env/` (bash + curl + WP-CLI, no PHPUnit): `up.sh` builds a throwaway WordPress + WooCommerce + Plugin Check site at `127.0.0.1:8973` plus a fake HexaSync, `run.sh` runs every `tests/t_*.sh` and exits non-zero on any failure, `down.sh` removes it. `PLUGIN_SRC=/path` points it at another copy of the plugin and `PHP_IMAGE` at another PHP. It needs Docker and host `curl`, `unzip` and `php` (containers have no network, so downloads happen on the host).

- Run the tests: `tools/test-env/up.sh && tools/test-env/run.sh`.
- Syntax check a file: `php -l src/API/HexaSyncLogAPI.php`
- Re-run table creation and cron scheduling: delete the `hexasync_db_version` option (or deactivate and reactivate); the next request re-runs `DatabaseSetup::install()`.
- Exercise the API (Basic Auth with a WordPress administrator's username and password):
  ```bash
  curl -u USER:PASS https://<site>/wp-json/beehexa/v1/hexasync/health
  curl -u USER:PASS -H 'Content-Type: application/json' \
    -d '{"log":{"profile_name":"P","task_name":"T","task_status":"Failed","item_id":"123"}}' \
    https://<site>/wp-json/beehexa/v1/hexasync/log
  ```

- Full request/response samples for every route live in `docs/rest-api.md`. They are kept out of `readme.txt` on purpose: the WordPress.org listing should not carry API instructions.
- Regenerate translations after adding or changing any UI string: `php tools/i18n/build.php` (see Translations below).

The `// phpcs:ignore WordPress.DB...` comments exist to pass WordPress.org's Plugin Check review. Keep them (and add the equivalent) when touching `$wpdb` queries, and keep every output escaped (`esc_html`, `esc_attr`, `esc_url`) and every input `wp_unslash` + sanitised, since the plugin is reviewed against those rules.

## Architecture

`hexasyncintegration.php` is the whole bootstrap: it `require_once`s every class file by hand and wires all hooks. **There is no autoloader**, so a new class under `src/` does nothing until it is added to that require list. Namespaces are `Beehexa\<Folder>` mirroring `src/<Folder>/`.

Data path, end to end:

1. **Auth** (`src/API/JsonBasicAuth.php`) hooks `determine_current_user` so HTTP Basic credentials are checked first as an Application Password (`wp_authenticate_application_password()` with `application_password_is_api_request` forced on, because WordPress first resolves the user in `WP->init`, before `REST_REQUEST` exists, so core skips Application Passwords on that call) and then as the account password via `wp_authenticate()`. Failures are re-wrapped with status 401, since a `WP_Error` without a status is sent as 500. Passwords are passed through unsanitized on purpose. Since 1.2.3 it only acts on `beehexa/v1` routes (`is_plugin_route()` reads `REQUEST_URI` / `?rest_route=`, because the REST route is not parsed yet at that point); the `hexasync_basic_auth_all_routes` filter restores the old site-wide behaviour.
   Since 1.2.7, `allow_woocommerce_keys()` (filter `woocommerce_rest_is_request_to_rest_api`) lets a WooCommerce consumer key authenticate `beehexa/v1`. A `ck_` user name skips the WordPress login path. Routes accept `manage_options` or `manage_woocommerce`.
2. **REST** (`src/API/HexaSyncLogAPI.php`) registers namespace `beehexa/v1/hexasync`: `GET /health`, `GET|POST /log`, `GET|POST|DELETE /log/{id}`. All routes require `manage_options` or `manage_woocommerce`.
3. **DTO** (`src/DTO/HexaSyncLogDTO.php`) is the single shape for a log row; `fromArray()`/`toArray()` use the column names directly.
4. **Repository** (`src/Repository/HexaSyncLogRepository.php`) is the only code that touches the table `{$wpdb->prefix}hexasync_log`. `save()` inserts or updates depending on whether the DTO has an id.
5. **Connection** (`src/Connection/`, `src/API/ConnectionAPI.php`, `src/API/Permissions.php`, `src/UI/ConnectPanel.php`, since 1.3.0). `ConnectionStore` keeps the connected workspace in the `hexasync_connection` option (not autoloaded; `public_view()` strips `signing_secret`). `ConnectionAPI` registers `GET|POST|DELETE beehexa/v1/hexasync/connection`, same permission as the log routes (`Permissions::can_use_api`); `POST` needs the request to be authenticated by a WooCommerce key whose id equals `key_id`. Error codes: `hexasync_invalid_connection` 400, `hexasync_key_required` 403, `hexasync_key_mismatch` 403, `hexasync_not_connected` 404; details in `docs/rest-api.md`. Re-authorizing the same `credential_id` with a new key saves the new connection, then deletes the old key only if its description starts with `HexaSync - API` (WC_Auth's name for a key approved for HexaSync) and it has the same `user_id` as the new key. `WooKeys::request_credentials()` re-implements `WC_REST_Authentication`'s precedence (SSL Basic / `consumer_key` query vs OAuth 1.0a header) so the plugin reads the same key WooCommerce would; it must stay in sync with WooCommerce. Because WooCommerce skips its check when a password or cookie already identified the user, `request_key_id()` also requires that neither this plugin's Basic auth nor an Application Password authenticated the request, that the key belongs to the current user, and on the SSL Basic path that the secret matches. `ConnectPanel` is the Connect UI on the main admin page; the HexaSync base URL is `apply_filters( 'hexasync_app_url', 'https://app.hexasync.com' )` (used for staging and the test harness). `Disconnect` (admin-post action `hexasync_disconnect`) sends a signed `app.uninstalled` POST to the stored `webhook_url` first and only then deletes the key and option; if HexaSync is unreachable nothing is deleted and the user picks "Try again" or "Disconnect anyway". `GET /health` also returns `version` (`BEEHEXA_VERSION`).
6. **Admin UI** (`src/UI/`) and **Notifications** (`src/Notifications/HexaSyncNotifications.php`) read through the repository.

Admin menu: top-level `hexasync` (connection guide and Connect panel; capability `manage_woocommerce` when WooCommerce is active, otherwise `manage_options`) and the `hexasync-logs` submenu (`manage_options`) are registered in `AdminMenu`; the Settings submenu (slug kept as `hexasync-notifications` for old links) is registered separately inside `HexaSyncNotifications::add_settings_submenu()` and holds both the email and log-retention sections.

### Things that are not obvious from one file

- **API contract quirks.** Request bodies are wrapped: `{"log": {...}}`. Update is `POST /log/{id}`, not PUT/PATCH. Create returns the bare row with 201, while get-one returns `{"data": row}` and list returns `{"data": [...], "total", "pages", "current_page"}`. HexaSync is the client, so changing any of this breaks the remote side.
- **Fields cannot be cleared via the API.** Both create and update copy a field only when `!empty(...)`, so sending `""` leaves the old value.
- **Adding a log field touches five places:** the `CREATE TABLE` in `DatabaseSetup`, a column migration (see next point), the DTO (property, getter/setter, `toArray`, `fromArray`), the repository's `$data` **and** the positional `$format` array in `save()` (order must match), and the copy-if-present blocks in both `create_log` and `update_log`. Add a column to `AdminSyncLogPage` and bump the "No logs found" `colspan` if it should be visible.
- **Schema migrations are hand-rolled.** `DatabaseSetup::install()` runs `CREATE TABLE IF NOT EXISTS` (so `dbDelta` never alters an existing table) then a `SHOW COLUMNS` / `ALTER TABLE` check per later-added column (currently `item_id`). It runs on activation and from `maybe_upgrade()` on `plugins_loaded` whenever the `hexasync_db_version` option differs from `DatabaseSetup::DB_VERSION`, because in-place updates never fire the activation hook. **Bump `DB_VERSION` whenever `install()` changes**, including when a new cron event must be scheduled on existing sites.
- **Filtering and pagination happen in PHP.** The admin log page and `GET /log` load rows via the repository and then `array_filter`/`array_slice`. The `limit`/`offset` args on `getAll()` exist but nobody passes them. `GET /log` uses `profile_name` if given and otherwise `task_name`; it never combines them (`getByProfileAndTaskName()` exists but is unused). `getCount()` is also unused and its query is not passed through `prepare()`.
- **`%i` identifier placeholders** in `$wpdb->prepare()` are why the minimum WordPress version is 6.2.
- **Timestamps.** `execute_at` / `executed_at` are free-form strings from HexaSync stored in VARCHAR columns; only `created_at` / `updated_at` are real MySQL timestamps.
- **Notification flow.** `install()` fires the custom action `hexasync_plugin_activated`, which is what schedules the `hexasync_check_new_logs` cron on a custom `twelve_hours` interval and the daily `hexasync_purge_old_logs` cron (deactivation clears both). `check_and_notify()` reads logs created since the `hexasync_last_notification_check` option, and sends one email only if some log carries an `item_id` not already in the `hexasync_notified_item_ids` option; logs without `item_id` never trigger mail. Settings live in the `hexasync_notification_settings` option (`enabled`, `emails[]`, `retention_days`; 0 = keep forever, which is the default). `sanitize_settings()` is registered through `register_setting`, so it also runs on `update_option()` from code and must accept the stored array shape as well as the form's comma-separated string. It also sets a one-hour transient that drives the admin notice.
- **Log rows link out** to `https://app.hexasync.com/monitoring/profiles/{profile_id}/jobs?type=sync-history&id={log_detail_id}`, so both ids must be stored for the "View on HexaSync" link to appear.

**Uninstall.** `uninstall.php` drops the log table and deletes every plugin option and cron event; add new options there too. `hexasync_connection` is included; deleting the plugin does not delete the WooCommerce key.

## Translations

Every admin-facing string goes through `__()` / `esc_html_e()` / `_n()` etc. with the text domain `hexasyncintegration`; REST API responses stay English on purpose, since HexaSync reads them. Strings containing markup are translated whole (`'Open <a href="%s">…</a>.'`) and printed through `wp_kses()` with an allow-list, and every `sprintf` placeholder has a `/* translators: */` comment on the line above.

`languages/` ships a `.pot` template plus Vietnamese (`hexasyncintegration-vi.po` / `.mo`). `hexasyncintegration.php` declares `Domain Path: /languages` and calls `load_plugin_textdomain()` on `init`; that call is needed for the bundled files to load, and WordPress.org language packs in `wp-content/languages/plugins/` still win when present.

There is no wp-cli or gettext on this machine, so `tools/i18n/build.php` does the job with WordPress's own POMO classes: it extracts strings from the PHP source (plus the `Plugin Name` and `Description` headers), rewrites the `.pot`, merges existing translations from `hexasyncintegration-vi.po`, and compiles the `.mo`. To change a Vietnamese translation, edit `msgstr` in the `.po` and re-run it. It reports `UNTRANSLATED (vi)` for new strings and `DROPPED` for strings no longer in the source. Only literal string arguments are extracted, so never pass a variable as the first argument of `__()`.

To see a page in Vietnamese without changing site settings, set the locale before WordPress boots in a one-off PHP process (pre-populate `$wp_filter` with `locale` and `determine_locale` filters returning `vi`, then `require wp-load.php` inside the `defaultwp_local-fpm-1` container, where the site is mounted at `/app`). Since WP 6.7 translations load just-in-time for the current locale, so forcing `plugin_locale` after boot does not work.

## Releasing

The plugin is GPLv2 or later: the header says so, `LICENSE.txt` holds the GPL v2 text, and the copyright notice sits under the plugin header. Keep any bundled third-party code GPL-compatible.

The version is written in three places that must agree: the `Version:` plugin header and `BEEHEXA_VERSION` in `hexasyncintegration.php`, and `Stable tag:` in `readme.txt` (which WordPress.org uses to pick the release). `readme.txt` is the WordPress.org listing, including its `## Changelog` section. The text domain is `hexasyncintegration`.

**Never touch `master`**: no commits, merges, rebases or pushes to it. Every version gets its own branch named after the version (`1.2.3`, `1.2.4`, …), created from the previous version's branch, and that version's work is committed and pushed there only.

### Publishing to WordPress.org

This git repo is the development source only. Publishing goes through a separate SVN checkout in the sibling folder `../hexasyncintegration-svn/` (`https://plugins.svn.wordpress.org/hexasyncintegration`):

- `trunk/` holds the plugin files: it mirrors this repo minus everything `.distignore` lists (`.git`, `.gitignore`, `.distignore`, `.superpowers`, `CLAUDE.md`, `docs`, `tools`), which leaves `hexasyncintegration.php`, `index.php`, `readme.txt`, `LICENSE.txt`, `uninstall.php`, `src/` and `languages/`. Add any new development-only file or folder to `.distignore`.
- `tags/<version>/` holds one copy per released version; `Stable tag:` in `readme.txt` must name a folder that exists here.
- `assets/` holds the WordPress.org listing images (icon, screenshots), which are not part of the plugin and do not live in this repo.

That folder also sits inside `wp-content/plugins/`, so WordPress lists `hexasyncintegration-svn/trunk` as a second copy of the plugin; never activate both. An `svn commit` there publishes to the public plugin directory, so only run it when explicitly asked.
