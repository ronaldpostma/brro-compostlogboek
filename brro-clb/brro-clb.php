<?php
/**
 * Plugin Name: Brro Compost Logboek
 * Plugin URI: https://github.com/ronaldpostma/brro-compostlogboek
 * Description: Custom style, script and functions for Compost Logboek
 * Version: 1.0.0
 * Author: Ronald Postma 
 * Author URI: https://brro.nl/
 * 
 * 
 * ===============================================
 * Index
 * - Plugin constants and file includes
 * - Asset enqueuing (brro_clb_enqueue_assets)
 * - Admin menu creation (brro_clb_menu_items)
 * - Template loading filters (log form, reports, user request form)
 * - Row action link additions (brro_clb_add_logboek_row_action)
 * ===============================================
 * 
 * to do's:
 * - remove from settings page the settings for log and reports page
 * - check each file for comments and structure
 * - add an index to each file
 * - add the background color from the settings to the reports page template as well
 * - add a function on log form submit to email the person if their email is filled in
 * - add a optional parameter to ?rapport with the email address, and generate a report with all log entries for that email address
 * - change in the report 'vanaf begin' to the actual date of the oldest log entry
 * - change in the report 'nu' to 'nu + (the date of today)'
 * 
 * - feedback from Rowin implementation
 * - finish plugin and add to github
 * - add update mechanism
 * - implement live on compostier.nl
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
require_once brro_clb_dir . '/php/brro-clb-database.php';
require_once brro_clb_dir . '/php/brro-clb-settings.php';
require_once brro_clb_dir . '/php/brro-clb-locations.php';
require_once brro_clb_dir . '/php/brro-clb-reports.php';
require_once brro_clb_dir . '/php/brro-clb-logging.php';

/**
 * Enqueue Styles & Scripts
 */
add_action('wp_enqueue_scripts', 'brro_clb_enqueue_assets');
function brro_clb_enqueue_assets() {
    // Only enqueue assets if URL has ?logboek or ?rapport parameter (rapport only on front page)
    $has_logboek = isset($_GET['logboek']);
    $has_rapport = (is_front_page() && isset($_GET['rapport']));	
    $has_logboek_opvragen = (is_front_page() && isset($_GET['logboek-opvragen']));
    //
    if (!$has_logboek && !$has_rapport && !$has_logboek_opvragen) {
        return;
    }
    //
    // Get settings for volume weights (needed for script localization)
    $options = get_option('brro_clb_settings', array());
    //
    // Utility classes (load first)
    $clb_style = 'css/brro-clb-style.css';
    wp_enqueue_style(
        'brro-clb',
        brro_clb_url . $clb_style,
        [],
        filemtime(brro_clb_dir . $clb_style)
    );
    //
    // JavaScript
    $clb_script = 'js/brro-clb-script.js';
    wp_enqueue_script(
        'brro-clb',
        brro_clb_url . $clb_script,
        ['jquery'],
        filemtime(brro_clb_dir . $clb_script),
        true
    );
    //
    // Get volume weights
    $input_volweight = isset($options['brro_clb_input_volweight']) ? floatval($options['brro_clb_input_volweight']) : 0;
    $output_volweight = isset($options['brro_clb_output_volweight']) ? floatval($options['brro_clb_output_volweight']) : 0;

    // Localize script for AJAX and settings
    wp_localize_script('brro-clb', 'brro_clb_ajax', [
        'ajax_url'     => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('brro_clb_nonce'),
        'units'        => array(
            'input_volweight'  => $input_volweight,
            'output_volweight' => $output_volweight
        )
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
    // Submenu item: Locaties, only if selected in options
    $settings = get_option('brro_clb_settings', []);
    $locations_choice = isset($settings['brro_clb_locations_choice']) ? $settings['brro_clb_locations_choice'] : 'clb-l';
    if ($locations_choice === 'clb-l') {
        add_submenu_page(
            'brro-clb-logs', // Parent slug
            'Compost Locaties', // Page title
            'Locaties', // Menu title
            'manage_options', // Capability
            'edit.php?post_type=brro_clb_locations',
        );
    }
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

/**
 * Load custom template for log form when ?logboek parameter is present
 * 
 * When a user visits a location post with ?logboek in the URL, this function
 * loads a custom template that displays only the log form.
 * 
 * @param string $template The current template path
 * @return string The template path to use
 */
function brro_clb_load_log_form_template($template) {
    // Only proceed if we're on a singular page with the logboek parameter
    if (!is_singular() || !isset($_GET['logboek'])) {
        return $template;
    }
    // Get the configured post type for locations from settings
    $settings = get_option('brro_clb_settings', []);
    $locations_choice = isset($settings['brro_clb_locations_choice']) ? $settings['brro_clb_locations_choice'] : 'clb-l';
    //
    if ($locations_choice === 'clb-l') {
        $configured_post_type = 'brro_clb_locations';
    } else {
        $configured_post_type = isset($settings['brro_clb_locations']) ? $settings['brro_clb_locations'] : '';
    }
    // Check if the current post is of the configured post type
    if (empty($configured_post_type) || get_post_type() !== $configured_post_type) {
        return $template;
    }
    // Path to custom template file
    $custom_template = brro_clb_dir . 'templates/brro-clb-log-form-template.php';
    // Check if template file exists and return it
    if (file_exists($custom_template)) {
        return $custom_template;
    }
    return $template;
}
add_filter('template_include', 'brro_clb_load_log_form_template', 99);

/**
 * Load custom template for reports when ?rapport parameter is present
 * 
 * When a user visits the front page (domain root) with ?rapport in the URL, this function
 * loads a custom template that displays the log reports.
 * 
 * @param string $template The current template path
 * @return string The template path to use
 */
function brro_clb_load_reports_template($template) {
    // Only proceed if we're on the front page and the rapport parameter is present
    if (!is_front_page() || !isset($_GET['rapport'])) {
        return $template;
    }
    // Path to custom template file
    $custom_template = brro_clb_dir . 'templates/brro-clb-log-reports-template.php';
    // Check if template file exists and return it
    if (file_exists($custom_template)) {
        return $custom_template;
    }
    return $template;
}
add_filter('template_include', 'brro_clb_load_reports_template', 99);

/**
 * Load custom template for user log request form when ?logboek-opvragen parameter is present
 * 
 * When a user visits the front page (domain root) with ?logboek-opvragen in the URL, this function
 * loads a custom template that displays the user log request form.
 * 
 * @param string $template The current template path
 * @return string The template path to use
 */
function brro_clb_load_log_user_requestform_template($template) {
    // Only proceed if we're on the front page and the logboek-opvragen parameter is present
    if (!is_front_page() || !isset($_GET['logboek-opvragen'])) {
        return $template;
    }
    // Path to custom template file
    $custom_template = brro_clb_dir . 'templates/brro-clb-log-user-requestform-template.php';
    // Check if template file exists and return it
    if (file_exists($custom_template)) {
        return $custom_template;
    }
    return $template;
}
add_filter('template_include', 'brro_clb_load_log_user_requestform_template', 99);

/**
 * Add "logboek" link to row actions for location post type
 * 
 * Adds a "logboek" link next to "View" in the post list table that links
 * to the frontend post URL with ?logboek parameter.
 * 
 * @param array $actions An array of action links
 * @param WP_Post $post The current post object
 * @return array Modified array of action links
 */
function brro_clb_add_logboek_row_action($actions, $post) {
    // Get the configured post type for locations from settings
    $settings = get_option('brro_clb_settings', []);
    $locations_choice = isset($settings['brro_clb_locations_choice']) ? $settings['brro_clb_locations_choice'] : 'clb-l';
    //
    if ($locations_choice === 'clb-l') {
        $configured_post_type = 'brro_clb_locations';
    } else {
        $configured_post_type = isset($settings['brro_clb_locations']) ? $settings['brro_clb_locations'] : '';
    }
    // Only add link if this is the configured location post type
    if (empty($configured_post_type) || $post->post_type !== $configured_post_type) {
        return $actions;
    }
    // Only add link if post is published
    if ($post->post_status !== 'publish') {
        return $actions;
    }
    // Get the post URL and add ?logboek parameter (without value)
    $permalink = get_permalink($post->ID);
    // Check if URL already has query parameters
    $separator = (strpos($permalink, '?') !== false) ? '&' : '?';
    $logboek_url = $permalink . $separator . 'logboek';
    // Add the logboek link after the view link
    $logboek_action = array(
        'logboek' => sprintf(
            '<a href="%s" target="_blank" aria-label="%s">%s</a>',
            esc_url($logboek_url),
            esc_attr(sprintf(__('Open logboek formulier voor %s', 'brro-clb'), $post->post_title)),
            esc_html__('Logboek formulier', 'brro-clb')
        )
    );
    // Insert after 'view' if it exists, otherwise just append
    if (isset($actions['view'])) {
        // Find the position of 'view' and insert after it
        $keys = array_keys($actions);
        $view_index = array_search('view', $keys);
        if ($view_index !== false) {
            $new_actions = array_slice($actions, 0, $view_index + 1, true);
            $new_actions = array_merge($new_actions, $logboek_action);
            $new_actions = array_merge($new_actions, array_slice($actions, $view_index + 1, null, true));
            return $new_actions;
        }
    }
    // If 'view' doesn't exist, just append
    return array_merge($actions, $logboek_action);
}
add_filter('post_row_actions', 'brro_clb_add_logboek_row_action', 10, 2);
add_filter('page_row_actions', 'brro_clb_add_logboek_row_action', 10, 2);