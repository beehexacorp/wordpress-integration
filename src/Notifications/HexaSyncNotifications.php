<?php
/**
 * HexaSyncNotifications - Handles email notifications for new logs
 *
 * @package Beehexa\Notifications
 */

namespace Beehexa\Notifications;

use Beehexa\Repository\HexaSyncLogRepository;

class HexaSyncNotifications {

    /**
     * Option name for settings
     */
    const OPTION_NAME = 'hexasync_notification_settings';

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'hexasync_check_new_logs';

    /**
     * Daily cron hook that deletes logs older than the retention setting
     */
    const PURGE_HOOK = 'hexasync_purge_old_logs';

    /**
     * How many notified item ids to remember (oldest are dropped first)
     */
    const MAX_NOTIFIED_IDS = 5000;

    /**
     * @var HexaSyncLogRepository
     */
    private $repository;

    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = new HexaSyncLogRepository();
    }

    /**
     * Initialize notifications
     */
    public static function init() {
        $instance = new self();

        // Add cron schedule
        add_filter('cron_schedules', function($schedules) {
            $schedules['twelve_hours'] = [
                'interval' => 12 * HOUR_IN_SECONDS,
                'display' => __('Every 12 Hours', 'hexasyncintegration')
            ];
            return $schedules;
        });

        // Register settings
        add_action('admin_init', [$instance, 'register_settings']);

        // Schedule cron on activation
        add_action('hexasync_plugin_activated', [$instance, 'schedule_cron']);

        // Add cron action
        add_action(self::CRON_HOOK, [$instance, 'check_and_notify']);
        add_action(self::PURGE_HOOK, [$instance, 'purge_old_logs']);

        // Add submenu
        add_action('admin_menu', [$instance, 'add_settings_submenu']);

        // Add admin notice
        add_action('admin_notices', [$instance, 'show_admin_notice']);
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'hexasync_notifications',
            self::OPTION_NAME,
            [$this, 'sanitize_settings']
        );

        add_settings_section(
            'hexasync_notifications_section',
            __('Email Notifications', 'hexasyncintegration'),
            [$this, 'settings_section_callback'],
            'hexasync_notifications'
        );

        add_settings_field(
            'enable_notifications',
            __('Enable Email Notifications', 'hexasyncintegration'),
            [$this, 'enable_field_callback'],
            'hexasync_notifications',
            'hexasync_notifications_section'
        );

        add_settings_field(
            'notification_emails',
            __('Notification Emails', 'hexasyncintegration'),
            [$this, 'emails_field_callback'],
            'hexasync_notifications',
            'hexasync_notifications_section'
        );

        add_settings_section(
            'hexasync_retention_section',
            __('Log Retention', 'hexasyncintegration'),
            [$this, 'retention_section_callback'],
            'hexasync_notifications'
        );

        add_settings_field(
            'retention_days',
            __('Delete logs older than', 'hexasyncintegration'),
            [$this, 'retention_field_callback'],
            'hexasync_notifications',
            'hexasync_retention_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $output = [];

        // $input is the form submission (emails as a comma-separated string) or, when the
        // option is saved from code with update_option(), the stored array itself.
        $output['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $output['emails'] = [];
        if(!empty($input['emails'])) {
            $emails = is_array($input['emails']) ? $input['emails'] : explode(",", (string) $input['emails']);
            $emails = array_map('sanitize_email', array_map('trim', $emails));
            $output['emails'] = array_values(array_filter($emails));
        }
        $output['retention_days'] = isset($input['retention_days']) ? max(0, (int) $input['retention_days']) : 0;
        return $output;
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . esc_html__('Configure email notifications for new HexaSync logs.', 'hexasyncintegration') . '</p>';
    }

    /**
     * Enable field callback
     */
    public function enable_field_callback() {
        $options = get_option(self::OPTION_NAME, ['enabled' => 0, 'emails' => []]);
        $enabled = !empty($options['enabled']);
        echo '<input type="checkbox" name="' . esc_attr(self::OPTION_NAME . '[enabled]') . '" value="1" ' . checked($enabled, true, false) . ' />';
    }

    /**
     * Emails field callback
     */
    public function emails_field_callback() {
        $options = get_option(self::OPTION_NAME, ['enabled' => 0, 'emails' => []]);
        $emails = isset($options['emails']) ? implode(", ", $options['emails']) : '';
        echo '<textarea name="' . esc_attr(self::OPTION_NAME . '[emails]') . '" rows="2" cols="50">' . esc_textarea($emails) . '</textarea>';
        echo '<p class="description">' . esc_html__('Enter emails separated by commas.', 'hexasyncintegration') . '</p>';
    }

    /**
     * Retention section callback
     */
    public function retention_section_callback() {
        echo '<p>' . esc_html__('Old logs are deleted once a day. Set 0 to keep every log.', 'hexasyncintegration') . '</p>';
    }

    /**
     * Retention field callback
     */
    public function retention_field_callback() {
        $options = get_option(self::OPTION_NAME, []);
        $days = isset($options['retention_days']) ? (int) $options['retention_days'] : 0;
        echo '<input type="number" min="0" step="1" class="small-text" name="' . esc_attr(self::OPTION_NAME . '[retention_days]') . '" value="' . esc_attr($days) . '" /> ';
        esc_html_e('days', 'hexasyncintegration');
    }

    /**
     * Add settings submenu
     */
    public function add_settings_submenu() {
        add_submenu_page(
            'hexasync',
            __('HexaSync Settings', 'hexasyncintegration'),
            __('Settings', 'hexasyncintegration'),
            'manage_options',
            'hexasync-notifications',
            [$this, 'settings_page']
        );
    }

    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('HexaSync Settings', 'hexasyncintegration'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('hexasync_notifications');
                do_settings_sections('hexasync_notifications');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Schedule cron event
     */
    public function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'twelve_hours', self::CRON_HOOK);
        }
        if (!wp_next_scheduled(self::PURGE_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK);
        }
    }

    /**
     * Delete logs older than the retention setting (0 keeps everything)
     */
    public function purge_old_logs() {
        $options = get_option(self::OPTION_NAME, []);
        $days = isset($options['retention_days']) ? (int) $options['retention_days'] : 0;
        if ($days > 0) {
            $this->repository->deleteOlderThan($days);
        }
    }

    /**
     * Check for new logs and send notifications
     */
    public function check_and_notify() {
        $options = get_option(self::OPTION_NAME, ['enabled' => 0, 'emails' => []]);
        if (empty($options['enabled']) || empty($options['emails'])) {
            return;
        }

        $last_check = get_option('hexasync_last_notification_check', 0);
        $notified_item_ids = get_option('hexasync_notified_item_ids', []);

        // Get logs created since last check
        $new_logs = $this->repository->getLogsSince($last_check);

        $new_item_ids = [];
        foreach ($new_logs as $log) {
            $item_id = $log->getItemId();
            if (!empty($item_id) && !in_array($item_id, $notified_item_ids)) {
                $new_item_ids[] = $item_id;
            }
        }

        if (!empty($new_item_ids)) {
            // Send email
            $this->send_notification_email($new_logs);

            // Update notified item_ids
            $notified_item_ids = array_values(array_unique(array_merge($notified_item_ids, $new_item_ids)));
            $notified_item_ids = array_slice($notified_item_ids, -self::MAX_NOTIFIED_IDS);
            update_option('hexasync_notified_item_ids', $notified_item_ids);

            // Add admin notice
            set_transient('hexasync_new_logs_notice', count($new_item_ids), 3600); // 1 hour
        }

        // Update last check
        update_option('hexasync_last_notification_check', time());
    }

    /**
     * Send notification email
     */
    private function send_notification_email($logs) {
        $options = get_option(self::OPTION_NAME);
        $emails = $options['emails'];

        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $domain = wp_parse_url($site_url, PHP_URL_HOST);

        $from_email = 'noreply@' . $domain;
        /* translators: %s: site name */
        $from_name = sprintf(__('%s Admin', 'hexasyncintegration'), $site_name);

        /* translators: %s: site name */
        $subject = sprintf(__('New HexaSync Logs - %s', 'hexasyncintegration'), $site_name);

        $headers = [
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Content-Type: text/html; charset=UTF-8'
        ];

        $message = '<p>' . esc_html__('New Sync logs have been added to the HexaSync Log monitor.', 'hexasyncintegration') . '</p>';
        /* translators: %s: number of new logs */
        $message .= '<p>' . esc_html(sprintf(__('Total new logs: %s', 'hexasyncintegration'), number_format_i18n(count($logs)))) . '</p>';
        $message .= "<p><a href='" . esc_url(admin_url('admin.php?page=hexasync-logs')) . "'>" . esc_html__('View logs', 'hexasyncintegration') . '</a></p>';
        $message .= '<p>' . esc_html__('Site:', 'hexasyncintegration') . " <a href='" . esc_url($site_url) . "'>" . esc_html($site_url) . '</a></p>';

        wp_mail($emails, $subject, $message, $headers);
    }

    /**
     * Show admin notice
     */
    public function show_admin_notice() {
        if ($count = get_transient('hexasync_new_logs_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            /* translators: %s: number of new HexaSync logs */
            $text = sprintf(_n('You have %s new HexaSync log.', 'You have %s new HexaSync logs.', $count, 'hexasyncintegration'), number_format_i18n($count));
            echo '<p>' . esc_html($text) . '</p>';
            echo '</div>';
        }
    }
}

