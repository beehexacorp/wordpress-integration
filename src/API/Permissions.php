<?php
/**
 * Who may use the plugin's REST API.
 *
 * @package Beehexa\API
 */

namespace Beehexa\API;

if ( ! defined( 'ABSPATH' ) ) exit;

class Permissions {

    /**
     * Administrators and shop managers (also what a WooCommerce key resolves to).
     */
    public static function can_use_api(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
    }
}
