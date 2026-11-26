# Authentication of the beehexa/v1 log API (1.2.7 behaviour).
API=/wp-json/beehexa/v1/hexasync
LOG='{"log":{"profile_name":"P","task_name":"T","task_status":"Failed","item_id":"auth1"}}'

login_failed_reset

read -r k_admin ck_admin cs_admin < <(make_key admin read_write)
read -r k_ro ck_ro cs_ro < <(make_key admin read)
read -r k_mgr ck_mgr cs_mgr < <(make_key shopmgr read_write)
read -r k_ed ck_ed cs_ed < <(make_key editor1 read_write)

req GET $API/health "$ck_admin:$cs_admin"
assert_eq "admin key: GET health" 200 "$STATUS"
req POST $API/log "$ck_admin:$cs_admin" "$LOG"
assert_eq "admin key: POST log" 201 "$STATUS"
assert_eq "admin key: created row has log_id" yes "$([ -n "$(json_get log_id)" ] && echo yes || echo no)"

req GET $API/health "$ck_ro:$cs_ro"
assert_eq "read-only key: GET health" 200 "$STATUS"
req POST $API/log "$ck_ro:$cs_ro" "$LOG"
assert_eq "read-only key: POST log" 401 "$STATUS"

req POST $API/log "$ck_mgr:$cs_mgr" "$LOG"
assert_eq "shop manager key: POST log" 201 "$STATUS"

req GET $API/health "$ck_ed:$cs_ed"
assert_eq "editor key: GET health" 403 "$STATUS"

req GET $API/health "$ck_admin:cs_wrongsecret"
assert_eq "wrong secret: GET health" 401 "$STATUS"

req GET /wp-json/wp/v2/users/me "$ck_admin:$cs_admin"
assert_eq "key on /wp/v2/users/me" 401 "$STATUS"

req GET $API/health "$(admin_app_password)"
assert_eq "application password: GET health" 200 "$STATUS"

req GET $API/health none
assert_eq "no credentials: GET health" 401 "$STATUS"

assert_eq "no wp_login_failed entries" 0 "$(login_failed_count)"

for k in "$k_admin" "$k_ro" "$k_mgr" "$k_ed"; do delete_key "$k"; done
