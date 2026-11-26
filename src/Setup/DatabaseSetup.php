<?php
/**
 *
 * Created By Hidro Le
 * Date: 3/5/26
 *
 */

namespace Beehexa\Setup;

if ( ! defined( 'ABSPATH' ) ) exit;

class DatabaseSetup
{
    /**
     * Schema version. Bump it whenever install() changes, so existing sites
     * pick the change up on their next request after updating the plugin.
     */
    const DB_VERSION = '2';

    /**
     * Option holding the schema version installed on this site.
     */
    const DB_VERSION_OPTION = 'hexasync_db_version';

    /**
     * Activation hook.
     */
    public static function hexasync_activate_plugin() {
        self::install();
    }

    /**
     * Runs on every request (plugins_loaded). WordPress does not run the activation
     * hook when a plugin is updated, so this is what applies schema changes and
     * schedules new cron events on sites that update in place.
     */
    public static function maybe_upgrade() {
        if (get_option(self::DB_VERSION_OPTION) !== self::DB_VERSION) {
            self::install();
        }
    }

    /**
     * Create or migrate the log table, then let the rest of the plugin
     * (re)schedule its cron events through the hexasync_plugin_activated action.
     */
    private static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'hexasync_log';

        $sql = /** @lang MySQL */
            sprintf("CREATE TABLE IF NOT EXISTS %s (
        log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        message LONGTEXT COMMENT 'Log message',
        profile_id VARCHAR(255) COMMENT 'Profile UUID',
        profile_name VARCHAR(500) NOT NULL COMMENT 'Profile name',
        action_type VARCHAR(100) COMMENT 'Action type (e.g., Automatic)',
        log_detail_id VARCHAR(255) COMMENT 'Log detail UUID',
        reference_info VARCHAR(500) COMMENT 'Reference information',
        item_id VARCHAR(255) COMMENT 'Item ID',
        error LONGTEXT COMMENT 'Error message/details',
        task_id VARCHAR(255) COMMENT 'Task UUID',
        task_name VARCHAR(500) NOT NULL COMMENT 'Task name',
        task_status VARCHAR(100) COMMENT 'Task status (e.g., Failed, Completed)',
        executed_at varchar(255) COMMENT 'Execution timestamp (e.g., Feb 25, 2026 21:38:42)',
        execute_at varchar(255) COMMENT 'Scheduled execution timestamp',
        retry_count INT(11) DEFAULT 0 COMMENT 'Number of retries',
        push_note LONGTEXT COMMENT 'Push/notification note',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
        KEY profile_id_idx (profile_id),
        KEY profile_name_idx (profile_name(100)),
        KEY task_id_idx (task_id),
        KEY task_name_idx (task_name(100)),
        KEY task_status_idx (task_status),
        KEY created_at_idx (created_at),
        KEY updated_at_idx (updated_at)
    ) %s;", $table_name, $charset_collate);

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Columns added after the first release. CREATE TABLE IF NOT EXISTS leaves an
        // existing table alone, so each one needs its own check.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $column_exists = $wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM %i LIKE %s', $table_name, 'item_id'));

        if (empty($column_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query($wpdb->prepare("ALTER TABLE %i ADD COLUMN item_id VARCHAR(255) COMMENT 'Item ID' AFTER reference_info", $table_name));
        }

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);

        // Schedules the notification and log-retention cron events.
        do_action('hexasync_plugin_activated');
    }
}
