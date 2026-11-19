<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: removes the log table,
 * the plugin's options and its scheduled events.
 *
 * @package Beehexa
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'hexasync_log' ) );

foreach ( array( 'hexasync_notification_settings', 'hexasync_last_notification_check', 'hexasync_notified_item_ids', 'hexasync_db_version' ) as $hexasync_option ) {
	delete_option( $hexasync_option );
}
delete_transient( 'hexasync_new_logs_notice' );

wp_clear_scheduled_hook( 'hexasync_check_new_logs' );
wp_clear_scheduled_hook( 'hexasync_purge_old_logs' );
