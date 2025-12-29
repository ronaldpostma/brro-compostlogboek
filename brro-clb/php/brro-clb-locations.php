<?php
if (!defined('ABSPATH')) exit;

/**
 * Conditionally register the Compost Locations CPT depending on plugin settings.
 *
 * Logic:
 * - If 'brro_clb_locations_choice' === 'clb-l':
 *     -> Register CPT 'brro_clb_locations' in normal (active) mode.
 * - If 'brro_clb_locations_choice' !== 'clb-l':
 *     -> If there are NO posts of type 'brro_clb_locations':
 *          - Do NOT register the CPT at all.
 *       If there ARE posts of type 'brro_clb_locations':
 *          - Register CPT in "legacy/disabled" mode and
 *            redirect any possible front-end endpoints to the homepage
 *            and any back-end screens for this CPT to the dashboard.
 */
add_action('init', 'brro_clb_conditionally_register_locations_cpt');
function brro_clb_conditionally_register_locations_cpt() {
    // Get plugin settings
    $options = get_option('brro_clb_settings', array());
    $locations_choice = isset($options['brro_clb_locations_choice'])
        ? sanitize_text_field($options['brro_clb_locations_choice'])
        : 'clb-l';

    // Check if there are any existing posts of this CPT (even if CPT not registered)
    $has_posts = brro_clb_locations_has_posts();

    if ($locations_choice === 'clb-l') {
        // Plugin manages its own compost locations: fully active CPT
        brro_clb_register_locations_cpt(true);
        return;
    }

    // locations_choice is NOT 'clb-l'
    if (!$has_posts) {
        // No legacy posts: behave as if the CPT does not exist at all
        return;
    }

    // There ARE legacy posts: register CPT in "disabled" mode and add redirects
    brro_clb_register_locations_cpt(false);

    // Frontend: redirect any possible endpoints for this CPT to the homepage
    add_action('template_redirect', 'brro_clb_locations_frontend_legacy_redirect');

    // Backend: redirect any admin screens for this CPT to the dashboard
    add_action('admin_init', 'brro_clb_locations_admin_legacy_redirect');
}

/**
 * Check if there are any posts of the compost locations CPT.
 *
 * This works even if the CPT is not currently registered,
 * because posts are stored by post_type slug in the database.
 *
 * @return bool
 */
function brro_clb_locations_has_posts() {
    $counts = wp_count_posts('brro_clb_locations');
    if (!$counts) {
        return false;
    }

    // Check non-trash, non-auto-draft posts
    $non_empty_statuses = array('publish', 'pending', 'draft', 'future', 'private');
    foreach ($non_empty_statuses as $status) {
        if (!empty($counts->$status) && (int) $counts->$status > 0) {
            return true;
        }
    }
    return false;
}

/**
 * Register the Compost Locations CPT.
 *
 * @param bool $active_mode If true, CPT is usable in the admin.
 *                          If false, CPT is registered only to keep
 *                          legacy posts addressable internally, but
 *                          is effectively hidden from users.
 */
function brro_clb_register_locations_cpt($active_mode = true) {
    $labels = array(
        'name'                  => 'Locaties',
        'singular_name'         => 'Locatie',
        'add_new'               => 'Nieuwe locatie',
        'add_new_item'          => 'Nieuwe locatie toevoegen',
        'edit_item'             => 'Locatie bewerken',
        'new_item'              => 'Nieuwe locatie',
        'view_item'             => 'Bekijk locatie',
        'search_items'          => 'Zoek locaties',
        'not_found'             => 'Geen locaties gevonden',
        'not_found_in_trash'    => 'Geen locaties in de prullenbak',
        'all_items'             => 'Alle locaties',
        'menu_name'             => 'Locaties',
    );

    // Base args: no front-end visibility, no archives, no search
    $args = array(
        'labels'                => $labels,
        'public'                => false,
        'publicly_queryable'    => false,
        'exclude_from_search'   => true,
        'show_in_nav_menus'     => false,
        'has_archive'           => false,
        'rewrite'               => false,
        'query_var'             => false,
        'show_in_rest'          => false,
        // Basic supports: title, featured image, menu order (page attributes)
        'supports'              => array('title', 'thumbnail', 'page-attributes'),
        'hierarchical'          => false,
        'menu_position'         => 26,
        'menu_icon'             => 'dashicons-location-alt',
        'capability_type'       => 'post',
    );

    if ($active_mode) {
        // Normal usable mode in admin.
        // IMPORTANT: Do NOT create a menu item here.
        // The menu item for this CPT is added conditionally in brro-clb.php.
        $args['show_ui']      = true;
        $args['show_in_menu'] = false;
    } else {
        // Legacy/disabled mode: hide from admin menus and screens as much as possible
        $args['show_ui']      = false;
        $args['show_in_menu'] = false;
    }

    register_post_type('brro_clb_locations', $args);
}

/**
 * Redirect any possible front-end requests for this CPT to the homepage
 * when the CPT is in legacy/disabled mode.
 */
function brro_clb_locations_frontend_legacy_redirect() {
    if (is_admin()) {
        return;
    }

    if (is_post_type_archive('brro_clb_locations') || is_singular('brro_clb_locations')) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}

/**
 * Redirect any admin screens for this CPT to the dashboard
 * when the CPT is in legacy/disabled mode.
 */
function brro_clb_locations_admin_legacy_redirect() {
    if (!is_admin()) {
        return;
    }

    $screen = get_current_screen();
    if (empty($screen) || empty($screen->post_type)) {
        return;
    }

    if ($screen->post_type === 'brro_clb_locations') {
        wp_safe_redirect(admin_url());
        exit;
    }
}