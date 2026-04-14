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
                'display' => __('Every 12 Hours')
            ];
            return $schedules;
        });

        // Register settings
        add_action('admin_init', [$instance, 'register_settings']);

        // Schedule cron on activation
        add_action('hexasync_plugin_activated', [$instance, 'schedule_cron']);

        // Add cron action
        add_action(self::CRON_HOOK, [$instance, 'check_and_notify']);

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
            'HexaSync Notification Settings',
            [$this, 'settings_section_callback'],
            'hexasync_notifications'
        );

        add_settings_field(
            'enable_notifications',
            'Enable Email Notifications',
            [$this, 'enable_field_callback'],
            'hexasync_notifications',
            'hexasync_notifications_section'
        );

        add_settings_field(
            'notification_emails',
            'Notification Emails',
            [$this, 'emails_field_callback'],
            'hexasync_notifications',
            'hexasync_notifications_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $output = [];

        $output['enabled'] = isset($input['enabled']) ? 1 : 0;
        if(!empty($input['emails'])) {
            $emails = explode(",", $input['emails']);
            $output['emails'] = array_map('trim', array_filter($emails));
        }
        return $output;
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>Configure email notifications for new HexaSync logs.</p>';
    }

    /**
     * Enable field callback
     */
    public function enable_field_callback() {
        $options = get_option(self::OPTION_NAME, ['enabled' => 0, 'emails' => []]);
        $checked = isset($options['enabled']) && $options['enabled'] ? 'checked' : '';
        echo '<input type="checkbox" name="' . self::OPTION_NAME . '[enabled]" value="1" ' . $checked . ' />';
    }

    /**
     * Emails field callback
     */
    public function emails_field_callback() {
        $options = get_option(self::OPTION_NAME, ['enabled' => 0, 'emails' => []]);
        $emails = isset($options['emails']) ? implode(", ", $options['emails']) : '';
        echo '<textarea name="' . self::OPTION_NAME . '[emails]" rows="2" cols="50">' . esc_textarea($emails) . '</textarea>';
        echo '<p class="description">Enter emails separated by commas.</p>';
    }

    /**
     * Add settings submenu
     */
    public function add_settings_submenu() {
        add_submenu_page(
            'hexasync',
            'HexaSync Notifications',
            'Notifications',
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
            <h1>HexaSync Notification Settings</h1>
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
    }

    /**
     * Check for new logs and send notifications
     */
    public function check_and_notify() {
        $options = get_option(self::OPTION_NAME, ['enabled' => 0, 'emails' => []]);
        if (!$options['enabled'] || empty($options['emails'])) {
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
            $notified_item_ids = array_unique(array_merge($notified_item_ids, $new_item_ids));
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
        $domain = parse_url($site_url, PHP_URL_HOST);

        $from_email = 'noreply@' . $domain;
        $from_name = $site_name . ' Admin';

        $subject = 'New HexaSync Logs - ' . $site_name;

        $headers = [
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Content-Type: text/html; charset=UTF-8'
        ];

        $message = "<p>New Sync logs have been added to the HexaSync Log monitor.</p>";
        $message .= "<p>Total new logs: " . count($logs) . "</p>";
        $message .= "<p><a href='" . admin_url('admin.php?page=hexasync-logs') . "'>View logs</a></p>";
        $message .= "<p>Site: <a href='" . $site_url . "'>" . $site_url . "</a></p>";

        wp_mail($emails, $subject, $message, $headers);
    }

    /**
     * Show admin notice
     */
    public function show_admin_notice() {
        if ($count = get_transient('hexasync_new_logs_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(_n('You have %s new HexaSync log.', 'You have %s new HexaSync logs.', $count, 'hexasync'), number_format_i18n($count)) . '</p>';
            echo '</div>';
        }
    }
}

