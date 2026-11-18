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

        ?>
        <div class="wrap">
            <h1>Connect WooCommerce to HexaSync</h1>
            <p style="max-width: 900px;">
                Follow this setup guide to connect your WooCommerce store with HexaSync for product,
                order, and customer sync.
            </p>

            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; max-width: 980px; margin-top: 16px;">
                <h2 style="margin-top: 0;">Step 1: Prepare your store</h2>
                <ol style="margin-left: 20px;">
                    <li>Make sure WooCommerce is installed and active.</li>
                    <li>Confirm your store can access the internet and WordPress cron is running.</li>
                    <li>Ensure your site uses HTTPS for secure API communication.</li>
                </ol>
            </div>

            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; max-width: 980px; margin-top: 12px;">
                <h2 style="margin-top: 0;">Step 2: Create WooCommerce API keys</h2>
                <ol style="margin-left: 20px;">
                    <li>Open <a href="<?php echo esc_url($woo_api_keys_url); ?>">WooCommerce REST API keys</a>.</li>
                    <li>Click <strong>Add key</strong>.</li>
                    <li>Set permissions to <strong>Read/Write</strong>.</li>
                    <li>Copy the <strong>Consumer key</strong> and <strong>Consumer secret</strong>.</li>
                </ol>
                <p><em>Keep both keys secure. You will add them in HexaSync connection settings.</em></p>
            </div>

            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; max-width: 980px; margin-top: 12px;">
                <h2 style="margin-top: 0;">Step 3: Connect in HexaSync</h2>
                <ol style="margin-left: 20px;">
                    <li>Log in to your HexaSync workspace.</li>
                    <li>Create a new profile and choose WooCommerce as source or destination.</li>
                    <li>Enter your store URL, Consumer key, and Consumer secret.</li>
                    <li>Run the connection test and save when it succeeds.</li>
                </ol>
            </div>

            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; max-width: 980px; margin-top: 12px;">
                <h2 style="margin-top: 0;">Step 4: Optional webhook setup</h2>
                <ol style="margin-left: 20px;">
                    <li>Open <a href="<?php echo esc_url($woo_webhooks_url); ?>">WooCommerce Webhooks</a>.</li>
                    <li>Create webhooks for events you want to sync in near real time (for example: order created/updated).</li>
                    <li>Use the webhook URL provided by your HexaSync flow.</li>
                </ol>
            </div>

            <div style="background: #f6f7f7; border-left: 4px solid #2271b1; padding: 12px 14px; max-width: 980px; margin-top: 12px;">
                <h2 style="margin-top: 0;">Troubleshooting checklist</h2>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li>API key permissions are Read/Write.</li>
                    <li>Store URL in HexaSync exactly matches your WordPress site URL.</li>
                    <li>No firewall or security plugin blocks incoming/outgoing API requests.</li>
                    <li>Server time and timezone are correct.</li>
                </ul>
            </div>

            <p style="margin-top: 16px;">
                Need sync history and debug details? Go to <a href="<?php echo esc_url(admin_url('admin.php?page=hexasync-logs')); ?>">HexaSync Logs</a>.
            </p>
        </div>
        <?php
    }
}

