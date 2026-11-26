<?php
/**
 * Disconnect: tell HexaSync first, then delete the WooCommerce key and the stored connection.
 *
 * @package Beehexa\Connection
 */

namespace Beehexa\Connection;

if ( ! defined( 'ABSPATH' ) ) exit;

class Disconnect {

    /**
     * Send the signed app.uninstalled event to the credential's webhook URL.
     *
     * @return true|\WP_Error true on a 2xx answer.
     */
    public static function notify( array $c ) {
        $body = wp_json_encode( [
            'event'     => 'app.uninstalled',
            'store_url' => home_url( '/' ),
            'key_id'    => $c['key_id'] ?? 0,
        ] );

        $args = [
            'timeout'     => 15,
            // A redirect would turn the POST into a GET and could forward the signed request elsewhere.
            'redirection' => 0,
            'body'    => $body,
            'headers' => [
                'Content-Type'          => 'application/json',
                'X-HexaSync-Signature'  => base64_encode( hash_hmac( 'sha256', $body, (string) ( $c['signing_secret'] ?? '' ), true ) ),
            ],
        ];
        $url = (string) ( $c['webhook_url'] ?? '' );

        // wp_safe_remote_post refuses local and private addresses. A 'local' environment (the
        // same one that lets ConnectionAPI accept http webhook URLs) may point at a HexaSync
        // running on the developer's network, so it uses wp_remote_post.
        $response = 'local' === wp_get_environment_type()
            ? wp_remote_post( $url, $args )
            : wp_safe_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'hexasync_notify_failed', $response->get_error_message() );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( $status < 200 || $status > 299 ) {
            return new \WP_Error( 'hexasync_notify_failed', (string) $status );
        }
        return true;
    }

    /**
     * @return true|\WP_Error
     */
    public static function run( bool $force ) {
        $c = ConnectionStore::get();
        if ( ! $c ) {
            return new \WP_Error( 'hexasync_not_connected', __( 'This store is not connected to HexaSync.', 'hexasyncintegration' ) );
        }

        if ( ! $force ) {
            $sent = self::notify( $c );
            if ( is_wp_error( $sent ) ) {
                return $sent;
            }
        }

        // A key that is already gone is fine: the goal is that it does not exist.
        WooKeys::delete_key( (int) ( $c['key_id'] ?? 0 ) );
        ConnectionStore::delete();
        return true;
    }

    public static function error_transient_key( ?int $user_id = null ): string {
        return 'hexasync_disconnect_error_' . ( $user_id ?? get_current_user_id() );
    }

    public static function handle_request(): void {
        check_admin_referer( 'hexasync_disconnect' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'hexasyncintegration' ), '', [ 'response' => 403 ] );
        }

        $force  = ! empty( $_POST['force'] );
        $result = self::run( $force );

        if ( is_wp_error( $result ) ) {
            set_transient( self::error_transient_key(), $result->get_error_message(), 300 );
            wp_safe_redirect( admin_url( 'admin.php?page=hexasync' ) );
            exit;
        }
        wp_safe_redirect( admin_url( 'admin.php?page=hexasync&hexasync_disconnected=1' ) );
        exit;
    }
}
