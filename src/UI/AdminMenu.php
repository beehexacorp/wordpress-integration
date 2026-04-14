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
        add_menu_page(
            'HexaSync',
            'HexaSync',
            'manage_options',
            'hexasync',
            null,
            'dashicons-list-view',
            100
        );

        add_submenu_page(
            'hexasync',
            'HexaSync Logs',
            'Logs',
            'manage_options',
            'hexasync-logs',
            [AdminSyncLogPage::class, 'hexasync_logs_page']
        );
    }
}
