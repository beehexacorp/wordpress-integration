<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * HexaSyncLogAPI - REST API for managing HexaSync Logs
 *
 * @package Beehexa\API
 */
namespace Beehexa\API;

class JsonBasicAuth
{

    static function json_basic_auth_handler($user)
    {
        global $current_user, $beehexa_json_basic_auth_error;
        if(!empty($current_user)) {
            return $current_user->ID;
        }

        $beehexa_json_basic_auth_error = null;

        // Don't authenticate twice
        if (!empty($user)) {
            return $user;
        }

        // Check that we're trying to authenticate
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            return $user;
        }
        // Check that we're trying to authenticate
        if (!isset($_SERVER['PHP_AUTH_PW'])) {
            return $user;
        }

        $username = sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_USER']));
        $password = sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_PW']));
        /**
         * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
         * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
         * recursion and a stack overflow unless the current function is removed from the determine_current_user
         * filter during authentication.
         */
        remove_filter('determine_current_user', [static::class, 'json_basic_auth_handler'], 20);

        $user = wp_authenticate($username, $password);

        add_filter('determine_current_user', [static::class,'json_basic_auth_handler'], 20);

        if (is_wp_error($user)) {
            $beehexa_json_basic_auth_error = $user;
            return null;
        }

        $beehexa_json_basic_auth_error = true;

        return $user->ID;
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
