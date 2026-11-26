<?php
/**
 *
 * Created By Hidro Le
 * Date: 3/5/26
 *
 */

namespace Beehexa\UI;

class AdminMenu
{
    public static function hexasync_register_admin_menu(){
        // Shop managers (manage_woocommerce) use the Connect panel on this page; the Logs and
        // Settings submenus stay administrator-only.
        add_menu_page(
            'HexaSync',
            'HexaSync',
            class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options',
            'hexasync',
            [AdminConnectionGuidePage::class, 'hexasync_connection_guide_page'],
            'dashicons-list-view',
            100
        );

        add_submenu_page(
            'hexasync',
            __('HexaSync Logs', 'hexasyncintegration'),
            __('Logs', 'hexasyncintegration'),
            'manage_options',
            'hexasync-logs',
            [AdminSyncLogPage::class, 'hexasync_logs_page']
        );
    }
}
