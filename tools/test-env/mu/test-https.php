<?php
/**
 * Test-harness mu-plugin (not part of the shipped plugin).
 *
 * - WooCommerce only accepts Basic-auth consumer keys over HTTPS, so pretend the request is HTTPS.
 * - Log every failed login to wp-content/login-failed.log so tests can assert none happened.
 */
$_SERVER['HTTPS'] = 'on';

add_action('wp_login_failed', function ($username) {
    file_put_contents(
        WP_CONTENT_DIR . '/login-failed.log',
        $username . "\n",
        FILE_APPEND | LOCK_EX
    );
});
