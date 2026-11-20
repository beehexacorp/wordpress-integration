<?php
/**
 * HexaSyncLogAPI - REST API for managing HexaSync Logs
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
