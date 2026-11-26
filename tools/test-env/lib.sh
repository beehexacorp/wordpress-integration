# Shared helpers for the harness. Source this file; do not execute it.
HX_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
HX_SITE=$HX_DIR/.site
HX_URL=${HX_URL:-http://127.0.0.1:8973}
HX_FAIL=0
HX_PASS_COUNT=0
HX_FAIL_COUNT=0
STATUS=""
BODY=""

# wp_cli <args>: run WP-CLI inside hxtest-wp, deprecation noise off. The container user is root,
# which docker's userns-remap maps to the host user, so files written stay owned by you.
wp_cli() {
  docker exec hxtest-wp php -d error_reporting=0 -d display_errors=0 \
    -d memory_limit=512M /usr/local/bin/wp.phar --allow-root --path=/var/www/html "$@"
}

# make_key <login> <perm> [description]   perm: read | write | read_write
# Inserts a WooCommerce REST key the way WC_Auth::create_keys does and echoes: key_id ck cs
# description defaults to "hxtest <login>"; WC_Auth names an approved key "<app_name> - API (<date>)".
make_key() {
  local login=$1 perm=$2 desc=${3:-hxtest $1} out
  out=$(wp_cli eval '
    $u = get_user_by("login", "'"$login"'");
    if (!$u) { fwrite(STDERR, "no such user\n"); exit(1); }
    global $wpdb;
    $ck = "ck_" . wc_rand_hash();
    $cs = "cs_" . wc_rand_hash();
    $wpdb->insert($wpdb->prefix . "woocommerce_api_keys", [
      "user_id"         => $u->ID,
      "description"     => base64_decode("'"$(printf '%s' "$desc" | base64 -w0)"'"),
      "permissions"     => "'"$perm"'",
      "consumer_key"    => wc_api_hash($ck),
      "consumer_secret" => $cs,
      "truncated_key"   => substr($ck, -7),
    ]);
    echo "KEY " . $wpdb->insert_id . " " . $ck . " " . $cs;
  ') || return 1
  printf '%s\n' "$out" | sed -n 's/^KEY //p' | tail -n1
}

# wp_login <login> <password> <cookie jar>: log in through wp-login.php, writing the session cookies to the jar.
wp_login() {
  curl -s -o /dev/null -c "$3" -b 'wordpress_test_cookie=WP%20Cookie%20check' \
    --data-urlencode "log=$1" --data-urlencode "pwd=$2" --data-urlencode 'testcookie=1' "$HX_URL/wp-login.php"
}

# delete_key <key_id>
delete_key() {
  wp_cli eval 'global $wpdb; $wpdb->delete($wpdb->prefix . "woocommerce_api_keys", ["key_id" => (int) "'"$1"'"]);'
}

# admin_app_password: echoes "admin:<application password>" for use as the req auth argument.
admin_app_password() { printf 'admin:%s\n' "$(cat "$HX_SITE/admin-app-password")"; }

# req <method> <path> <auth> [body] [extra curl args...]
#   auth: "ck_xxx:cs_xxx" | "user:app_password" | "user:password" | "none"
#   path: e.g. /wp-json/beehexa/v1/hexasync/health
# Sets $STATUS (HTTP code) and $BODY (response body). A body is sent as application/json.
req() {
  local method=$1 path=$2 auth=$3 body=${4-} tmp
  shift 3; [ $# -gt 0 ] && shift
  local args=(-s --max-time 30 -X "$method" -o)
  tmp=$(mktemp)
  args+=("$tmp" -w '%{http_code}')
  [ "$auth" != none ] && args+=(-u "$auth")
  [ -n "$body" ] && args+=(-H 'Content-Type: application/json' --data-binary "$body")
  STATUS=$(curl "${args[@]}" "$@" "$HX_URL$path") || STATUS=000
  BODY=$(cat "$tmp"); rm -f "$tmp"
}

# json_get <dotted.path> [json]: read a field from $BODY (or the given JSON) using host PHP.
json_get() {
  local json=${2-$BODY}
  JSON_IN=$json php -r '$d=json_decode(getenv("JSON_IN"),true);
    foreach (explode(".", $argv[1]) as $k) { if (!is_array($d) || !array_key_exists($k,$d)) exit(0); $d=$d[$k]; }
    echo is_scalar($d) ? $d : json_encode($d);' "$1"
}

# assert_eq <name> <expected> <actual>
assert_eq() {
  if [ "$2" = "$3" ]; then
    echo "PASS $1"; HX_PASS_COUNT=$((HX_PASS_COUNT+1))
  else
    echo "FAIL $1 (expected '$2', got '$3')"; HX_FAIL=1; HX_FAIL_COUNT=$((HX_FAIL_COUNT+1))
  fi
}

# fakehs_reset [status]: empty the request log and set the fake HexaSync status (default 200).
fakehs_reset() {
  : > "$HX_SITE/fakehs/requests.jsonl"
  printf '%s' "${1:-200}" > "$HX_SITE/fakehs/status"
}
# fakehs_requests: print the fake HexaSync request log (one JSON object per line).
fakehs_requests() { cat "$HX_SITE/fakehs/requests.jsonl"; }

# login_failed_reset / login_failed_count: the mu-plugin logs wp_login_failed user names here.
login_failed_reset() { : > "$HX_SITE/wp/wp-content/login-failed.log"; }
login_failed_count() { [ -f "$HX_SITE/wp/wp-content/login-failed.log" ] && wc -l < "$HX_SITE/wp/wp-content/login-failed.log" | tr -d ' ' || echo 0; }
