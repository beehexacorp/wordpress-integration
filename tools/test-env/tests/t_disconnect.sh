# Disconnect: notify HexaSync first, delete only on 2xx.
d_api=/wp-json/beehexa/v1/hexasync
d_secret=abcdefghijabcdefghijabcdefghij0123456789
d_hook=http://hxtest-fakehs:8080/api/v3/messages/abc/

type c_body >/dev/null 2>&1 || c_body() {
  printf '{"workspace_id":"ws-1","workspace_name":"Acme","credential_id":"%s","credential_name":"My store","dashboard_url":"https://app.hexasync.com/ws-1","key_id":%s,"webhook_url":"%s","signing_secret":"%s"}' \
    "${2:-cred-1}" "$1" "${4:-https://app.hexasync.com/hooks/abc}" "${3:-0123456789abcdef0123456789abcdef01234567}"
}

# d_setup: make a key and a connection pointing at the fake receiver. Sets d_k.
d_setup() {
  local ck cs
  read -r d_k ck cs < <(make_key admin read_write)
  wp_cli option delete hexasync_connection >/dev/null 2>&1
  req POST $d_api/connection "$ck:$cs" "$(c_body "$d_k" cred-d "$d_secret" "$d_hook")"
}
d_key_exists() { wp_cli eval 'global $wpdb; echo (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d", '"$1"'));'; }
d_conn_exists() { wp_cli eval 'echo get_option("hexasync_connection") ? 1 : 0;'; }
# d_run <true|false>: prints "OK" or the WP_Error code.
d_run() { wp_cli eval '$r = \Beehexa\Connection\Disconnect::run('"$1"'); echo is_wp_error($r) ? $r->get_error_code() : "OK";'; }

t_disconnect_ok() {
  d_setup; fakehs_reset 200
  assert_eq "ok: run" OK "$(d_run false)"
  assert_eq "ok: one request" 1 "$(fakehs_requests | wc -l | tr -d ' ')"
  local b; b=$(fakehs_requests | head -n1 | php -r '$l=json_decode(stream_get_contents(STDIN),true); echo $l["body"];')
  assert_eq "ok: event" app.uninstalled "$(json_get event "$b")"
  assert_eq "ok: key_id" "$d_k" "$(json_get key_id "$b")"
  assert_eq "ok: key gone" 0 "$(d_key_exists "$d_k")"
  assert_eq "ok: option gone" 0 "$(d_conn_exists)"
}
t_disconnect_signature() {
  d_setup; fakehs_reset 200
  d_run false >/dev/null
  local line b sig exp
  line=$(fakehs_requests | head -n1)
  b=$(printf '%s' "$line" | php -r '$l=json_decode(stream_get_contents(STDIN),true); echo $l["body"];')
  sig=$(printf '%s' "$line" | php -r '$l=json_decode(stream_get_contents(STDIN),true); foreach($l["headers"] as $k=>$v){ if(strtolower($k)==="x-hexasync-signature") echo $v; }')
  exp=$(printf '%s' "$b" | openssl dgst -sha256 -hmac "$d_secret" -binary | base64)
  assert_eq "signature" "$exp" "$sig"
}
t_disconnect_notify_fails_keeps_all() {
  d_setup; fakehs_reset 500
  assert_eq "500: error code" hexasync_notify_failed "$(d_run false)"
  assert_eq "500: key kept" 1 "$(d_key_exists "$d_k")"
  assert_eq "500: option kept" 1 "$(d_conn_exists)"
}
t_disconnect_unreachable() {
  d_setup; fakehs_reset 200
  docker stop hxtest-fakehs >/dev/null 2>&1
  local r; r=$(d_run false 2>&1)
  docker start hxtest-fakehs >/dev/null 2>&1
  sleep 1
  assert_eq "unreachable: error code" hexasync_notify_failed "$r"
  assert_eq "unreachable: key kept" 1 "$(d_key_exists "$d_k")"
  assert_eq "unreachable: option kept" 1 "$(d_conn_exists)"
}
t_disconnect_redirect_not_followed() {
  d_setup; fakehs_reset 302
  assert_eq "302: error code" hexasync_notify_failed "$(d_run false)"
  assert_eq "302: one request only" 1 "$(fakehs_requests | wc -l | tr -d ' ')"
  assert_eq "302: key kept" 1 "$(d_key_exists "$d_k")"
  assert_eq "302: option kept" 1 "$(d_conn_exists)"
}
t_disconnect_force() {
  d_setup; fakehs_reset 500
  assert_eq "force: run" OK "$(d_run true)"
  assert_eq "force: no request" 0 "$(fakehs_requests | wc -l | tr -d ' ')"
  assert_eq "force: key gone" 0 "$(d_key_exists "$d_k")"
  assert_eq "force: option gone" 0 "$(d_conn_exists)"
}
t_disconnect_key_already_gone() {
  d_setup; fakehs_reset 200
  delete_key "$d_k"
  assert_eq "key gone: run" OK "$(d_run false)"
  assert_eq "key gone: option gone" 0 "$(d_conn_exists)"
}
t_disconnect_not_connected() {
  wp_cli option delete hexasync_connection >/dev/null 2>&1
  assert_eq "not connected" hexasync_not_connected "$(d_run false)"
}
t_disconnect_requires_nonce() {
  d_setup; fakehs_reset 200
  local jar; jar=$(mktemp)
  curl -s -o /dev/null -c "$jar" -b 'wordpress_test_cookie=WP%20Cookie%20check' \
    --data-urlencode 'log=admin' --data-urlencode 'pwd=hxtest-admin-pass' --data-urlencode 'testcookie=1' "$HX_URL/wp-login.php"
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' -b "$jar" -X POST -d 'x=1' "$HX_URL/wp-admin/admin-post.php?action=hexasync_disconnect")
  rm -f "$jar"
  assert_eq "no nonce: status" 403 "$code"
  assert_eq "no nonce: option kept" 1 "$(d_conn_exists)"
  assert_eq "no nonce: no request" 0 "$(fakehs_requests | wc -l | tr -d ' ')"
}

# d_cookie <jar> <name prefix>: value of the first cookie in a curl jar whose name starts with the prefix.
d_cookie() { awk -v p="$2" '$6 ~ "^"p { print $7; exit }' "$1"; }
# d_post <jar> <nonce>: POST the Disconnect form. Sets $STATUS, $BODY and d_loc (Location header).
d_post() {
  local hdr tmp; hdr=$(mktemp); tmp=$(mktemp)
  STATUS=$(curl -s -o "$tmp" -D "$hdr" -w '%{http_code}' -b "$1" \
    --data-urlencode 'action=hexasync_disconnect' --data-urlencode "_wpnonce=$2" "$HX_URL/wp-admin/admin-post.php") || STATUS=000
  d_loc=$(sed -n 's/^[Ll]ocation: *//p' "$hdr" | tr -d '\r')
  BODY=$(cat "$tmp"); rm -f "$hdr" "$tmp"
}
# d_admin_nonce <jar>: the Disconnect nonce scraped from the admin page as the jar's user.
d_admin_nonce() {
  curl -s -b "$1" "$HX_URL/wp-admin/admin.php?page=hexasync" \
    | tr '\n' ' ' | grep -o 'value="hexasync_disconnect"> *<input type="hidden" name="_wpnonce" value="[0-9a-f]*"' \
    | head -n1 | sed 's/.*value="\([0-9a-f]*\)"$/\1/'
}
d_admin_id() { wp_cli user get admin --field=ID; }
t_disconnect_handler_ok() {
  d_setup; fakehs_reset 200
  local jar nonce; jar=$(mktemp)
  wp_login admin hxtest-admin-pass "$jar"
  nonce=$(d_admin_nonce "$jar")
  assert_eq "handler ok: nonce scraped" yes "$([ -n "$nonce" ] && echo yes || echo no)"
  d_post "$jar" "$nonce"; rm -f "$jar"
  assert_eq "handler ok: status" 302 "$STATUS"
  assert_eq "handler ok: redirect" yes "$(case "$d_loc" in */wp-admin/admin.php?page=hexasync\&hexasync_disconnected=1) echo yes ;; *) echo "no: $d_loc" ;; esac)"
  assert_eq "handler ok: option gone" 0 "$(d_conn_exists)"
  assert_eq "handler ok: key gone" 0 "$(d_key_exists "$d_k")"
}
t_disconnect_handler_notify_fails() {
  d_setup; fakehs_reset 500
  wp_cli transient delete "hexasync_disconnect_error_$(d_admin_id)" >/dev/null 2>&1
  local jar nonce; jar=$(mktemp)
  wp_login admin hxtest-admin-pass "$jar"
  nonce=$(d_admin_nonce "$jar")
  d_post "$jar" "$nonce"; rm -f "$jar"
  assert_eq "handler 500: status" 302 "$STATUS"
  assert_eq "handler 500: redirect" yes "$(case "$d_loc" in */wp-admin/admin.php?page=hexasync) echo yes ;; *) echo "no: $d_loc" ;; esac)"
  assert_eq "handler 500: error transient" 500 "$(wp_cli transient get "hexasync_disconnect_error_$(d_admin_id)" 2>/dev/null)"
  assert_eq "handler 500: option kept" 1 "$(d_conn_exists)"
  assert_eq "handler 500: key kept" 1 "$(d_key_exists "$d_k")"
  wp_cli transient delete "hexasync_disconnect_error_$(d_admin_id)" >/dev/null 2>&1
}
# editor1 has no manage_woocommerce and sees no panel, so the nonce is made for editor1's own
# session: the logged_in cookie from the jar gives wp_create_nonce() the same session token.
t_disconnect_handler_editor() {
  d_setup; fakehs_reset 200
  wp_cli user update editor1 --user_pass=hxtest-editor-pass >/dev/null 2>&1
  local jar nonce cookie; jar=$(mktemp)
  wp_login editor1 hxtest-editor-pass "$jar"
  cookie=$(d_cookie "$jar" wordpress_logged_in_)
  nonce=$(wp_cli eval '$_COOKIE[LOGGED_IN_COOKIE] = rawurldecode("'"$cookie"'"); echo wp_create_nonce("hexasync_disconnect");' --user=editor1)
  d_post "$jar" "$nonce"; rm -f "$jar"
  assert_eq "handler editor: status" 403 "$STATUS"
  assert_eq "handler editor: nonce was valid (capability message)" 1 "$(printf '%s' "$BODY" | grep -cF 'You do not have permission to do this.')"
  assert_eq "handler editor: option kept" 1 "$(d_conn_exists)"
  assert_eq "handler editor: key kept" 1 "$(d_key_exists "$d_k")"
  assert_eq "handler editor: no request" 0 "$(fakehs_requests | wc -l | tr -d ' ')"
}

for d_t in t_disconnect_ok t_disconnect_signature t_disconnect_notify_fails_keeps_all t_disconnect_unreachable t_disconnect_redirect_not_followed \
  t_disconnect_force t_disconnect_key_already_gone t_disconnect_not_connected t_disconnect_requires_nonce \
  t_disconnect_handler_ok t_disconnect_handler_notify_fails t_disconnect_handler_editor; do
  "$d_t"
done
wp_cli option delete hexasync_connection >/dev/null 2>&1
