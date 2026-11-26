# Connect panel on the main admin page (ConnectPanel::render()).
p_api=/wp-json/beehexa/v1/hexasync

# p_render <user> [php prelude]: echo the panel HTML rendered as <user>.
p_render() {
  wp_cli eval "${2-} \Beehexa\UI\ConnectPanel::render();" --user="$1"
}
p_connect() {
  local k ck cs
  read -r k ck cs < <(make_key admin read_write)
  req POST $p_api/connection "$ck:$cs" "$(c_body "$k")" >/dev/null 2>&1
  delete_key "$k"
}
p_has() { printf '%s' "$2" | grep -cF -- "$1" | tr -d ' '; }

wp_cli option delete hexasync_connection >/dev/null 2>&1
# c_body lives in t_connection.sh; define it here too in case that file is absent.
type c_body >/dev/null 2>&1 || c_body() {
  printf '{"workspace_id":"ws-1","workspace_name":"Acme","credential_id":"cred-1","credential_name":"My store","dashboard_url":"https://app.hexasync.com/ws-1","key_id":%s,"webhook_url":"https://app.hexasync.com/hooks/abc","signing_secret":"0123456789abcdef0123456789abcdef01234567"}' "$1"
}

t_panel_connect_button() {
  wp_cli option delete hexasync_connection >/dev/null 2>&1
  local out enc
  out=$(p_render admin)
  enc=$(wp_cli eval 'echo rawurlencode(home_url("/"));')
  assert_eq "button text" 1 "$(p_has 'Connect to HexaSync' "$out")"
  assert_eq "button url" 1 "$(p_has "https://app.hexasync.com/connect/woocommerce?store_url=${enc}&amp;admin_url=" "$out")"
  assert_eq "button has no target" 0 "$(p_has 'target=' "$out")"
}
t_panel_app_url_filter() {
  local out
  out=$(p_render admin "add_filter('hexasync_app_url', function () { return 'https://staging.hexasync.com/'; });")
  assert_eq "filter: staging url" 1 "$(p_has 'href="https://staging.hexasync.com/connect/woocommerce?' "$out")"
}
t_panel_query_not_trusted() {
  local out
  out=$(p_render admin '$_GET["hexasync_connected"] = "1";')
  assert_eq "query without option: no notice" 0 "$(p_has 'Your store is connected' "$out")"
}
t_panel_connected() {
  p_connect
  local out
  out=$(p_render admin)
  assert_eq "connected: workspace" 1 "$(p_has 'Connected to Acme' "$out")"
  assert_eq "connected: open link" 1 "$(p_has 'Open in HexaSync' "$out")"
  assert_eq "connected: no connect button" 0 "$(p_has 'Connect to HexaSync' "$out")"
  assert_eq "connected: none yet" 1 "$(p_has 'none yet' "$out")"
  assert_eq "connected: no secret" 0 "$(p_has 0123456789abcdef "$out")"
  out=$(p_render admin '$_GET["hexasync_connected"] = "1";')
  assert_eq "connected + query: notice" 1 "$(p_has 'Your store is connected' "$out")"
}
t_panel_last_log() {
  local k ck cs out
  read -r k ck cs < <(make_key admin read_write)
  req POST $p_api/log "$ck:$cs" '{"log":{"profile_name":"P","task_name":"T","task_status":"Failed","item_id":"panel1"}}'
  delete_key "$k"
  out=$(p_render admin)
  assert_eq "last log: not none yet" 0 "$(p_has 'none yet' "$out")"
  assert_eq "last log: label" 1 "$(p_has 'Last log received:' "$out")"
}
# p_page <login> <password>: fetch wp-admin/admin.php?page=hexasync over HTTP as <login>.
# Sets $STATUS and $BODY like req.
p_page() {
  local jar tmp; jar=$(mktemp); tmp=$(mktemp)
  wp_login "$1" "$2" "$jar"
  STATUS=$(curl -s -o "$tmp" -w '%{http_code}' -b "$jar" "$HX_URL/wp-admin/admin.php?page=hexasync") || STATUS=000
  BODY=$(cat "$tmp"); rm -f "$jar" "$tmp"
}
# "Connected on" is shown in the site's time zone, not UTC.
t_panel_connected_on_timezone() {
  p_connect
  local p_tz p_df out
  p_tz=$(wp_cli option get timezone_string 2>/dev/null); p_df=$(wp_cli option get date_format 2>/dev/null)
  wp_cli option update timezone_string Asia/Ho_Chi_Minh >/dev/null 2>&1
  wp_cli option update date_format Y-m-d >/dev/null 2>&1
  # 2026-01-01 20:00 UTC is 2026-01-02 03:00 in Ho Chi Minh City.
  wp_cli eval '$c = get_option("hexasync_connection"); $c["connected_at"] = 1767297600; update_option("hexasync_connection", $c, false);' >/dev/null 2>&1
  out=$(p_render admin)
  wp_cli option update timezone_string "$p_tz" >/dev/null 2>&1
  wp_cli option update date_format "$p_df" >/dev/null 2>&1
  assert_eq "connected on: site time zone" 1 "$(p_has 'Connected on 2026-01-02' "$out")"
}
t_panel_shop_manager() {
  wp_cli option delete hexasync_connection >/dev/null 2>&1
  assert_eq "shop manager sees button" 1 "$(p_has 'Connect to HexaSync' "$(p_render shopmgr)")"
  wp_cli user update shopmgr --user_pass=hxtest-shopmgr-pass >/dev/null 2>&1
  p_page shopmgr hxtest-shopmgr-pass
  assert_eq "shop manager: admin page status" 200 "$STATUS"
  assert_eq "shop manager: admin page has button" 1 "$(p_has 'Connect to HexaSync' "$BODY")"
  wp_cli user update editor1 --user_pass=hxtest-editor-pass >/dev/null 2>&1
  p_page editor1 hxtest-editor-pass
  local p_denied=no
  { [ "$STATUS" = 403 ] || printf '%s' "$BODY" | grep -q 'not allowed'; } && p_denied=yes
  assert_eq "editor: admin page denied" yes "$p_denied"
  assert_eq "editor: admin page has no button" 0 "$(p_has 'Connect to HexaSync' "$BODY")"
}
t_panel_editor_hidden() {
  wp_cli option delete hexasync_connection >/dev/null 2>&1
  assert_eq "editor sees no button" 0 "$(p_has 'Connect to HexaSync' "$(p_render editor1)")"
  assert_eq "editor sees nothing" 0 "$(p_has 'hexasync' "$(p_render editor1)")"
}
t_panel_no_woo() {
  local out
  wp_cli plugin deactivate woocommerce >/dev/null 2>&1
  out=$(p_render admin)
  wp_cli plugin activate woocommerce >/dev/null 2>&1
  assert_eq "no woo: notice" 1 "$(p_has 'needs WooCommerce' "$out")"
  assert_eq "no woo: no button" 0 "$(p_has 'Connect to HexaSync' "$out")"
  assert_eq "woo reactivated" active "$(wp_cli plugin get woocommerce --field=status)"
}

for p_t in t_panel_connect_button t_panel_app_url_filter t_panel_query_not_trusted t_panel_connected \
  t_panel_last_log t_panel_connected_on_timezone t_panel_shop_manager t_panel_editor_hidden t_panel_no_woo; do
  "$p_t"
done
wp_cli plugin activate woocommerce >/dev/null 2>&1
wp_cli option delete hexasync_connection >/dev/null 2>&1
