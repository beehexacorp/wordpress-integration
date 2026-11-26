<?php
/**
 * ConnectionAPI - REST route that tells the plugin which HexaSync workspace it is connected to.
 *
 * @package Beehexa\API
 */

namespace Beehexa\API;

use Beehexa\Connection\ConnectionStore;
use Beehexa\Connection\WooKeys;

if ( ! defined( 'ABSPATH' ) ) exit;

class ConnectionAPI {

    const FIELDS = array(
        'workspace_id', 'workspace_name', 'credential_id', 'credential_name',
        'dashboard_url', 'key_id', 'webhook_url', 'signing_secret',
    );

    public function register_routes() {
        register_rest_route(
            HexaSyncLogAPI::REST_NAMESPACE,
            '/connection',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'save_connection' ),
                    'permission_callback' => array( Permissions::class, 'can_use_api' ),
                ),
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'get_connection' ),
                    'permission_callback' => array( Permissions::class, 'can_use_api' ),
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array( $this, 'delete_connection' ),
                    'permission_callback' => array( Permissions::class, 'can_use_api' ),
                ),
            )
        );
    }

    private function invalid( string $message ) {
        return new \WP_Error( 'hexasync_invalid_connection', $message, array( 'status' => 400 ) );
    }

    private function valid_url( string $url ): bool {
        if ( 'local' === wp_get_environment_type() ) {
            $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
            return false !== filter_var( $url, FILTER_VALIDATE_URL ) && in_array( $scheme, array( 'http', 'https' ), true );
        }
        return false !== wp_http_validate_url( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME );
    }

    public function save_connection( $request ) {
        $key_id = WooKeys::request_key_id();
        if ( null === $key_id ) {
            return new \WP_Error( 'hexasync_key_required', 'Authenticate with a WooCommerce REST API key.', array( 'status' => 403 ) );
        }

        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = $request->get_body_params();
        }
        $c = array();
        foreach ( self::FIELDS as $field ) {
            if ( ! isset( $params[ $field ] ) || '' === $params[ $field ] || ! is_scalar( $params[ $field ] ) ) {
                return $this->invalid( "Missing or invalid field: $field" );
            }
        }

        $claimed = $params['key_id'];
        if ( ! is_numeric( $claimed ) || (int) $claimed <= 0 || (string) (int) $claimed !== (string) $claimed ) {
            return $this->invalid( 'key_id must be a positive integer' );
        }
        $c['key_id'] = (int) $claimed;

        foreach ( array( 'workspace_id', 'workspace_name', 'credential_id', 'credential_name' ) as $field ) {
            $c[ $field ] = sanitize_text_field( (string) $params[ $field ] );
            if ( '' === $c[ $field ] ) {
                return $this->invalid( "Missing or invalid field: $field" );
            }
        }
        foreach ( array( 'dashboard_url', 'webhook_url' ) as $field ) {
            $url = esc_url_raw( (string) $params[ $field ] );
            if ( '' === $url || ! $this->valid_url( $url ) ) {
                return $this->invalid( "$field must be a valid https URL" );
            }
            $c[ $field ] = $url;
        }
        // Stored unchanged: sanitizing could silently alter an HMAC key.
        $secret = (string) $params['signing_secret'];
        if ( ! preg_match( '/\A[A-Za-z0-9._~+\/=-]{32,}\z/', $secret ) ) {
            return $this->invalid( 'signing_secret must be at least 32 characters from A-Z a-z 0-9 . _ ~ + / = -' );
        }
        $c['signing_secret'] = $secret;

        if ( $c['key_id'] !== $key_id ) {
            return new \WP_Error( 'hexasync_key_mismatch', 'key_id does not match the key used for this request.', array( 'status' => 403 ) );
        }

        // Re-authorize: the earlier approval's key is no longer used. Save the new connection
        // first, then delete the old key, and only one that HexaSync's approval created
        // (WC_Auth names it "HexaSync - API (<date>)") for the same user as the new key.
        $old = ConnectionStore::get();
        ConnectionStore::save( $c );
        if ( $old && isset( $old['credential_id'], $old['key_id'] )
            && $old['credential_id'] === $c['credential_id'] && (int) $old['key_id'] !== $c['key_id'] && (int) $old['key_id'] > 0 ) {
            $old_key = WooKeys::get_key( (int) $old['key_id'] );
            $new_key = WooKeys::get_key( $c['key_id'] );
            if ( $old_key && $new_key
                && 0 === strpos( (string) $old_key->description, 'HexaSync - API' )
                && (int) $old_key->user_id === (int) $new_key->user_id ) {
                WooKeys::delete_key( (int) $old['key_id'] );
            }
        }

        return new \WP_REST_Response( array( 'data' => ConnectionStore::public_view( ConnectionStore::get() ) ), 200 );
    }

    public function get_connection() {
        $c = ConnectionStore::get();
        if ( ! $c ) {
            return new \WP_Error( 'hexasync_not_connected', 'Not connected to HexaSync.', array( 'status' => 404 ) );
        }
        return new \WP_REST_Response( array( 'data' => ConnectionStore::public_view( $c ) ), 200 );
    }

    public function delete_connection() {
        ConnectionStore::delete();
        return new \WP_REST_Response( array( 'deleted' => true ), 200 );
    }
}
