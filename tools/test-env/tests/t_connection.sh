# Connection store and the beehexa/v1/hexasync/connection route.
c_api=/wp-json/beehexa/v1/hexasync
c_secret=0123456789abcdef0123456789abcdef01234567

# c_body <key_id> [credential_id] [secret] [webhook_url]
c_body() {
  printf '{"workspace_id":"ws-1","workspace_name":"Acme","credential_id":"%s","credential_name":"My store","dashboard_url":"https://app.hexasync.com/ws-1","key_id":%s,"webhook_url":"%s","signing_secret":"%s"}' \
    "${2:-cred-1}" "$1" "${4:-https://app.hexasync.com/hooks/abc}" "${3:-$c_secret}"
}
c_key_exists() { wp_cli eval 'global $wpdb; echo (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d", '"$1"'));'; }

wp_cli option delete hexasync_connection >/dev/null 2>&1
read -r c_k1 c_ck1 c_cs1 < <(make_key admin read_write "HexaSync - API (test)")

t_connection_get_empty() {
  req GET $c_api/connection "$c_ck1:$c_cs1"
  assert_eq "get empty: status" 404 "$STATUS"
  assert_eq "get empty: code" hexasync_not_connected "$(json_get code)"
}
t_connection_post_ok() {
  req POST $c_api/connection "$c_ck1:$c_cs1" "$(c_body "$c_k1")"
  assert_eq "post ok: status" 200 "$STATUS"
  assert_eq "post ok: workspace_name" Acme "$(json_get data.workspace_name)"
  assert_eq "post ok: no signing_secret in body" 0 "$(printf '%s' "$BODY" | grep -c signing_secret)"
}
t_connection_get_after() {
  req GET $c_api/connection "$c_ck1:$c_cs1"
  assert_eq "get after: status" 200 "$STATUS"
  assert_eq "get after: credential_id" cred-1 "$(json_get data.credential_id)"
  assert_eq "get after: no signing_secret" 0 "$(printf '%s' "$BODY" | grep -c signing_secret)"
}
t_connection_option_not_autoloaded() {
  local c_al; c_al=$(wp_cli option get-autoload hexasync_connection)
  case "$c_al" in off|no) c_al=ok ;; esac
  assert_eq "option not autoloaded" ok "$c_al"
}
t_connection_key_mismatch() {
  req POST $c_api/connection "$c_ck1:$c_cs1" "$(c_body $((c_k1+999)))"
  assert_eq "key mismatch: status" 403 "$STATUS"
  assert_eq "key mismatch: code" hexasync_key_mismatch "$(json_get code)"
}
t_connection_rejects_app_password() {
  req POST $c_api/connection "$(admin_app_password)" "$(c_body "$c_k1")"
  assert_eq "app password: status" 403 "$STATUS"
  assert_eq "app password: code" hexasync_key_required "$(json_get code)"
}
t_connection_bad_url() {
  req POST $c_api/connection "$c_ck1:$c_cs1" "$(c_body "$c_k1" cred-1 "$c_secret" ftp://x)"
  assert_eq "bad url: status" 400 "$STATUS"
  assert_eq "bad url: code" hexasync_invalid_connection "$(json_get code)"
}
t_connection_short_secret() {
  req POST $c_api/connection "$c_ck1:$c_cs1" "$(c_body "$c_k1" cred-1 short12345)"
  assert_eq "short secret: status" 400 "$STATUS"
}
t_connection_secret_bad_chars() {
  req POST $c_api/connection "$c_ck3:$c_cs3" "$(c_body "$c_k3" cred-1 'abcdefghij%41abcdefghijabcdefghijabcd')"
  assert_eq "secret with %41: status" 400 "$STATUS"
  assert_eq "secret with %41: code" hexasync_invalid_connection "$(json_get code)"
}
t_connection_missing_field() {
  req POST $c_api/connection "$c_ck1:$c_cs1" '{"workspace_id":"ws-1"}'
  assert_eq "missing field: status" 400 "$STATUS"
  assert_eq "missing field: code" hexasync_invalid_connection "$(json_get code)"
}
t_reauthorize_deletes_old_key() {
  read -r c_k2 c_ck2 c_cs2 < <(make_key admin read_write "HexaSync - API (test)")
  req POST $c_api/connection "$c_ck2:$c_cs2" "$(c_body "$c_k2" cred-1)"
  assert_eq "reauthorize: status" 200 "$STATUS"
  assert_eq "reauthorize: old key gone" 0 "$(c_key_exists "$c_k1")"
  assert_eq "reauthorize: new key present" 1 "$(c_key_exists "$c_k2")"
}
t_other_credential_keeps_old_key() {
  read -r c_k3 c_ck3 c_cs3 < <(make_key admin read_write)
  req POST $c_api/connection "$c_ck3:$c_cs3" "$(c_body "$c_k3" cred-2)"
  assert_eq "other credential: status" 200 "$STATUS"
  assert_eq "other credential: old key kept" 1 "$(c_key_exists "$c_k2")"
  req GET $c_api/connection "$c_ck3:$c_cs3"
  assert_eq "other credential: GET shows cred-2" cred-2 "$(json_get data.credential_id)"
}
# Re-authorize only deletes a key HexaSync's approval created (description "HexaSync - API ...")
# that belongs to the same user as the new key.
t_reauthorize_keeps_foreign_key() {
  local c_ka c_cka c_csa c_kb c_ckb c_csb
  read -r c_ka c_cka c_csa < <(make_key admin read_write "Accounting app")
  req POST $c_api/connection "$c_cka:$c_csa" "$(c_body "$c_ka" cred-m)"
  read -r c_kb c_ckb c_csb < <(make_key admin read_write "HexaSync - API (test)")
  req POST $c_api/connection "$c_ckb:$c_csb" "$(c_body "$c_kb" cred-m)"
  assert_eq "reauthorize, other description: status" 200 "$STATUS"
  assert_eq "reauthorize, other description: old key kept" 1 "$(c_key_exists "$c_ka")"
  delete_key "$c_ka"; delete_key "$c_kb"
}
t_reauthorize_keeps_other_users_key() {
  local c_ka c_cka c_csa c_kb c_ckb c_csb
  read -r c_ka c_cka c_csa < <(make_key shopmgr read_write "HexaSync - API (test)")
  req POST $c_api/connection "$c_cka:$c_csa" "$(c_body "$c_ka" cred-u)"
  read -r c_kb c_ckb c_csb < <(make_key admin read_write "HexaSync - API (test)")
  req POST $c_api/connection "$c_ckb:$c_csb" "$(c_body "$c_kb" cred-u)"
  assert_eq "reauthorize, other user: status" 200 "$STATUS"
  assert_eq "reauthorize, other user: old key kept" 1 "$(c_key_exists "$c_ka")"
  delete_key "$c_ka"; delete_key "$c_kb"
}
t_request_key_id_oauth() {
  local c_out
  c_out=$(wp_cli eval '$_GET["oauth_consumer_key"]="'"$c_ck3"'"; echo (int) Beehexa\Connection\WooKeys::request_key_id();' --user=admin)
  assert_eq "request_key_id via oauth_consumer_key" "$c_k3" "$c_out"
}
t_request_key_id_precedence() {
  local c_out
  # (a) valid key A via Basic plus ?oauth_consumer_key=ck_B: WooCommerce authenticates A, so B must not win.
  req POST "$c_api/connection?oauth_consumer_key=$c_ck3" "$c_ck2:$c_cs2" "$(c_body "$c_k3" cred-9)"
  assert_eq "mixed credentials (Basic A + ?oauth B, key_id B): status" 403 "$STATUS"
  assert_eq "mixed credentials: code" hexasync_key_mismatch "$(json_get code)"
  # (b) over SSL, ?consumer_key beats PHP_AUTH_USER, as in WooCommerce.
  c_out=$(wp_cli eval '$_SERVER["HTTPS"]="on"; $_GET["consumer_key"]="'"$c_ck2"'"; $_GET["consumer_secret"]="'"$c_cs2"'"; $_SERVER["PHP_AUTH_USER"]="'"$c_ck3"'"; $_SERVER["PHP_AUTH_PW"]="y"; echo (int) Beehexa\Connection\WooKeys::request_key_id();' --user=admin)
  assert_eq "ssl: GET consumer_key beats PHP_AUTH_USER" "$c_k2" "$c_out"
  # (c) OAuth header overrides GET/POST.
  c_out=$(wp_cli eval '$_SERVER["HTTP_AUTHORIZATION"]="OAuth oauth_consumer_key=\"'"$c_ck2"'\",oauth_nonce=\"n\""; $_GET["oauth_consumer_key"]="'"$c_ck3"'"; echo (int) Beehexa\Connection\WooKeys::request_key_id();' --user=admin)
  assert_eq "oauth header beats GET oauth_consumer_key" "$c_k2" "$c_out"
}
# A user already identified by an Application Password (or a cookie) makes WooCommerce skip its own
# check, so a consumer key in the query must not be taken as the authenticating key.
t_app_password_with_key_in_query() {
  req POST "$c_api/connection?consumer_key=$c_ck3&consumer_secret=x" "$(admin_app_password)" "$(c_body "$c_k3" cred-q)"
  assert_eq "app password + ?consumer_key: status" 403 "$STATUS"
  assert_eq "app password + ?consumer_key: code" hexasync_key_required "$(json_get code)"
  req POST "$c_api/connection?oauth_consumer_key=$c_ck3" "$(admin_app_password)" "$(c_body "$c_k3" cred-q)"
  assert_eq "app password + ?oauth_consumer_key: status" 403 "$STATUS"
  assert_eq "app password + ?oauth_consumer_key: code" hexasync_key_required "$(json_get code)"
}
# The key belongs to another user than the one WordPress identified.
t_request_key_id_other_user() {
  local c_out
  c_out=$(wp_cli eval '$_GET["oauth_consumer_key"]="'"$c_ck3"'"; var_export(Beehexa\Connection\WooKeys::request_key_id());' --user=shopmgr)
  assert_eq "request_key_id: key of another user" NULL "$c_out"
  c_out=$(wp_cli eval '$_SERVER["HTTPS"]="on"; $_GET["consumer_key"]="'"$c_ck3"'"; $_GET["consumer_secret"]="cs_wrong"; var_export(Beehexa\Connection\WooKeys::request_key_id());' --user=admin)
  assert_eq "request_key_id: ssl with wrong secret" NULL "$c_out"
  # Over plain HTTP the OAuth path is taken; the plugin's own Basic auth having identified the user
  # (Application Password or account password) means WooCommerce never looked at the key.
  c_out=$(wp_cli eval 'unset($_SERVER["HTTPS"]); $GLOBALS["beehexa_json_basic_auth_error"] = true; $_GET["oauth_consumer_key"]="'"$c_ck3"'"; var_export(Beehexa\Connection\WooKeys::request_key_id());' --user=admin)
  assert_eq "request_key_id: user identified by plugin Basic auth" NULL "$c_out"
}
t_connection_secret_trailing_newline() {
  req POST $c_api/connection "$c_ck3:$c_cs3" "$(c_body "$c_k3" cred-1 'abcdefghijabcdefghijabcdefghij0123456789\n')"
  assert_eq "secret with trailing newline: status" 400 "$STATUS"
  assert_eq "secret with trailing newline: code" hexasync_invalid_connection "$(json_get code)"
}
t_connection_rest_route_form() {
  req GET "/?rest_route=/beehexa/v1/hexasync/connection" "$c_ck3:$c_cs3"
  assert_eq "rest_route form: status" 200 "$STATUS"
}
t_connection_delete() {
  req DELETE $c_api/connection "$c_ck3:$c_cs3"
  assert_eq "delete: status" 200 "$STATUS"
  assert_eq "delete: deleted" 1 "$(json_get deleted)"
  req GET $c_api/connection "$c_ck3:$c_cs3"
  assert_eq "delete: GET then 404" 404 "$STATUS"
  assert_eq "delete: key kept" 1 "$(c_key_exists "$c_k3")"
  req DELETE $c_api/connection "$c_ck3:$c_cs3"
  assert_eq "delete: idempotent" 200 "$STATUS"
}
t_health_version() {
  local c_ver; c_ver=$(sed -n "s/^define( 'BEEHEXA_VERSION', '\(.*\)' );/\1/p" "$HX_DIR/../../hexasyncintegration.php")
  req GET $c_api/health "$c_ck3:$c_cs3"
  assert_eq "health version" "$c_ver" "$(json_get version)"
}
# Last: uninstall.php also drops the log table and hexasync_db_version.
t_uninstall_removes_option() {
  req POST $c_api/connection "$c_ck3:$c_cs3" "$(c_body "$c_k3" cred-3)"
  wp_cli eval 'define("WP_UNINSTALL_PLUGIN", 1); include WP_PLUGIN_DIR . "/hexasyncintegration/uninstall.php";' >/dev/null 2>&1
  wp_cli option get hexasync_connection >/dev/null 2>&1
  assert_eq "uninstall removes option (get fails)" 1 "$?"
  req GET $c_api/health "$c_ck3:$c_cs3"   # maybe_upgrade recreates the log table
}

for c_t in t_connection_get_empty t_connection_post_ok t_connection_get_after t_connection_option_not_autoloaded \
  t_connection_key_mismatch t_connection_rejects_app_password t_connection_bad_url t_connection_short_secret \
  t_connection_missing_field t_reauthorize_deletes_old_key t_other_credential_keeps_old_key t_reauthorize_keeps_foreign_key t_reauthorize_keeps_other_users_key \
  t_request_key_id_oauth t_request_key_id_precedence t_connection_secret_bad_chars \
  t_app_password_with_key_in_query t_request_key_id_other_user t_connection_secret_trailing_newline \
  t_connection_rest_route_form t_connection_delete t_health_version t_uninstall_removes_option; do
  "$c_t"
done
for c_k in "$c_k1" "$c_k2" "$c_k3"; do delete_key "$c_k"; done
