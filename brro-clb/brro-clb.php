<?php
/**
 * Plugin Name: Brro Compost Logboek
 * Plugin URI: https://github.com/ronaldpostma/brro-compostlogboek
 * Description: Custom style, script and functions for Compost Logboek
 * Version: 1.0.0
 * Author: Ronald Postma 
 * Author URI: https://brro.nl/
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'brro_clb_version', '1.0.0' );
define( 'brro_clb_dir', plugin_dir_path( __FILE__ ) );
define( 'brro_clb_url', plugin_dir_url( __FILE__ ) );

// Include required files
require_once brro_clb_dir . '/php/brro-clb-settings.php';
require_once brro_clb_dir . '/php/brro-clb-locations.php';
require_once brro_clb_dir . '/php/brro-clb-reports.php';
require_once brro_clb_dir . '/php/brro-clb-logging.php';

/**
 * Enqueue Styles & Scripts
 */
add_action('wp_enqueue_scripts', 'brro_clb_enqueue_assets');
function brro_clb_enqueue_assets() {
    // Utility classes (load first)
    $clb_style = '/css/brro-clb-style.css';
    wp_enqueue_style(
        'brro-clb',
        get_template_directory_uri() . $clb_style,
        [],
        filemtime(get_template_directory() . $clb_style)
    );
    
    // JavaScript
    $clb_script = '/js/brro-clb-script.js';
    wp_enqueue_script(
        'brro-clb',
        get_template_directory_uri() . $clb_script,
        ['jquery'],
        filemtime(get_template_directory() . $clb_script),
        true
    );
    // Localize script for AJAX
    wp_localize_script('brro-clb', 'brro_clb_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('brro_clb_nonce')
    ]);
}


// Create menu items
add_action('admin_menu', 'brro_clb_menu_items');
function brro_clb_menu_items() {
    // Main menu item: Compost Logboek	
    add_menu_page(
        'Compost Logboek', // Page title
        'Compost Logboek', // Menu title
        'manage_options', // Capability
        'brro-clb-logs', // Menu slug
        'brro_clb_logs_page', // Function included in /php/brro-clb-logging.php
        'dashicons-book', // Icon
    );
    // Submenu item: Logboek
    add_submenu_page(
        'brro-clb-logs', // Parent slug
        'Compost Logboek', // Page title (same as parent)
        'Logboek', // Menu title
        'manage_options', // Capability
        'brro-clb-logs', // Menu slug (same as parent)
        'brro_clb_logs_page', // Function included in /php/brro-clb-logging.php
    );
    // Submenu item: Locaties
    add_submenu_page(
        'brro-clb-logs', // Parent slug
        'Compost Locaties', // Page title
        'Locaties', // Menu title
        'manage_options', // Capability
        'brro-clb-locations', // Menu slug
        'brro_clb_locations_page', // Function included in /php/brro-clb-locations.php
    );
    // Submenu item: Reports
    add_submenu_page(
        'brro-clb-logs', // Parent slug
        'Compost Logboek Rapportage', // Page title
        'Rapportage', // Menu title
        'manage_options', // Capability
        'brro-clb-reports', // Menu slug
        'brro_clb_reports_page', // Function included in /php/brro-clb-reports.php
    );
    // Submenu item: Settings
    add_submenu_page(
        'brro-clb-logs', // Parent slug
        'Compost Logboek Instellingen', // Page title
        'Instellingen', // Menu title
        'manage_options', // Capability
        'brro-clb-settings', // Menu slug
        'brro_clb_settings_page', // Function included in /php/brro-clb-settings.php
    );
}