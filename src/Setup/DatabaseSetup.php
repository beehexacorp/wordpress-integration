<?php
/**
 *
 * Created By Hidro Le
 * Date: 3/5/26
 *
 */

namespace Beehexa\Setup;

class DatabaseSetup
{

    public static function hexasync_activate_plugin() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'hexasync_log';

        $sql = /** @lang MySQL */
            sprintf("CREATE TABLE IF NOT EXISTS %s (
        log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        message LONGTEXT COMMENT 'Log message',
        profile_id VARCHAR(255) COMMENT 'Profile UUID',
        profile_name VARCHAR(500) NOT NULL COMMENT 'Profile name',        action_type VARCHAR(100) COMMENT 'Action type (e.g., Automatic)',
        log_detail_id VARCHAR(255) COMMENT 'Log detail UUID',
        reference_info VARCHAR(500) COMMENT 'Reference information',        error LONGTEXT COMMENT 'Error message/details',
        task_id VARCHAR(255) COMMENT 'Task UUID',
        task_name VARCHAR(500) NOT NULL COMMENT 'Task name',
        task_status VARCHAR(100) COMMENT 'Task status (e.g., Failed, Completed)',        executed_at varchar(255) COMMENT 'Execution timestamp (e.g., Feb 25, 2026 21:38:42)',
        execute_at varchar(255) COMMENT 'Scheduled execution timestamp',
        retry_count INT(11) DEFAULT 0 COMMENT 'Number of retries',        push_note LONGTEXT COMMENT 'Push/notification note',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',        KEY profile_id_idx (profile_id),
        KEY profile_name_idx (profile_name(100)),
        KEY task_id_idx (task_id),
        KEY task_name_idx (task_name(100)),
        KEY task_status_idx (task_status),        KEY created_at_idx (created_at),
        KEY updated_at_idx (updated_at)
    ) %s;", $table_name, $charset_collate);

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function hexasync_update_plugin(){
        return true;
    }
}
