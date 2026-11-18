<?php
/**
 *
 * Created By Hidro Le
 * Date: 11/18/26
 *
 */

namespace Beehexa\UI;

class AdminConnectionGuidePage
{
    /**
     * Display the main HexaSync admin page with WooCommerce connection steps.
     */
    public static function hexasync_connection_guide_page()
    {
        $woo_api_keys_url = admin_url('admin.php?page=wc-settings&tab=advanced&section=keys');
        $woo_webhooks_url = admin_url('admin.php?page=wc-settings&tab=advanced&section=webhooks');

        $allowed = [
            'a'      => ['href' => []],
            'strong' => [],
        ];
        $card = 'background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; max-width: 980px;';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Connect WooCommerce to HexaSync', 'hexasyncintegration'); ?></h1>
            <p style="max-width: 900px;">
                <?php esc_html_e('Follow this setup guide to connect your WooCommerce store with HexaSync for product, order, and customer sync.', 'hexasyncintegration'); ?>
            </p>

            <div style="<?php echo esc_attr($card); ?> margin-top: 16px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Step 1: Prepare your store', 'hexasyncintegration'); ?></h2>
                <ol style="margin-left: 20px;">
                    <li><?php esc_html_e('Make sure WooCommerce is installed and active.', 'hexasyncintegration'); ?></li>
                    <li><?php esc_html_e('Confirm your store can access the internet and WordPress cron is running.', 'hexasyncintegration'); ?></li>
                    <li><?php esc_html_e('Ensure your site uses HTTPS for secure API communication.', 'hexasyncintegration'); ?></li>
                </ol>
            </div>

            <div style="<?php echo esc_attr($card); ?> margin-top: 12px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Step 2: Create WooCommerce API keys', 'hexasyncintegration'); ?></h2>
                <ol style="margin-left: 20px;">
                    <li><?php
                        /* translators: %s: URL of the WooCommerce REST API keys screen */
                        echo wp_kses(sprintf(__('Open <a href="%s">WooCommerce REST API keys</a>.', 'hexasyncintegration'), esc_url($woo_api_keys_url)), $allowed);
                    ?></li>
                    <li><?php echo wp_kses(__('Click <strong>Add key</strong>.', 'hexasyncintegration'), $allowed); ?></li>
                    <li><?php echo wp_kses(__('Set permissions to <strong>Read/Write</strong>.', 'hexasyncintegration'), $allowed); ?></li>
                    <li><?php echo wp_kses(__('Copy the <strong>Consumer key</strong> and <strong>Consumer secret</strong>.', 'hexasyncintegration'), $allowed); ?></li>
                </ol>
                <p><em><?php esc_html_e('Keep both keys secure. You will add them in HexaSync connection settings.', 'hexasyncintegration'); ?></em></p>
            </div>

            <div style="<?php echo esc_attr($card); ?> margin-top: 12px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Step 3: Connect in HexaSync', 'hexasyncintegration'); ?></h2>
                <ol style="margin-left: 20px;">
                    <li><?php esc_html_e('Log in to your HexaSync workspace.', 'hexasyncintegration'); ?></li>
                    <li><?php esc_html_e('Create a new profile and choose WooCommerce as source or destination.', 'hexasyncintegration'); ?></li>
                    <li><?php esc_html_e('Enter your store URL, Consumer key, and Consumer secret.', 'hexasyncintegration'); ?></li>
                    <li><?php esc_html_e('Run the connection test and save when it succeeds.', 'hexasyncintegration'); ?></li>
                </ol>
            </div>

            <div style="<?php echo esc_attr($card); ?> margin-top: 12px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Step 4: Optional webhook setup', 'hexasyncintegration'); ?></h2>
                <ol style="margin-left: 20px;">
                    <li><?php
                        /* translators: %s: URL of the WooCommerce webhooks screen */
                        echo wp_kses(sprintf(__('Open <a href="%s">WooCommerce Webhooks</a>.', 'hexasyncintegration'), esc_url($woo_webhooks_url)), $allowed);
                    ?></li>
                    <li><?php esc_html_e('Create webhooks for events you want to sync in near real time (for example: order created/updated).', 'hexasyncintegration'); ?></li>
                    <li><?php esc_html_e('Use the webhook URL provided by your HexaSync flow.', 'hexasyncintegration'); ?></li>
                </ol>
            </div>

            <div style="background: #f6f7f7; border-left: 4px solid #2271b1; padding: 12px 14px; max-width: 980px; margin-top: 12px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Troubleshooting checklist', 'hexasyncintegration'); ?></h2>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php esc_html_e('API key permissions are Read/Write.', 'hexasyncintegration'); ?></li>
                    <li><?php esc_html_e('Store URL in HexaSync exactly matches your WordPress site URL.', 'hexasyncintegration'); ?></li>
                    <li><?php esc_html_e('No firewall or security plugin blocks incoming/outgoing API requests.', 'hexasyncintegration'); ?></li>
                    <li><?php esc_html_e('Server time and timezone are correct.', 'hexasyncintegration'); ?></li>
                </ul>
            </div>

            <p style="margin-top: 16px;">
                <?php
                /* translators: %s: URL of the HexaSync Logs admin page */
                echo wp_kses(sprintf(__('Need sync history and debug details? Go to <a href="%s">HexaSync Logs</a>.', 'hexasyncintegration'), esc_url(admin_url('admin.php?page=hexasync-logs'))), $allowed);
                ?>
            </p>
        </div>
        <?php
    }
}

