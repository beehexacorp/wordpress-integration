<?php
/**
 * The "Connect to HexaSync" panel at the top of the main admin page.
 *
 * @package Beehexa\UI
 */

namespace Beehexa\UI;

use Beehexa\Connection\ConnectionStore;
use Beehexa\Connection\Disconnect;
use Beehexa\Repository\HexaSyncLogRepository;

if ( ! defined( 'ABSPATH' ) ) exit;

class ConnectPanel {

    /**
     * Base URL of the HexaSync web app, without a trailing slash.
     */
    public static function app_url(): string {
        return untrailingslashit( apply_filters( 'hexasync_app_url', 'https://app.hexasync.com' ) );
    }

    /**
     * Where the "Connect to HexaSync" button sends the store owner.
     */
    public static function connect_url(): string {
        return self::app_url() . '/connect/woocommerce?' . http_build_query( [
            'store_url'      => home_url( '/' ),
            'admin_url'      => admin_url( 'admin.php?page=hexasync' ),
            'plugin_version' => BEEHEXA_VERSION,
            'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : '',
        ] );
    }

    /**
     * Print the panel for the current user and connection state.
     */
    public static function render(): void {
        $card = 'background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; max-width: 980px; margin-top: 16px;';

        if ( ! class_exists( 'WooCommerce' ) ) {
            ?>
            <div class="notice notice-warning inline" style="max-width: 980px;">
                <p><?php esc_html_e( 'Connecting with one click needs WooCommerce. Install and activate WooCommerce, or follow the manual steps below.', 'hexasyncintegration' ); ?></p>
            </div>
            <?php
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $connection = ConnectionStore::get();

        // The query flag only picks the notice; the stored connection decides whether it shows.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $connection && isset( $_GET['hexasync_connected'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['hexasync_connected'] ) ) ) {
            ?>
            <div class="notice notice-success inline" style="max-width: 980px;">
                <p><?php esc_html_e( 'Your store is connected to HexaSync.', 'hexasyncintegration' ); ?></p>
            </div>
            <?php
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $connection && isset( $_GET['hexasync_disconnected'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['hexasync_disconnected'] ) ) ) {
            ?>
            <div class="notice notice-success inline" style="max-width: 980px;">
                <p><?php esc_html_e( 'Disconnected from HexaSync.', 'hexasyncintegration' ); ?></p>
            </div>
            <?php
        }

        echo '<div class="hexasync-connect-panel" style="' . esc_attr( $card ) . '">';
        if ( $connection ) {
            self::render_connected( $connection );
        } else {
            self::render_not_connected();
        }
        echo '</div>';
    }

    private static function render_not_connected(): void {
        ?>
        <h2 style="margin-top: 0;"><?php esc_html_e( 'HexaSync connection', 'hexasyncintegration' ); ?></h2>
        <p>
            <a class="button button-primary" href="<?php echo esc_attr( esc_url_raw( self::connect_url() ) ); ?>"><?php esc_html_e( 'Connect to HexaSync', 'hexasyncintegration' ); ?></a>
        </p>
        <p><?php esc_html_e( 'You will sign in to HexaSync, choose a workspace, then approve a Read/Write WooCommerce API key for HexaSync on this site.', 'hexasyncintegration' ); ?></p>
        <?php
    }

    private static function render_connected( array $c ): void {
        $c = ConnectionStore::public_view( $c );

        $latest = ( new HexaSyncLogRepository() )->getLatestCreatedAt();
        $last   = $latest
            ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $latest )
            : __( 'none yet', 'hexasyncintegration' );
        ?>
        <h2 style="margin-top: 0;">
            <?php
            /* translators: 1: HexaSync workspace name, 2: API credential name */
            echo esc_html( sprintf( __( 'Connected to %1$s (%2$s)', 'hexasyncintegration' ), (string) ( $c['workspace_name'] ?? '' ), (string) ( $c['credential_name'] ?? '' ) ) );
            ?>
        </h2>
        <p>
            <?php
            /* translators: %s: date the store was connected */
            echo esc_html( sprintf( __( 'Connected on %s', 'hexasyncintegration' ), wp_date( (string) get_option( 'date_format' ), (int) ( $c['connected_at'] ?? 0 ) ) ) );
            ?>
            <br>
            <?php
            /* translators: %s: date and time of the newest log, or "none yet" */
            echo esc_html( sprintf( __( 'Last log received: %s', 'hexasyncintegration' ), $last ) );
            ?>
        </p>
        <?php if ( ! empty( $c['dashboard_url'] ) ) : ?>
        <p>
            <a class="button" href="<?php echo esc_url( $c['dashboard_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in HexaSync', 'hexasyncintegration' ); ?></a>
        </p>
        <?php endif; ?>
        <?php

        $error_key = Disconnect::error_transient_key();
        $error     = get_transient( $error_key );
        if ( false !== $error ) {
            delete_transient( $error_key );
            ?>
            <div class="notice notice-error inline" style="margin: 0 0 12px;">
                <p>
                    <?php
                    /* translators: %s: the reason HexaSync could not be told, such as an HTTP status or a connection error */
                    echo esc_html( sprintf( __( 'HexaSync could not be told about the disconnect: %s', 'hexasyncintegration' ), (string) $error ) );
                    ?>
                </p>
            </div>
            <div style="display: flex; gap: 8px;">
                <?php
                self::disconnect_form( __( 'Try again', 'hexasyncintegration' ), false, false );
                self::disconnect_form( __( 'Disconnect anyway', 'hexasyncintegration' ), true, false );
                ?>
            </div>
            <?php
            return;
        }

        self::disconnect_form( __( 'Disconnect', 'hexasyncintegration' ), false, true );
    }

    /**
     * One Disconnect form: POST to admin-post.php with a nonce.
     */
    private static function disconnect_form( string $label, bool $force, bool $confirm ): void {
        $onclick = $confirm
            ? 'return confirm(' . wp_json_encode( __( 'Disconnect this store from HexaSync? HexaSync will be told first; then this site\'s HexaSync API key is deleted.', 'hexasyncintegration' ) ) . ');'
            : '';
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
            <input type="hidden" name="action" value="hexasync_disconnect">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'hexasync_disconnect' ) ); ?>">
            <?php if ( $force ) : ?>
            <input type="hidden" name="force" value="1">
            <?php endif; ?>
            <button type="submit" class="button"<?php echo $onclick ? ' onclick="' . esc_attr( $onclick ) . '"' : ''; ?>><?php echo esc_html( $label ); ?></button>
        </form>
        <?php
    }
}
