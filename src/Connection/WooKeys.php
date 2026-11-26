<?php
/**
 * Helpers for the WooCommerce REST API key that authenticated the current request.
 *
 * @package Beehexa\Connection
 */

namespace Beehexa\Connection;

if ( ! defined( 'ABSPATH' ) ) exit;

class WooKeys {

    /**
     * The consumer key this request carries, read with WC_REST_Authentication's precedence so
     * mixed credentials cannot make the plugin pick a different key than WooCommerce would:
     * 1. over SSL, Basic auth: $_GET consumer_key (+consumer_secret) wins over PHP_AUTH_USER (+PW);
     * 2. otherwise OAuth 1.0a: array_merge($_GET, $_POST), overridden by the Authorization: OAuth header.
     *
     * @return array{0: string, 1: ?string} The consumer key ('' when none) and, on the SSL Basic
     *                                      path, the consumer secret sent with it (null on OAuth).
     */
    private static function request_credentials(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( is_ssl() ) {
            $key    = '';
            $secret = '';
            if ( ! empty( $_GET['consumer_key'] ) && ! empty( $_GET['consumer_secret'] ) ) {
                $key    = wp_unslash( $_GET['consumer_key'] );
                $secret = wp_unslash( $_GET['consumer_secret'] );
            }
            if ( ! $key && ! empty( $_SERVER['PHP_AUTH_USER'] ) && ! empty( $_SERVER['PHP_AUTH_PW'] ) ) {
                $key    = wp_unslash( $_SERVER['PHP_AUTH_USER'] );
                $secret = wp_unslash( $_SERVER['PHP_AUTH_PW'] );
            }
            if ( $key && $secret ) {
                return ( is_string( $key ) && is_string( $secret ) ) ? array( $key, $secret ) : array( '', null );
            }
        }

        $params = wp_unslash( array_merge( $_GET, $_POST ) );
        // phpcs:enable

        $header = '';
        if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            $header = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        } elseif ( function_exists( 'getallheaders' ) ) {
            foreach ( getallheaders() as $name => $value ) {
                if ( 'authorization' === strtolower( $name ) ) {
                    $header = $value;
                    break;
                }
            }
        }
        $header = trim( (string) $header );
        // Same rules as WC_REST_Authentication::parse_header(); header values override GET/POST.
        if ( 'OAuth ' === substr( $header, 0, 6 ) && preg_match_all( '/(oauth_[a-z_-]*)=(:?"([^"]*)"|([^,]*))/', $header, $m ) ) {
            foreach ( $m[1] as $i => $name ) {
                $params[ $name ] = urldecode( empty( $m[3][ $i ] ) ? $m[4][ $i ] : $m[3][ $i ] );
            }
        }

        $key = ( ! empty( $params['oauth_consumer_key'] ) && is_string( $params['oauth_consumer_key'] ) ) ? $params['oauth_consumer_key'] : '';
        return array( $key, null );
    }

    /**
     * key_id of the WooCommerce key that authenticated this request, or null.
     *
     * WooCommerce does not expose which key it accepted, and it skips its own check entirely when
     * something else (an Application Password, this plugin's Basic auth, a login cookie) already
     * identified the user. So a key found in the request is only trusted when:
     * - the user was not identified by a password: neither this plugin's Basic auth nor an
     *   Application Password authenticated the request;
     * - the key belongs to the current user;
     * - on the SSL Basic path, the secret sent with it matches the key's secret.
     * On the OAuth 1.0a path the signature is WooCommerce's to verify; with the checks above, the
     * user can only have been identified by WooCommerce's key check or by a cookie of the key's
     * own owner.
     */
    public static function request_key_id(): ?int {
        if ( ! function_exists( 'wc_api_hash' ) ) {
            return null;
        }
        if ( true === ( $GLOBALS['beehexa_json_basic_auth_error'] ?? null )
            || ! empty( $GLOBALS['wp_rest_application_password_uuid'] ) ) {
            return null;
        }
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return null;
        }
        list( $consumer_key, $secret ) = self::request_credentials();
        if ( '' === $consumer_key ) {
            return null;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT key_id, user_id, consumer_secret FROM %i WHERE consumer_key = %s',
            $wpdb->prefix . 'woocommerce_api_keys',
            wc_api_hash( $consumer_key )
        ) );
        if ( ! $row || (int) $row->user_id !== $user_id ) {
            return null;
        }
        if ( null !== $secret && ! hash_equals( (string) $row->consumer_secret, $secret ) ) {
            return null;
        }
        return (int) $row->key_id;
    }

    /**
     * The owner and description of a WooCommerce REST API key, or null when it does not exist.
     *
     * @return object{user_id: string, description: string}|null
     */
    public static function get_key( int $key_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT key_id, user_id, description FROM %i WHERE key_id = %d',
            $wpdb->prefix . 'woocommerce_api_keys',
            $key_id
        ) );
        return $row ? $row : null;
    }

    /**
     * Delete a WooCommerce REST API key. True when a row went.
     */
    public static function delete_key( int $key_id ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => $key_id ), array( '%d' ) );
        return (bool) $deleted;
    }
}
