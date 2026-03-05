<?php
/**
 * @package Beehexa
 */
/*
Plugin Name: HexaSync Automation Engine by Beehexa
Plugin URI: https://www.beehexa.com/
Description: Through years of experience working with global retailers, manufacturers, and distributors, Beehexa has developed the HexaSync Automation Engine — a unified platform that connects and automates processes across eCommerce, Accounting, ERP, POS, CRM, GenAI, and other enterprise systems.
Version: 1.1.0
Requires at least: 5.8
Requires PHP: 7.4
Author: HexaSync - Beehexa Team
Author URI: https://www.beehexa.com/about/
License: GPLv2 or later
Text Domain: hexasync
*/

// Autoload classes
use Beehexa\UI\AdminSyncLogPage;

require_once plugin_dir_path(__FILE__) . 'src/API/JsonBasicAuth.php';
require_once plugin_dir_path(__FILE__) . 'src/DTO/HexaSyncLogDTO.php';
require_once plugin_dir_path(__FILE__) . 'src/UI/AdminMenu.php';
require_once plugin_dir_path(__FILE__) . 'src/UI/AdminSyncLogPage.php';
require_once plugin_dir_path(__FILE__) . 'src/Repository/HexaSyncLogRepository.php';
require_once plugin_dir_path(__FILE__) . 'src/API/HexaSyncLogAPI.php';
require_once plugin_dir_path(__FILE__) . 'src/Setup/DatabaseSetup.php';
/**
 * Plugin activation hook - Create database table if it doesn't exist
 */
register_activation_hook(__FILE__, [ Beehexa\Setup\DatabaseSetup::class, 'hexasync_activate_plugin']);
/**
 * Plugin update hook - Update table schema on plugin update
 */
add_action('wp_after_update_plugin', [Beehexa\Setup\DatabaseSetup::class,'hexasync_update_plugin']);

/**
 * Register admin menu for HexaSync Logs
 */
add_action('admin_menu', [\Beehexa\UI\AdminMenu::class, 'hexasync_register_admin_menu']);

/**
 * Basic Authentication for REST API
 */
add_filter( 'determine_current_user', ['Beehexa\API\JsonBasicAuth','json_basic_auth_handler'], 20 );

/**
 * Handle authentication errors for REST API
 */
add_filter( 'rest_authentication_errors', ['Beehexa\API\JsonBasicAuth','json_basic_auth_error']);


/**
 * Register REST API routes for HexaSync Logs
 */
add_action('rest_api_init', [new Beehexa\API\HexaSyncLogAPI(),'register_routes']);
