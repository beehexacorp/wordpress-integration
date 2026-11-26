<?php
/** Router for `php -S` so WordPress pretty permalinks (/wp-json/...) work. */
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $root . $path;
if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server serve static files and real .php scripts
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php') && $path !== '/') {
    // directories such as /wp-admin/ fall through to WordPress below unless they have an index
    $_SERVER['SCRIPT_NAME']     = rtrim($path, '/') . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = rtrim($file, '/') . '/index.php';
    chdir(rtrim($file, '/'));
    require rtrim($file, '/') . '/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
chdir($root);
require $root . '/index.php';
