#!/usr/bin/env bash
# Build a throwaway WordPress + WooCommerce site in Docker for testing the plugin.
#   PLUGIN_SRC=/path/to/plugin  (default: this repo's root) is mounted read-only.
#   PHP_IMAGE=php:8.6-cli       (default php:8.3-cli) to try another PHP.
# Re-runnable: the database and downloads are reused, the web and fake-HexaSync
# containers are recreated (so a new PLUGIN_SRC takes effect).
set -euo pipefail

DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PLUGIN_SRC=$(cd "${PLUGIN_SRC:-$DIR/../..}" && pwd)
SITE=$DIR/.site
WP=$SITE/wp
PORT=8973
URL=http://127.0.0.1:$PORT
IMAGE=hxtest-php
# Docker runs with userns-remap here: container root is the host user, so files stay owned by the host user.
DB_PASS=hxtest-root
ADMIN_PASS=hxtest-admin-pass
USER_PASS=hxtest-user-pass

mkdir -p "$SITE" "$SITE/fakehs"

# ---- downloads (on the host: containers have no outbound network) -----------
fetch_zip() { # url name  -> unzips into $SITE/dl-<name>/ and echoes nothing
  local url=$1 name=$2 d="$SITE/dl-$2"
  rm -rf "$d"; mkdir -p "$d"
  curl -fsSL "$url" -o "$d/$name.zip"
  unzip -q "$d/$name.zip" -d "$d/x"
}
if [ ! -f "$WP/wp-load.php" ]; then
  echo "download WordPress"
  fetch_zip https://wordpress.org/latest.zip wordpress
  rm -rf "$WP"; mv "$SITE/dl-wordpress/x/wordpress" "$WP"
fi
mkdir -p "$WP/wp-content/plugins" "$WP/wp-content/mu-plugins"
if [ ! -d "$WP/wp-content/plugins/woocommerce" ]; then
  echo "download WooCommerce"
  fetch_zip https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip woocommerce
  mv "$SITE/dl-woocommerce/x/woocommerce" "$WP/wp-content/plugins/woocommerce"
fi
if [ ! -d "$WP/wp-content/plugins/plugin-check" ]; then
  echo "download plugin-check"
  fetch_zip https://downloads.wordpress.org/plugin/plugin-check.latest-stable.zip plugin-check
  mv "$SITE/dl-plugin-check/x/plugin-check" "$WP/wp-content/plugins/plugin-check"
fi
if [ ! -f "$SITE/wp-cli.phar" ]; then
  echo "download wp-cli"
  d="$SITE/dl-wpcli"; rm -rf "$d"; mkdir -p "$d"
  curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$d/wp-cli.phar"
  mv "$d/wp-cli.phar" "$SITE/wp-cli.phar"
fi
rm -rf "$SITE"/dl-*
cp "$DIR/router.php" "$WP/router.php"
cp "$DIR/mu/test-https.php" "$WP/wp-content/mu-plugins/test-https.php"

# ---- containers --------------------------------------------------------------
docker build -q ${PHP_IMAGE:+--build-arg PHP_IMAGE="$PHP_IMAGE"} -t "$IMAGE" "$DIR" >/dev/null
docker network inspect hxtest >/dev/null 2>&1 || docker network create hxtest >/dev/null

if ! docker ps -a --format '{{.Names}}' | grep -qx hxtest-db; then
  docker run -d --name hxtest-db --network hxtest \
    -e MARIADB_ROOT_PASSWORD="$DB_PASS" -e MARIADB_DATABASE=wp mariadb:10.11 >/dev/null
fi
docker start hxtest-db >/dev/null
for i in $(seq 1 60); do
  docker exec hxtest-db mariadb -uroot -p"$DB_PASS" -e 'select 1' >/dev/null 2>&1 && break
  sleep 1
  [ "$i" = 60 ] && { echo "database did not start" >&2; exit 1; }
done

docker rm -f hxtest-wp hxtest-fakehs >/dev/null 2>&1 || true
docker run -d --name hxtest-wp --network hxtest -p 127.0.0.1:$PORT:$PORT \
  -e WP_ENVIRONMENT_TYPE=local \
  -v "$WP":/var/www/html \
  -v "$PLUGIN_SRC":/var/www/html/wp-content/plugins/hexasyncintegration:ro \
  -v "$SITE/wp-cli.phar":/usr/local/bin/wp.phar:ro \
  -w /var/www/html "$IMAGE" \
  php -d memory_limit=512M -S 0.0.0.0:$PORT -t /var/www/html /var/www/html/router.php >/dev/null
docker run -d --name hxtest-fakehs --network hxtest \
  -v "$DIR/fake-hexasync.php":/app/router.php:ro -v "$SITE/fakehs":/data \
  -w /app "$IMAGE" php -S 0.0.0.0:8080 /app/router.php >/dev/null

wpc() { docker exec hxtest-wp php -d error_reporting=0 -d display_errors=0 -d memory_limit=512M \
          /usr/local/bin/wp.phar --allow-root --path=/var/www/html "$@"; }

# ---- WordPress install -------------------------------------------------------
if [ ! -f "$WP/wp-config.php" ]; then
  wpc config create --dbname=wp --dbuser=root --dbpass="$DB_PASS" --dbhost=hxtest-db --skip-check >/dev/null
  wpc config set WP_ENVIRONMENT_TYPE local >/dev/null
fi
if ! wpc core is-installed 2>/dev/null; then
  wpc db reset --yes >/dev/null 2>&1 || true
  wpc core install --url="$URL" --title="HX Test" --admin_user=admin \
    --admin_password="$ADMIN_PASS" --admin_email=admin@example.test --skip-email >/dev/null
fi
wpc option update home "$URL" >/dev/null
wpc option update siteurl "$URL" >/dev/null
wpc rewrite structure '/%postname%/' >/dev/null
wpc plugin activate woocommerce >/dev/null 2>&1 || true
wpc plugin activate hexasyncintegration plugin-check >/dev/null
wpc user get shopmgr >/dev/null 2>&1 || wpc user create shopmgr shopmgr@example.test --role=shop_manager --user_pass="$USER_PASS" >/dev/null
wpc user get editor1 >/dev/null 2>&1 || wpc user create editor1 editor1@example.test --role=editor --user_pass="$USER_PASS" >/dev/null

# Admin Application Password, stored for the tests (lib.sh: admin_app_password).
wpc user application-password delete admin --all >/dev/null 2>&1 || true
wpc user application-password create admin hxtest --porcelain > "$SITE/admin-app-password"
chmod 600 "$SITE/admin-app-password"

: > "$SITE/fakehs/requests.jsonl"
for i in $(seq 1 30); do
  curl -fs "$URL/wp-json/" >/dev/null 2>&1 && break
  sleep 1
  [ "$i" = 30 ] && { echo "site did not answer at $URL" >&2; exit 1; }
done
echo "plugin source: $PLUGIN_SRC"
echo "ready $URL"
