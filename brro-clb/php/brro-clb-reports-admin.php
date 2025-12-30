<?php
/**
 * Reports admin functions - form creation, submission handling, and admin page display
 * ===============================================
 * This file contains the functions for the admin interface to create and manage reports.
 * Includes the form for generating new reports, handling form submissions, and displaying
 * the admin reports page with saved reports.
 * ===============================================
 * Index
 * - Helper functions (get_post_type_for_locations, get_post_type_taxonomy)
 * - Admin report form (brro_clb_reports_form)
 * - Report submission handling (brro_clb_handle_report_submission)
 * - Admin reports page (brro_clb_reports_page)
 */
if (!defined('ABSPATH')) exit;

/**
 * Get the post type used for locations based on settings
 * 
 * @return string Post type name
 */
function brro_clb_get_post_type_for_locations() {
    $options = get_option('brro_clb_settings', array());
    $locations_choice = isset($options['brro_clb_locations_choice']) ? sanitize_text_field($options['brro_clb_locations_choice']) : 'clb-l';
    
    if ($locations_choice === 'own') {
        $post_type = isset($options['brro_clb_locations']) ? sanitize_text_field($options['brro_clb_locations']) : 'post';
        return $post_type;
    } else {
        return 'brro_clb_locations';
    }
}

/**
 * Get the first taxonomy name for the selected post type from settings
 * Returns the first taxonomy found (hierarchical or non-hierarchical)
 * 
 * @return string|false Taxonomy name if found, false otherwise
 */
function brro_clb_get_post_type_taxonomy() {
    $post_type = brro_clb_get_post_type_for_locations();
    
    // Get all taxonomies for this post type
    $taxonomies = get_object_taxonomies($post_type, 'objects');
    
    if (empty($taxonomies)) {
        return false;
    }
    
    // Return the first taxonomy found
    $first_taxonomy = reset($taxonomies);
    return $first_taxonomy->name;
}

/**
 * Form to generate and save a new report
 * Displays above the reports table on the admin page
 */
function brro_clb_reports_form() {
    global $wpdb;
    
    // Get unique location IDs and names from logs table
    $logs_table_name = brro_clb_get_logs_table_name();
    $locations = $wpdb->get_results(
        "SELECT DISTINCT clb_log_location_id, clb_log_location_name 
         FROM $logs_table_name 
         ORDER BY clb_log_location_name ASC",
        ARRAY_A
    );
    
    // Check if post type has any taxonomies
    $post_type = brro_clb_get_post_type_for_locations();
    $all_taxonomies = get_object_taxonomies($post_type, 'objects');
    $has_taxonomy = !empty($all_taxonomies);
    
    // Get terms from ALL taxonomies for posts in logs
    $taxonomy_terms = array();
    if ($has_taxonomy && !empty($locations)) {
        $location_ids = array_unique(array_column($locations, 'clb_log_location_id'));
        
        // Collect terms from all taxonomies
        foreach ($all_taxonomies as $taxonomy_name => $taxonomy_obj) {
            $unique_terms = array();
            
            foreach ($location_ids as $location_id) {
                $terms = wp_get_post_terms($location_id, $taxonomy_name, array('fields' => 'all'));
                if (!is_wp_error($terms) && !empty($terms)) {
                    foreach ($terms as $term) {
                        // Use term_id as key to ensure uniqueness
                        $unique_terms[$term->term_id] = array(
                            'taxonomy' => $taxonomy_name,
                            'term' => $term
                        );
                    }
                }
            }
            
            // Add to main array
            $taxonomy_terms = array_merge($taxonomy_terms, array_values($unique_terms));
        }
        
        // Sort terms: first by taxonomy name, then by term name
        usort($taxonomy_terms, function($a, $b) {
            // First compare taxonomy names
            $tax_cmp = strcmp($a['taxonomy'], $b['taxonomy']);
            if ($tax_cmp !== 0) {
                return $tax_cmp;
            }
            // If same taxonomy, compare term names
            return strcmp($a['term']->name, $b['term']->name);
        });
    }
    
    // Display success/error messages
    $success_message = get_transient('brro_clb_report_success');
    $error_message = get_transient('brro_clb_report_error');
    
    ?>
    <div class="brro-clb-report-form-wrapper" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
        <h2>Nieuw rapport aanmaken</h2>
        
        <?php if ($success_message): ?>
            <div class="notice notice-success is-dismissible" style="margin: 10px 0;">
                <p><?php echo esc_html($success_message); ?></p>
            </div>
            <?php delete_transient('brro_clb_report_success'); ?>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="notice notice-error is-dismissible" style="margin: 10px 0;">
                <p><?php echo esc_html($error_message); ?></p>
            </div>
            <?php delete_transient('brro_clb_report_error'); ?>
        <?php endif; ?>
        
        <form method="post" action="" id="brro-clb-report-form">
            <?php wp_nonce_field('brro_clb_create_report', 'brro_clb_report_nonce'); ?>
            
            <!-- Locations choice -->
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label>Voor welke locaties wil je de rapportage inzien?</label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" 
                                       name="brro_clb_rep_locations_choice" 
                                       value="all-posts" 
                                       checked="checked" 
                                       id="rep_locations_all" />
                                Voor alle locaties
                            </label>
                            <br>
                            <label>
                                <input type="radio" 
                                       name="brro_clb_rep_locations_choice" 
                                       value="spec-posts" 
                                       id="rep_locations_spec" />
                                Voor één of meerdere specifieke locaties
                            </label>
                            <?php if ($has_taxonomy): ?>
                            <br>
                            <label>
                                <input type="radio" 
                                       name="brro_clb_rep_locations_choice" 
                                       value="rep_locations_tax" 
                                       id="rep_locations_tax" />
                                Voor alle locaties binnen een specifieke categorie(ën)
                            </label>
                            <?php endif; ?>
                        </fieldset>
                    </td>
                </tr>
                
                <!-- Specific locations select (hidden by default) -->
                <tr id="rep_locations_select_row" style="display: none;">
                    <th scope="row">
                        <label for="brro_clb_rep_locations">Kies locaties</label>
                    </th>
                    <td>
                        <?php if (!empty($locations)): ?>
                            <select name="brro_clb_rep_locations[]" 
                                    id="brro_clb_rep_locations" 
                                    multiple 
                                    size="10" 
                                    style="min-width: 350px;">
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo esc_attr($location['clb_log_location_id']); ?>">
                                        <?php echo esc_html($location['clb_log_location_name'] . ' (ID: ' . $location['clb_log_location_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Houd Ctrl (of Cmd op Mac) ingedrukt om meerdere locaties te selecteren.</p>
                        <?php else: ?>
                            <p class="description">Er zijn nog geen locaties beschikbaar in het logboek.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <!-- Taxonomy terms select (hidden by default) -->
                <?php if ($has_taxonomy): ?>
                <tr id="rep_locations_tax_row" style="display: none;">
                    <th scope="row">
                        <label for="brro_clb_rep_taxonomy_terms">Kies categorieën</label>
                    </th>
                    <td>
                        <?php if (!empty($taxonomy_terms)): ?>
                            <select name="brro_clb_rep_taxonomy_terms[]" 
                                    id="brro_clb_rep_taxonomy_terms" 
                                    multiple 
                                    size="10" 
                                    style="min-width: 350px;">
                                <?php foreach ($taxonomy_terms as $term_data): 
                                    $taxonomy_name_display = $term_data['taxonomy'];
                                    $term_obj = $term_data['term'];
                                    $taxonomy_label = isset($all_taxonomies[$taxonomy_name_display]->labels->singular_name) 
                                        ? $all_taxonomies[$taxonomy_name_display]->labels->singular_name 
                                        : $taxonomy_name_display;
                                    $option_value = esc_attr($taxonomy_name_display . '|' . $term_obj->term_id);
                                    ?>
                                    <option value="<?php echo $option_value; ?>">
                                        <?php echo esc_html($taxonomy_label . ': ' . $term_obj->name . ' (ID: ' . $term_obj->term_id . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Houd Ctrl (of Cmd op Mac) ingedrukt om meerdere categorieën te selecteren.</p>
                        <?php else: ?>
                            <p class="description">Er zijn nog geen categorieën beschikbaar voor locaties in het logboek.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                
                <!-- Period choice -->
                <tr>
                    <th scope="row">
                        <label>Voor welke periode wil je de rapportage inzien?</label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" 
                                       name="brro_clb_rep_period_choice" 
                                       value="all-dates" 
                                       checked="checked" 
                                       id="rep_period_all" />
                                Vanaf het begin: alle metingen in het logboek
                            </label>
                            <br>
                            <label>
                                <input type="radio" 
                                       name="brro_clb_rep_period_choice" 
                                       value="spec-dates" 
                                       id="rep_period_spec" />
                                Voor een specifieke periode
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <!-- Date range fields (hidden by default) -->
                <tr id="rep_period_dates_row" style="display: none;">
                    <th scope="row">
                        <label>Periode</label>
                    </th>
                    <td>
                        <label for="brro_clb_rep_period_from">Van:</label>
                        <input type="date" 
                               name="brro_clb_rep_period_from" 
                               id="brro_clb_rep_period_from" 
                               value="" 
                               style="margin-right: 20px;" />
                        
                        <label for="brro_clb_rep_period_to">Tot en met:</label>
                        <input type="date" 
                               name="brro_clb_rep_period_to" 
                               id="brro_clb_rep_period_to" 
                               value="" />
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" 
                       name="brro_clb_create_report" 
                       id="brro_clb_create_report" 
                       class="button button-primary" 
                       value="Rapport aanmaken" />
            </p>
        </form>
    </div>
    
    <script type="text/javascript">
    jQuery(function($) {
        // Toggle locations select based on radio choice
        $('input[name="brro_clb_rep_locations_choice"]').change(function() {
            var selectedValue = $(this).val();
            if (selectedValue === 'spec-posts') {
                $('#rep_locations_select_row').show();
                $('#rep_locations_tax_row').hide();
                $('#brro_clb_rep_taxonomy_terms').val(null); // Clear taxonomy selection
            } else if (selectedValue === 'rep_locations_tax') {
                $('#rep_locations_select_row').hide();
                $('#rep_locations_tax_row').show();
                $('#brro_clb_rep_locations').val(null); // Clear location selection
            } else {
                $('#rep_locations_select_row').hide();
                $('#rep_locations_tax_row').hide();
                $('#brro_clb_rep_locations').val(null); // Clear selection
                $('#brro_clb_rep_taxonomy_terms').val(null); // Clear taxonomy selection
            }
        });
        
        // Toggle date fields based on period choice
        $('input[name="brro_clb_rep_period_choice"]').change(function() {
            if ($(this).val() === 'spec-dates') {
                $('#rep_period_dates_row').show();
            } else {
                $('#rep_period_dates_row').hide();
                $('#brro_clb_rep_period_from, #brro_clb_rep_period_to').val(''); // Clear dates
            }
        });
    });
    </script>
    <?php
}

/**
 * Handle report form submission and save to database
 * Hooked into 'init' to catch POST submissions
 */
function brro_clb_handle_report_submission() {
    // Only process if form was submitted
    if (!isset($_POST['brro_clb_create_report'])) {
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['brro_clb_report_nonce']) || !wp_verify_nonce($_POST['brro_clb_report_nonce'], 'brro_clb_create_report')) {
        set_transient('brro_clb_report_error', 'Beveiligingscontrole mislukt. Probeer het opnieuw.', 30);
        return;
    }
    
    // Ensure database tables are up to date
    brro_clb_ensure_all_tables_exist();
    
    global $wpdb;
    $table_name = brro_clb_get_reports_table_name();
    
    // Get form values
    $locations_choice = isset($_POST['brro_clb_rep_locations_choice']) ? sanitize_text_field($_POST['brro_clb_rep_locations_choice']) : '';
    $period_choice = isset($_POST['brro_clb_rep_period_choice']) ? sanitize_text_field($_POST['brro_clb_rep_period_choice']) : '';
    
    // Validate required fields
    if (empty($locations_choice) || empty($period_choice)) {
        set_transient('brro_clb_report_error', 'Alle velden zijn verplicht.', 30);
        return;
    }
    
    // Process locations
    $taxonomy_json = null;
    if ($locations_choice === 'all-posts') {
        // Save as 'all' (JSON-encoded string)
        $locations_json = json_encode('all');
    } elseif ($locations_choice === 'spec-posts') {
        // Get selected location IDs
        $selected_locations = isset($_POST['brro_clb_rep_locations']) ? $_POST['brro_clb_rep_locations'] : array();
        
        // Validate: must have at least 1 location
        if (empty($selected_locations)) {
            set_transient('brro_clb_report_error', 'Selecteer minimaal één locatie.', 30);
            return;
        }
        
        // Sanitize location IDs and save as JSON array
        $location_ids = array_map('absint', $selected_locations);
        $locations_json = json_encode($location_ids);
    } elseif ($locations_choice === 'rep_locations_tax') {
        // Get selected term values (format: "taxonomy|term_id")
        $selected_terms = isset($_POST['brro_clb_rep_taxonomy_terms']) ? $_POST['brro_clb_rep_taxonomy_terms'] : array();
        
        // Validate: must have at least 1 term
        if (empty($selected_terms)) {
            set_transient('brro_clb_report_error', 'Selecteer minimaal één categorie.', 30);
            return;
        }
        
        // Parse taxonomy|term_id values and build terms array
        $terms_array = array();
        foreach ($selected_terms as $term_value) {
            $parts = explode('|', $term_value, 2);
            if (count($parts) === 2) {
                $taxonomy_name = sanitize_text_field($parts[0]);
                $term_id = absint($parts[1]);
                
                // Validate taxonomy exists
                if (!taxonomy_exists($taxonomy_name)) {
                    continue; // Skip invalid taxonomy
                }
                
                // Validate term exists
                $term = get_term($term_id, $taxonomy_name);
                if ($term && !is_wp_error($term)) {
                    $terms_array[] = array(
                        'taxonomy' => $taxonomy_name,
                        'term_id' => $term_id
                    );
                }
            }
        }
        
        if (empty($terms_array)) {
            set_transient('brro_clb_report_error', 'Selecteer minimaal één geldige categorie.', 30);
            return;
        }
        
        // Build new data structure with terms array
        $taxonomy_data = array(
            'terms' => $terms_array
        );
        $taxonomy_json = json_encode($taxonomy_data);
        
        // For taxonomy option, locations should be 'all' since we're filtering by taxonomy
        $locations_json = json_encode('all');
    } else {
        set_transient('brro_clb_report_error', 'Ongeldige locatie keuze.', 30);
        return;
    }
    
    // Process dates
    if ($period_choice === 'all-dates') {
        $date_from = '1900-01-01';
        $date_to = '3025-01-01';
    } elseif ($period_choice === 'spec-dates') {
        $date_from = isset($_POST['brro_clb_rep_period_from']) ? sanitize_text_field($_POST['brro_clb_rep_period_from']) : '';
        $date_to = isset($_POST['brro_clb_rep_period_to']) ? sanitize_text_field($_POST['brro_clb_rep_period_to']) : '';
        
        // Validate dates
        if (empty($date_from) || empty($date_to)) {
            set_transient('brro_clb_report_error', 'Beide datums zijn verplicht voor een specifieke periode.', 30);
            return;
        }
        
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            set_transient('brro_clb_report_error', 'Ongeldig datumformaat. Gebruik YYYY-MM-DD.', 30);
            return;
        }
        
        // Validate that from date is before to date
        if (strtotime($date_from) > strtotime($date_to)) {
            set_transient('brro_clb_report_error', 'De startdatum moet voor de einddatum liggen.', 30);
            return;
        }
    } else {
        set_transient('brro_clb_report_error', 'Ongeldige periode keuze.', 30);
        return;
    }
    
    // Ensure column exists if we have taxonomy data
    if ($taxonomy_json !== null && !brro_clb_reports_column_exists('clb_report_taxonomy_terms')) {
        // Column doesn't exist, try to add it
        global $wpdb;
        $table_name_check = brro_clb_get_reports_table_name();
        $alter_result = $wpdb->query("ALTER TABLE $table_name_check ADD COLUMN clb_report_taxonomy_terms text DEFAULT NULL");
        if ($alter_result === false) {
            error_log('Brro CLB: Failed to add clb_report_taxonomy_terms column - ' . $wpdb->last_error);
        }
    }
    
    // Prepare data for database insertion
    $data = array(
        'clb_report_date_made' => current_time('Y-m-d'),
        'clb_report_date_from' => $date_from,
        'clb_report_date_to' => $date_to,
        'clb_report_locations' => $locations_json
    );
    
    // Build format array for prepared statement
    $format = array(
        '%s', // date_made
        '%s', // date_from
        '%s', // date_to
        '%s'  // locations (JSON string)
    );
    
    // Add taxonomy terms if provided (column should exist now)
    if ($taxonomy_json !== null) {
        $data['clb_report_taxonomy_terms'] = $taxonomy_json;
        $format[] = '%s'; // taxonomy_terms (JSON string)
    }
    
    // Insert into database
    $result = $wpdb->insert($table_name, $data, $format);
    
    if ($result === false) {
        // Database error - log detailed error
        $error_message = 'Er is een fout opgetreden bij het opslaan. Probeer het opnieuw.';
        $last_error = $wpdb->last_error;
        $last_query = $wpdb->last_query;
        
        error_log('Brro CLB: Report database insert failed');
        if (!empty($last_error)) {
            error_log('Brro CLB: Database error - ' . $last_error);
        }
        if (!empty($last_query)) {
            error_log('Brro CLB: Last query - ' . $last_query);
        }
        error_log('Brro CLB: Data array - ' . print_r($data, true));
        error_log('Brro CLB: Format array - ' . print_r($format, true));
        error_log('Brro CLB: Column exists check - ' . (brro_clb_reports_column_exists('clb_report_taxonomy_terms') ? 'yes' : 'no'));
        
        // For debugging, show the error to admin users
        if (current_user_can('manage_options') && !empty($last_error)) {
            $error_message .= ' (' . esc_html($last_error) . ')';
        }
        
        set_transient('brro_clb_report_error', $error_message, 30);
    } else {
        // Success - store success message
        $report_id = $wpdb->insert_id;
        set_transient('brro_clb_report_success', sprintf('Rapport succesvol aangemaakt! Rapport ID: %d', $report_id), 30);
        
        // Redirect to prevent duplicate submissions on page refresh
        $redirect_url = remove_query_arg('submitted');
        $redirect_url = add_query_arg('submitted', 'success', $redirect_url);
        wp_safe_redirect($redirect_url);
        exit;
    }
}

// Hook into init to catch form submissions
add_action('init', 'brro_clb_handle_report_submission');

/**
 * Output the admin page content for reports
 * Displays all saved reports in a table format
 */
function brro_clb_reports_page() {
    global $wpdb;
    $table_name = brro_clb_get_reports_table_name();
    
    // Get all report entries, ordered by most recent first
    $reports = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY clb_report_date_made DESC, clb_report_id DESC",
        ARRAY_A
    );
    
    ?>
    <div class="wrap">
        <h1>Compost Logboek Rapportage</h1>
        
        <?php 
        // Display the form to create new reports
        brro_clb_reports_form();
        ?>
        
        <h2>Opgeslagen rapporten</h2>
        
        <?php if (empty($reports)): ?>
            <p>Er zijn nog geen rapporten opgeslagen.</p>
        <?php else: ?>
            <p>Totaal aantal rapporten: <strong><?php echo count($reports); ?></strong></p>
            
            <style>
                /* Style for reports table columns */
                .brro-clb-reports-table th:nth-child(1),
                .brro-clb-reports-table td:nth-child(1) {
                    max-width: 180px;
                    width: 180px;
                    box-sizing: border-box;
                }
                .brro-clb-reports-table th:nth-child(2),
                .brro-clb-reports-table td:nth-child(2) {
                    max-width: 180px;
                    width: 180px;
                    box-sizing: border-box;
                }
                .brro-clb-reports-table th:nth-child(3),
                .brro-clb-reports-table td:nth-child(3) {
                    /* Locations column - auto width, takes remaining space */
                    width: auto;
                    box-sizing: border-box;
                }
                .brro-clb-reports-table th:nth-child(4),
                .brro-clb-reports-table td:nth-child(4) {
                    /* Categories column - auto width */
                    width: auto;
                    box-sizing: border-box;
                }
                .brro-clb-reports-table th:nth-child(5),
                .brro-clb-reports-table td:nth-child(5) {
                    max-width: 180px;
                    width: 180px;
                    box-sizing: border-box;
                }
            </style>
            
            <table class="wp-list-table widefat fixed striped brro-clb-reports-table">
                <thead>
                    <tr>
                        <th>Data van</th>
                        <th>Data tot en met</th>
                        <th>Locaties</th>
                        <th>Categorieën</th>
                        <th>Rapport</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>
                                <?php
                                // Display 'Eerste log' if date is 1900-01-01, otherwise format the date
                                if ($report['clb_report_date_from'] === '1900-01-01') {
                                    echo esc_html('Eerste log');
                                } else {
                                    echo esc_html(date_i18n(get_option('date_format'), strtotime($report['clb_report_date_from'])));
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Display 'Nu' if date is 3025-01-01, otherwise format the date
                                if ($report['clb_report_date_to'] === '3025-01-01') {
                                    echo esc_html('Nu');
                                } else {
                                    echo esc_html(date_i18n(get_option('date_format'), strtotime($report['clb_report_date_to'])));
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Decode JSON-encoded locations
                                $locations = json_decode($report['clb_report_locations'], true);
                                
                                // Check if taxonomy filtering was used
                                $taxonomy_data = null;
                                if (brro_clb_reports_column_exists('clb_report_taxonomy_terms')) {
                                    $taxonomy_json = isset($report['clb_report_taxonomy_terms']) ? $report['clb_report_taxonomy_terms'] : null;
                                    if (!empty($taxonomy_json)) {
                                        $taxonomy_data = json_decode($taxonomy_json, true);
                                    }
                                }
                                
                                // Check for taxonomy filter (format: multiple taxonomies)
                                $has_taxonomy_filter = false;
                                if ($taxonomy_data && isset($taxonomy_data['terms']) && !empty($taxonomy_data['terms'])) {
                                    $has_taxonomy_filter = true;
                                }
                                
                                if ($has_taxonomy_filter) {
                                    // Taxonomy option was used - show "Alle locaties"
                                    echo esc_html('Alle locaties');
                                } elseif ($locations === 'all') {
                                    // Display 'all' as "Alle locaties"
                                    echo esc_html('Alle locaties');
                                } elseif (is_array($locations) && !empty($locations)) {
                                    // Get location names from logs table
                                    $logs_table_name = brro_clb_get_logs_table_name();
                                    $location_ids = array_map('absint', $locations);
                                    
                                    // Sanitize all IDs (absint ensures they're safe integers)
                                    $sanitized_ids = array_map('absint', $location_ids);
                                    $ids_string = implode(',', $sanitized_ids);
                                    
                                    // Build query - table name comes from our function (safe), IDs are sanitized integers
                                    $query = "SELECT DISTINCT clb_log_location_id, clb_log_location_name 
                                              FROM $logs_table_name 
                                              WHERE clb_log_location_id IN ($ids_string)
                                              ORDER BY clb_log_location_name ASC";
                                    
                                    // Get unique location names for the given IDs
                                    $location_data = $wpdb->get_results($query, ARRAY_A);
                                    
                                    // Create a map of ID to name for easy lookup
                                    $location_map = array();
                                    foreach ($location_data as $loc) {
                                        $location_map[$loc['clb_log_location_id']] = $loc['clb_log_location_name'];
                                    }
                                    
                                    // Display location names in the same order as IDs, or fallback to ID if name not found
                                    $location_display = array();
                                    foreach ($location_ids as $loc_id) {
                                        if (isset($location_map[$loc_id])) {
                                            $location_display[] = $location_map[$loc_id];
                                        } else {
                                            // Fallback to ID if name not found in logs
                                            $location_display[] = 'ID: ' . $loc_id;
                                        }
                                    }
                                    
                                    echo esc_html(implode(', ', $location_display));
                                } else {
                                    // Fallback for invalid data
                                    echo '<em>Onbekend</em>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Display categories/taxonomy terms
                                if ($has_taxonomy_filter && $taxonomy_data) {
                                    $term_display = array();
                                    
                                    // Handle taxonomy terms (format: multiple taxonomies)
                                    if (isset($taxonomy_data['terms']) && !empty($taxonomy_data['terms'])) {
                                        foreach ($taxonomy_data['terms'] as $term_info) {
                                            $taxonomy_name = $term_info['taxonomy'];
                                            $term_id = absint($term_info['term_id']);
                                            $term = get_term($term_id, $taxonomy_name);
                                            
                                            if ($term && !is_wp_error($term)) {
                                                // Get taxonomy label
                                                $taxonomy_obj = get_taxonomy($taxonomy_name);
                                                $taxonomy_label = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $taxonomy_name;
                                                $term_display[] = $taxonomy_label . ': ' . $term->name;
                                            }
                                        }
                                    }
                                    
                                    if (!empty($term_display)) {
                                        echo esc_html(implode(', ', $term_display));
                                    } else {
                                        echo '<em>Onbekend</em>';
                                    }
                                } else {
                                    // No taxonomy filtering - show "Alle categorieën"
                                    echo esc_html('Alle categorieën');
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Create URL to frontend report: domain root with ?rapport parameter
                                $report_url = add_query_arg('rapport', $report['clb_report_id'], home_url('/'));
                                ?>
                                <a href="<?php echo esc_url($report_url); ?>" target="_blank">
                                    <?php echo esc_html('Bekijk rapport'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

