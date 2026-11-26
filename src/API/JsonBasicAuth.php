<?php
/**
 * JsonBasicAuth - HTTP Basic Authentication for the plugin's beehexa/v1 REST routes
 *
 * Based on the WP REST API team's Basic Auth plugin (https://github.com/WP-API/Basic-Auth),
 * GPLv2 or later, the same license as this plugin.
 *
 * @package Beehexa\API
 */
namespace Beehexa\API;

if ( ! defined( 'ABSPATH' ) ) exit;
class JsonBasicAuth
{

    static function json_basic_auth_handler($user)
    {
        global $beehexa_json_basic_auth_error;

        // Someone else (core Application Passwords, WooCommerce API keys, a login
        // cookie) already identified the user.
        if (!empty($user)) {
            return $user;
        }

        // Only handle this plugin's own REST routes; the rest of the site keeps
        // WordPress's own authentication (cookies, Application Passwords).
        if (!static::is_plugin_route()) {
            return $user;
        }

        // Check that we're trying to authenticate
        if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            return $user;
        }

        // A WooCommerce consumer key is WooCommerce's to check (see allow_woocommerce_keys()).
        // Trying it as a WordPress login would fail and count as a failed login attempt,
        // which login-limiting plugins can answer by blocking HexaSync's IP address.
        if (static::is_woocommerce_key(sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_USER'])))) {
            return $user;
        }

        $beehexa_json_basic_auth_error = null;

        $username = sanitize_user(wp_unslash($_SERVER['PHP_AUTH_USER']));
        // Passwords are compared as typed, like WordPress core does; sanitizing would change them.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $password = wp_unslash($_SERVER['PHP_AUTH_PW']);

        /**
         * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
         * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
         * recursion and a stack overflow unless the current function is removed from the determine_current_user
         * filter during authentication.
         */
        remove_filter('determine_current_user', [static::class, 'json_basic_auth_handler'], 20);

        // Application Passwords first. WordPress first asks for the current user in WP->init,
        // before REST_REQUEST is defined, so core skips Application Passwords on that call;
        // is_plugin_route() has already established that this is a REST API request.
        add_filter('application_password_is_api_request', '__return_true');
        $authenticated = wp_authenticate_application_password(null, $username, $password);
        remove_filter('application_password_is_api_request', '__return_true');

        // Then the account password.
        if (!$authenticated instanceof \WP_User) {
            $authenticated = wp_authenticate($username, $password);
        }

        add_filter('determine_current_user', [static::class,'json_basic_auth_handler'], 20);

        if (is_wp_error($authenticated)) {
            // Plain-text message and a 401 status (a WP_Error without one is sent as a 500).
            $beehexa_json_basic_auth_error = new \WP_Error(
                $authenticated->get_error_code(),
                wp_strip_all_tags($authenticated->get_error_message()),
                ['status' => 401]
            );
            return null;
        }

        $beehexa_json_basic_auth_error = true;

        return $authenticated->ID;
    }

    /**
     * Whether the current request targets the beehexa/v1 REST namespace, either
     * through pretty permalinks (/wp-json/beehexa/v1/...) or ?rest_route=/beehexa/v1/...
     *
     * determine_current_user runs before the REST server parses the route, so this
     * reads the request URI directly.
     */
    static function is_plugin_route()
    {
        /**
         * Filters whether Basic Authentication is accepted on every REST route
         * instead of only the plugin's own beehexa/v1 routes (the 1.2.2 behaviour).
         */
        if (apply_filters('hexasync_basic_auth_all_routes', false)) {
            return true;
        }

        return static::is_plugin_namespace();
    }

    /**
     * Lets a WooCommerce REST API key (consumer key and secret) authenticate the
     * plugin's beehexa/v1 routes, so HexaSync can write logs with the key the store
     * already gave it for the WooCommerce connection, without a WordPress password.
     *
     * Hooked to woocommerce_rest_is_request_to_rest_api. WooCommerce then checks the
     * key, its Read/Write permission against the request method, and records its last
     * access, exactly as on its own routes. Only this plugin's namespace is added,
     * whatever hexasync_basic_auth_all_routes says.
     *
     * @param bool $is_request_to_rest_api Whether WooCommerce already treats the request as its own.
     * @return bool
     */
    static function allow_woocommerce_keys($is_request_to_rest_api)
    {
        return $is_request_to_rest_api || static::is_plugin_namespace();
    }

    /**
     * Whether a Basic Auth user name is a WooCommerce consumer key.
     *
     * @param string $username User name from the Authorization header.
     * @return bool
     */
    static function is_woocommerce_key($username)
    {
        return class_exists('WooCommerce', false) && strpos($username, 'ck_') === 0;
    }

    /**
     * Whether the request URI names a beehexa/v1 route.
     */
    static function is_plugin_namespace()
    {
        $namespace = '/beehexa/v1/';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check, no state change.
        if (isset($_GET['rest_route'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $route = '/' . ltrim(sanitize_text_field(wp_unslash($_GET['rest_route'])), '/');
            return strpos($route . '/', $namespace) === 0;
        }

        if (empty($_SERVER['REQUEST_URI'])) {
            return false;
        }
        $path = (string) wp_parse_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH);

        return strpos($path . '/', '/' . rest_get_url_prefix() . $namespace) !== false;
    }

    static function json_basic_auth_error($error)
    {
        // Passthrough other errors
        if (!empty($error)) {
            return $error;
        }

        global $beehexa_json_basic_auth_error;

        return $beehexa_json_basic_auth_error;
    }
}
