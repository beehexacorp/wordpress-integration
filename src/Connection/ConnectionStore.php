<?php
/**
 * Stores which HexaSync workspace this site is connected to.
 *
 * @package Beehexa\Connection
 */

namespace Beehexa\Connection;

if ( ! defined( 'ABSPATH' ) ) exit;

class ConnectionStore {

    const OPTION = 'hexasync_connection';

    /**
     * @return array|null The stored connection, or null when not connected.
     */
    public static function get(): ?array {
        $c = get_option( self::OPTION, null );
        return ( is_array( $c ) && ! empty( $c ) ) ? $c : null;
    }

    /**
     * Save the connection (not autoloaded) and stamp connected_at.
     */
    public static function save( array $c ): void {
        $c['connected_at'] = time();
        update_option( self::OPTION, $c, false );
    }

    public static function delete(): void {
        delete_option( self::OPTION );
    }

    /**
     * The connection without its signing secret, safe to return over REST.
     */
    public static function public_view( array $c ): array {
        unset( $c['signing_secret'] );
        return $c;
    }
}
