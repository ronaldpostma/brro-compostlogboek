<?php
/**
 * Reports functions - database display
 * ===============================================
 * This file contains the functions for displaying saved reports
 * in the wp-admin page.
 * ===============================================
 */


/**
 * Form on the backend page, above the table with db entries to generate a report and save it to the database. This form has the following fields:
 * - Radio 'brro_clb_rep_locations_choice' (label "Voor welke locaties wil je de rapportage inzien?") with 2 options 'all-posts' (label: Voor alle locaties) and 'spec-posts' (label: Voor één of meerdere specifieke locaties
 * Select field 'brro_clb_rep_locations' (label 'Kies locaties (max 4)'), display with jQuery/JS if 'brro_clb_rep_locations_choice' equals or changes to 'spec-posts', otherwise hidden. The options are a dropdown list of Post Titles from all unique post id's encountered in database table 'brro_clb_logs' in column 'clb_log_location_id' - the user can select up to 4 posts here.
 * - Radio with 2 options 'brro_clb_rep_period_choice' (label: Voor welke periode wil je de rapportage inzien?) with 2 options 'all-dates' (label: vanaf het begin) and 'spec-dates' (label: voor een specifieke periode)
 * - If 'brro_clb_rep_period_choice' equals or changes to 'spec-date' display with jQuery/JS two date picker fields: 'brro_clb_rep_period_from' and 'brro_clb_rep_period_to' (labels: 'van' and 'tot en met') in format yyyy-mm-dd
 * - submit button, create a database entry in 'brro_clb_reports' and map to the following columns:
 *    > clb_report_id (unique number for this entry, auto-increment follwing the last entry, first entry starts at 1)
 *    > clb_report_date_made (the today date, when this form is submitted)
 *    > clb_report_date_from (conditional: if 'brro_clb_rep_period_choice' equals 'all-dates', save as date 1900-01-01 - else save the picked date in 'brro_clb_rep_period_from'
 *    > clb_report_date_to (conditional: if 'brro_clb_rep_period_choice' equals 'all-dates', save as date 3025-01-01 - else save the picked date in 'brro_clb_rep_period_to'
 *    > clb_report_locations (conditional: if 'brro_clb_rep_locations_choice' equals 'all-posts' save as string 'all-posts', otherwise save a comma separated string of the post-id's selected in 'brro_clb_rep_locations'
 * - display a success message if the report is saved successfully, otherwise display an error message.
 */

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
    
    // Ensure database tables are up to date (including new column)
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
                                
                                // Check for new format (multiple taxonomies) or old format (single taxonomy)
                                $has_taxonomy_filter = false;
                                if ($taxonomy_data) {
                                    if (isset($taxonomy_data['terms']) && !empty($taxonomy_data['terms'])) {
                                        $has_taxonomy_filter = true;
                                    } elseif (isset($taxonomy_data['taxonomy']) && isset($taxonomy_data['term_ids']) && !empty($taxonomy_data['term_ids'])) {
                                        $has_taxonomy_filter = true;
                                    }
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
                                    
                                    // Handle new format (multiple taxonomies)
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
                                    // Handle old format (single taxonomy) - backward compatibility
                                    elseif (isset($taxonomy_data['taxonomy']) && isset($taxonomy_data['term_ids'])) {
                                        $taxonomy_name = $taxonomy_data['taxonomy'];
                                        $term_ids = array_map('absint', $taxonomy_data['term_ids']);
                                        $taxonomy_obj = get_taxonomy($taxonomy_name);
                                        $taxonomy_label = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $taxonomy_name;
                                        
                                        foreach ($term_ids as $term_id) {
                                            $term = get_term($term_id, $taxonomy_name);
                                            if ($term && !is_wp_error($term)) {
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

/**
 * Build device-email mapping from all logs in database
 * 
 * Creates mappings to identify which device IDs belong to which email users.
 * This is used to count unique users accurately across devices.
 * 
 * @return array {
 *   @type array $email_to_devices Array of device IDs per email (email => array of device IDs)
 *   @type array $device_to_email Array of email per device ID (device ID => email)
 * }
 */
function brro_clb_build_device_email_mapping() {
    global $wpdb;
    
    $logs_table_name = brro_clb_get_logs_table_name();
    $email_to_devices = array();
    $device_to_email = array();
    
    // Query ALL logs to build historical associations
    $all_logs = $wpdb->get_results(
        "SELECT clb_log_device_id, clb_log_email FROM $logs_table_name 
         WHERE clb_log_device_id IS NOT NULL AND clb_log_device_id != ''",
        ARRAY_A
    );
    
    if ($all_logs) {
        foreach ($all_logs as $log) {
            $device_id = trim($log['clb_log_device_id']);
            $encrypted_email = $log['clb_log_email'];
            
            // Skip if no device ID
            if (empty($device_id)) {
                continue;
            }
            
            // If log has an email, decrypt it and build associations
            if (!empty($encrypted_email)) {
                $decrypted_email = brro_clb_decrypt_email($encrypted_email);
                
                // Only process if decryption was successful
                if ($decrypted_email && is_string($decrypted_email) && !empty(trim($decrypted_email))) {
                    $email = trim(strtolower($decrypted_email));
                    
                    // Build email -> devices mapping
                    if (!isset($email_to_devices[$email])) {
                        $email_to_devices[$email] = array();
                    }
                    if (!in_array($device_id, $email_to_devices[$email], true)) {
                        $email_to_devices[$email][] = $device_id;
                    }
                    
                    // Build device -> email mapping (if device not already linked to a different email)
                    // If device is already linked, keep the first association
                    if (!isset($device_to_email[$device_id])) {
                        $device_to_email[$device_id] = $email;
                    }
                }
            }
        }
    }
    
    return array(
        'email_to_devices' => $email_to_devices,
        'device_to_email' => $device_to_email
    );
}

/**
 * Count unique users from logs using email-priority logic
 * 
 * @param array $logs Array of log entries to count users from
 * @param array $device_to_email Mapping of device ID => email (from brro_clb_build_device_email_mapping)
 * @return int Count of unique users
 */
function brro_clb_count_unique_users($logs, $device_to_email) {
    $unique_emails = array();
    $anonymous_device_ids = array();
    
    foreach ($logs as $log) {
        $device_id = !empty($log['clb_log_device_id']) ? trim($log['clb_log_device_id']) : '';
        
        if (empty($device_id)) {
            continue;
        }
        
        // Check if log has an email
        $has_email = false;
        $email = null;
        
        if (!empty($log['clb_log_email'])) {
            $decrypted = brro_clb_decrypt_email($log['clb_log_email']);
            if ($decrypted && is_string($decrypted) && !empty(trim($decrypted))) {
                $email = trim(strtolower($decrypted));
                $has_email = true;
            }
        }
        
        if ($has_email) {
            // Log has email - count this email as a user
            if (!in_array($email, $unique_emails, true)) {
                $unique_emails[] = $email;
            }
        } else {
            // Log has no email - check if device has been associated with email before
            if (isset($device_to_email[$device_id])) {
                // Device is linked to an email - count under that email
                $linked_email = $device_to_email[$device_id];
                if (!in_array($linked_email, $unique_emails, true)) {
                    $unique_emails[] = $linked_email;
                }
            } else {
                // Device has never been linked to email - count as anonymous
                if (!in_array($device_id, $anonymous_device_ids, true)) {
                    $anonymous_device_ids[] = $device_id;
                }
            }
        }
    }
    
    // Total unique users = unique emails + unique anonymous device IDs
    return count($unique_emails) + count($anonymous_device_ids);
}

/**
 * Helper function to get logs and calculate statistics for a report
 * 
 * @param array $report Report data from database
 * @return array Array containing logs and calculated statistics:
 *   - 'logs': array of log entries
 *   - 'total_activities': total number of activities
 *   - 'input_count': number of input activities (groenafval toegevoegd)
 *   - 'output_count': number of output activities (compost geoogst)
 *   - 'total_input_weight': total weight of all input activities in kg
 *   - 'total_output_weight': total weight of all output activities in kg
 *   - 'location_names': array of location names for display
 *   - 'unique_users': count of unique users based on email (primary) and device IDs
 */
function brro_clb_get_report_data($report) {
    global $wpdb;
    
    $logs_table_name = brro_clb_get_logs_table_name();
    
    // Get date range from report
    $date_from = $report['clb_report_date_from'];
    $date_to = $report['clb_report_date_to'];
    
    // Decode locations from report
    $locations = json_decode($report['clb_report_locations'], true);
    
    // Check if taxonomy filtering was used
    $taxonomy_data = null;
    $filter_by_taxonomy = false;
    if (brro_clb_reports_column_exists('clb_report_taxonomy_terms')) {
        $taxonomy_json = isset($report['clb_report_taxonomy_terms']) ? $report['clb_report_taxonomy_terms'] : null;
        if (!empty($taxonomy_json)) {
            $taxonomy_data = json_decode($taxonomy_json, true);
            
            // Check for new format (multiple taxonomies)
            if ($taxonomy_data && isset($taxonomy_data['terms']) && !empty($taxonomy_data['terms'])) {
                $filter_by_taxonomy = true;
            }
            // Backward compatibility: check for old format (single taxonomy)
            elseif ($taxonomy_data && isset($taxonomy_data['taxonomy']) && isset($taxonomy_data['term_ids']) && !empty($taxonomy_data['term_ids'])) {
                // Convert old format to new format for processing
                $terms_array = array();
                foreach ($taxonomy_data['term_ids'] as $term_id) {
                    $terms_array[] = array(
                        'taxonomy' => $taxonomy_data['taxonomy'],
                        'term_id' => $term_id
                    );
                }
                $taxonomy_data['terms'] = $terms_array;
                $filter_by_taxonomy = true;
            }
        }
    }
    
    // If filtering by taxonomy, get post IDs that have the selected terms
    $taxonomy_post_ids = array();
    if ($filter_by_taxonomy && isset($taxonomy_data['terms'])) {
        // Group terms by taxonomy
        $terms_by_taxonomy = array();
        foreach ($taxonomy_data['terms'] as $term_info) {
            $taxonomy_name = $term_info['taxonomy'];
            $term_id = absint($term_info['term_id']);
            if (!isset($terms_by_taxonomy[$taxonomy_name])) {
                $terms_by_taxonomy[$taxonomy_name] = array();
            }
            $terms_by_taxonomy[$taxonomy_name][] = $term_id;
        }
        
        // Get posts for each taxonomy group and combine results
        $all_post_ids = array();
        $post_type = brro_clb_get_post_type_for_locations();
        
        foreach ($terms_by_taxonomy as $taxonomy_name => $term_ids) {
            $term_ids = array_filter(array_map('absint', $term_ids)); // Sanitize and remove zeros
            if (empty($term_ids)) {
                continue;
            }
            
            // Get all posts that have these terms assigned
            $tax_query_args = array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => array(
                    array(
                        'taxonomy' => $taxonomy_name,
                        'field' => 'term_id',
                        'terms' => $term_ids,
                        'operator' => 'IN'
                    )
                )
            );
            
            $taxonomy_posts = get_posts($tax_query_args);
            if (!is_wp_error($taxonomy_posts) && !empty($taxonomy_posts)) {
                $all_post_ids = array_merge($all_post_ids, array_map('absint', $taxonomy_posts));
            }
        }
        
        // Remove duplicates
        $taxonomy_post_ids = array_unique($all_post_ids);
        
        // If no posts found with these terms, return empty results
        if (empty($taxonomy_post_ids)) {
            return array(
                'logs' => array(),
                'total_activities' => 0,
                'input_count' => 0,
                'output_count' => 0,
                'total_input_weight' => 0,
                'total_output_weight' => 0,
                'location_names' => array(),
                'unique_users' => 0,
                'taxonomy_data' => $taxonomy_data
            );
        }
    }
    
    // Build WHERE clause for locations and prepare query
    $location_ids_for_query = array();
    $query_params = array();
    
    if ($filter_by_taxonomy) {
        // Filter by post IDs that have the selected taxonomy terms
        if (empty($taxonomy_post_ids)) {
            // No posts found - return empty results
            return array(
                'logs' => array(),
                'total_activities' => 0,
                'input_count' => 0,
                'output_count' => 0,
                'total_input_weight' => 0,
                'total_output_weight' => 0,
                'location_names' => array(),
                'unique_users' => 0,
                'taxonomy_data' => $taxonomy_data
            );
        }
        $sanitized_ids = array_map('absint', $taxonomy_post_ids);
        $placeholders = implode(',', array_fill(0, count($sanitized_ids), '%d'));
        $location_where = "clb_log_location_id IN ($placeholders)";
        $query_params = array_merge($query_params, $sanitized_ids);
        $location_ids_for_query = $sanitized_ids;
    } elseif ($locations === 'all') {
        // No location filter needed - get all locations
        $location_where = '1=1';
    } elseif (is_array($locations) && !empty($locations)) {
        // Filter by specific location IDs
        $location_ids_for_query = array_map('absint', $locations);
        $sanitized_ids = array_filter($location_ids_for_query); // Remove any zeros
        if (empty($sanitized_ids)) {
            // No valid IDs - return empty results
            return array(
                'logs' => array(),
                'total_activities' => 0,
                'input_count' => 0,
                'output_count' => 0,
                'total_input_weight' => 0,
                'total_output_weight' => 0,
                'location_names' => array(),
                'unique_users' => 0
            );
        }
        $placeholders = implode(',', array_fill(0, count($sanitized_ids), '%d'));
        $location_where = "clb_log_location_id IN ($placeholders)";
        $query_params = array_merge($query_params, $sanitized_ids);
    } else {
        // Invalid locations data - return empty results
        return array(
            'logs' => array(),
            'total_activities' => 0,
            'input_count' => 0,
            'output_count' => 0,
            'total_input_weight' => 0,
            'total_output_weight' => 0,
            'location_names' => array(),
            'unique_users' => 0
        );
    }
    
    // Build query with date range and location filters
    $query = "SELECT * FROM $logs_table_name 
              WHERE $location_where 
              AND clb_log_date >= %s 
              AND clb_log_date <= %s 
              ORDER BY clb_log_date DESC, clb_log_time DESC";
    
    // Add date parameters
    $query_params[] = $date_from;
    $query_params[] = $date_to;
    
    // Get logs matching the report criteria using prepared statement
    if (!empty($query_params)) {
        $logs = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
    } else {
        // Fallback (shouldn't happen, but safety check)
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $logs_table_name 
             WHERE clb_log_date >= %s 
             AND clb_log_date <= %s 
             ORDER BY clb_log_date DESC, clb_log_time DESC",
            $date_from,
            $date_to
        ), ARRAY_A);
    }
    
    if (!$logs) {
        $logs = array();
    }
    
    // Calculate statistics
    $total_activities = count($logs);
    $input_count = 0;
    $output_count = 0;
    $total_input_weight = 0;
    $total_output_weight = 0;
    
    foreach ($logs as $log) {
        if ($log['clb_log_activity'] === 'input') {
            $input_count++;
            $total_input_weight += floatval($log['clb_log_total_weight']);
        } else {
            $output_count++;
            $total_output_weight += floatval($log['clb_log_total_weight']);
        }
    }
    
    // Get location names for display
    $location_names = array();
    if ($filter_by_taxonomy && !empty($location_ids_for_query)) {
        // Get names for locations filtered by taxonomy
        $sanitized_ids = array_filter(array_map('absint', $location_ids_for_query));
        if (!empty($sanitized_ids)) {
            $placeholders = implode(',', array_fill(0, count($sanitized_ids), '%d'));
            $location_query = "SELECT DISTINCT clb_log_location_id, clb_log_location_name 
                              FROM $logs_table_name 
                              WHERE clb_log_location_id IN ($placeholders)
                              ORDER BY clb_log_location_name ASC";
            $location_data = $wpdb->get_results($wpdb->prepare($location_query, $sanitized_ids), ARRAY_A);
            $location_map = array();
            foreach ($location_data as $loc) {
                $location_map[$loc['clb_log_location_id']] = $loc['clb_log_location_name'];
            }
            foreach ($location_ids_for_query as $loc_id) {
                if (isset($location_map[$loc_id])) {
                    $location_names[] = $location_map[$loc_id];
                }
            }
        }
    } elseif ($locations === 'all') {
        // Get all unique location names from the logs
        $location_ids_in_logs = array_unique(array_column($logs, 'clb_log_location_id'));
        if (!empty($location_ids_in_logs)) {
            $sanitized_ids = array_filter(array_map('absint', $location_ids_in_logs));
            if (!empty($sanitized_ids)) {
                $placeholders = implode(',', array_fill(0, count($sanitized_ids), '%d'));
                $location_query = "SELECT DISTINCT clb_log_location_id, clb_log_location_name 
                                  FROM $logs_table_name 
                                  WHERE clb_log_location_id IN ($placeholders)
                                  ORDER BY clb_log_location_name ASC";
                $location_data = $wpdb->get_results($wpdb->prepare($location_query, $sanitized_ids), ARRAY_A);
                foreach ($location_data as $loc) {
                    $location_names[] = $loc['clb_log_location_name'];
                }
            }
        }
    } elseif (is_array($locations) && !empty($locations) && !empty($location_ids_for_query)) {
        // Get names for specific locations
        $sanitized_ids = array_filter(array_map('absint', $location_ids_for_query));
        if (!empty($sanitized_ids)) {
            $placeholders = implode(',', array_fill(0, count($sanitized_ids), '%d'));
            $location_query = "SELECT DISTINCT clb_log_location_id, clb_log_location_name 
                              FROM $logs_table_name 
                              WHERE clb_log_location_id IN ($placeholders)
                              ORDER BY clb_log_location_name ASC";
            $location_data = $wpdb->get_results($wpdb->prepare($location_query, $sanitized_ids), ARRAY_A);
            $location_map = array();
            foreach ($location_data as $loc) {
                $location_map[$loc['clb_log_location_id']] = $loc['clb_log_location_name'];
            }
            foreach ($location_ids_for_query as $loc_id) {
                if (isset($location_map[$loc_id])) {
                    $location_names[] = $location_map[$loc_id];
                }
            }
        }
    }
    
    // Count unique users using email-priority logic
    $mapping = brro_clb_build_device_email_mapping();
    $unique_users = brro_clb_count_unique_users($logs, $mapping['device_to_email']);
    
    // Build return array
    $return_data = array(
        'logs' => $logs,
        'total_activities' => $total_activities,
        'input_count' => $input_count,
        'output_count' => $output_count,
        'total_input_weight' => $total_input_weight,
        'total_output_weight' => $total_output_weight,
        'location_names' => $location_names,
        'unique_users' => $unique_users
    );
    
    // Add taxonomy data if filtering by taxonomy
    if ($filter_by_taxonomy && $taxonomy_data) {
        $return_data['taxonomy_data'] = $taxonomy_data;
    }
    
    return $return_data;
}

/**
 * Helper function to generate the report header HTML
 * Includes logo and site name with "logboek rapportage" title
 * 
 * @return string Header HTML content
 */
function brro_clb_get_report_header() {
    // Get logo URL from plugin settings
    $options = get_option('brro_clb_settings', array());
    $logo_url = isset($options['brro_clb_logo_url']) ? esc_url($options['brro_clb_logo_url']) : '';
    
    ob_start();
    ?>
    <div class="brro-clb-template-header">
        <?php
        // Display logo from plugin settings
        if (!empty($logo_url)) {
            echo '<img class="brro-clb-logo" src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . ' Logo">';
        }
        ?>
        <div class="report-title"><?php bloginfo('name'); ?> logboek rapportage</div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Front end rapport function
 * Gets report data from database based on ?rapport parameter in URL
 * 
 * @return string Report HTML content
 */
function brro_clb_get_rapport() {
    global $wpdb;
    
    // Get raw parameter value from URL
    $rapport_param = isset($_GET['rapport']) ? $_GET['rapport'] : '';
    
    // If no parameter provided, show error
    if (empty($rapport_param)) {
        return '<p>Geen rapport ID opgegeven.</p>';
    }
    
    // Check if parameter is numeric (report ID) or email address
    $report_id = absint($rapport_param);
    $is_email = ($report_id === 0 && !empty($rapport_param));
    
    // If it's an email address, handle email-based report
    if ($is_email) {
        // URL-decode and sanitize the email
        $email = sanitize_email(urldecode($rapport_param));
        
        // Validate email format
        if (!is_email($email)) {
            return '<p>Ongeldig e-mailadres opgegeven.</p>';
        }
        
        // Retrieve logs for this email address
        if (!function_exists('brro_clb_get_logs_by_email')) {
            return '<p>Fout: Functie voor het ophalen van logs per e-mailadres is niet beschikbaar.</p>';
        }
        
        $logs = brro_clb_get_logs_by_email($email);
        
        // If no logs found, show message
        if (empty($logs)) {
            return '<p>Geen logs gevonden voor dit e-mailadres.</p>';
        }
        
        // Calculate statistics from logs
        $total_activities = count($logs);
        $input_count = 0;
        $output_count = 0;
        $total_input_weight = 0;
        $total_output_weight = 0;
        
        foreach ($logs as $log) {
            if ($log['clb_log_activity'] === 'input') {
                $input_count++;
                $total_input_weight += floatval($log['clb_log_total_weight']);
            } else {
                $output_count++;
                $total_output_weight += floatval($log['clb_log_total_weight']);
            }
        }
        
        // Get unique location names from logs
        $location_names = array();
        $location_ids_in_logs = array_unique(array_column($logs, 'clb_log_location_id'));
        if (!empty($location_ids_in_logs)) {
            $location_names_map = array();
            foreach ($logs as $log) {
                $loc_id = $log['clb_log_location_id'];
                if (!isset($location_names_map[$loc_id])) {
                    $location_names_map[$loc_id] = $log['clb_log_location_name'];
                }
            }
            $location_names = array_values($location_names_map);
            sort($location_names);
        }
        
        // Count unique users using email-priority logic
        $mapping = brro_clb_build_device_email_mapping();
        $unique_users = brro_clb_count_unique_users($logs, $mapping['device_to_email']);
        
        // Determine date range from logs
        $date_from = null;
        $date_to = null;
        foreach ($logs as $log) {
            $log_date = $log['clb_log_date'];
            if ($date_from === null || $log_date < $date_from) {
                $date_from = $log_date;
            }
            if ($date_to === null || $log_date > $date_to) {
                $date_to = $log_date;
            }
        }
        
        // Format display variables
        $total_input_weight = number_format_i18n($total_input_weight, 2);
        $total_output_weight = number_format_i18n($total_output_weight, 2);
        $date_from_display = ($date_from) ? date_i18n(get_option('date_format'), strtotime($date_from)) : 'Eerste log';
        $date_to_display = ($date_to) ? date_i18n(get_option('date_format'), strtotime($date_to)) : 'Nu';
        $locations_display = (!empty($location_names)) ? implode(', ', $location_names) : 'Onbekend';
        $filter_by_taxonomy = false;
        $taxonomy_data = null;
        // Set $locations to 'all' if multiple locations, empty array otherwise (for display logic compatibility)
        $locations = (count($location_names) > 1) ? 'all' : array();
        
        // Skip to display section (will group logs by location later)
        $skip_to_display = true;
        
    } else {
        // Existing report ID logic
        // Get table name
        $table_name = brro_clb_get_reports_table_name();
        
        // Get report data from database using prepared statement
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE clb_report_id = %d",
            $report_id
        ), ARRAY_A);
        
        // If report not found, show error
        if (!$report) {
            return '<p>Rapport niet gevonden.</p>';
        }
        
        // Get report data and statistics using helper function
        $report_data = brro_clb_get_report_data($report);
        
        // Extract variables for easier use in template
        $logs = $report_data['logs'];
        $total_activities = $report_data['total_activities'];
        $input_count = $report_data['input_count'];
        $output_count = $report_data['output_count'];
        $total_input_weight = number_format_i18n($report_data['total_input_weight'], 2);
        $total_output_weight = number_format_i18n($report_data['total_output_weight'], 2);
        $location_names = $report_data['location_names'];
        $unique_users = $report_data['unique_users'];
        $taxonomy_data = isset($report_data['taxonomy_data']) ? $report_data['taxonomy_data'] : null;
        $filter_by_taxonomy = !empty($taxonomy_data);
        $skip_to_display = false;
        
        // Format date from
        $date_from_display = ($report['clb_report_date_from'] === '1900-01-01') 
            ? 'Eerste log' 
            : date_i18n(get_option('date_format'), strtotime($report['clb_report_date_from']));
        
        // Format date to
        $date_to_display = ($report['clb_report_date_to'] === '3025-01-01') 
            ? 'Nu' 
            : date_i18n(get_option('date_format'), strtotime($report['clb_report_date_to']));
        
        // Format locations display
        $locations_display = '';
        $locations = json_decode($report['clb_report_locations'], true);
        if ($filter_by_taxonomy) {
            $locations_display = 'Alle locaties';
        } elseif ($locations === 'all') {
            $locations_display = 'Alle locaties';
        } elseif (!empty($location_names)) {
            $locations_display = implode(', ', $location_names);
        } else {
            $locations_display = '<em>Onbekend</em>';
        }
    }
    
    // Set location summary text and label based on report type
    if ($skip_to_display) {
        // Email-based report: determine based on number of locations
        if (count($location_names) === 1) {
            $location_summary_text = 'deze locatie';
            $location_label = 'Locatie:';
        } elseif (count($location_names) > 1) {
            $location_summary_text = 'deze locaties';
            $location_label = 'Locaties:';
        } else {
            $location_summary_text = 'deze locatie(s)';
            $location_label = 'Locaties:';
        }
    } else {
        // Existing report ID logic
        $location_summary_text = '';
        if ($filter_by_taxonomy || $locations === 'all') {
            $location_summary_text = 'alle locaties';
        } elseif (count($location_names) === 1) {
            $location_summary_text = 'deze locatie';
        } elseif (count($location_names) > 1) {
            $location_summary_text = 'deze locaties';
        } else {
            $location_summary_text = 'deze locatie(s)'; // Fallback for edge cases
        }
        
        // Determine location label (singular or plural)
        $location_label = '';
        if ($filter_by_taxonomy || $locations === 'all') {
            $location_label = 'Locaties:';
        } elseif (count($location_names) === 1) {
            $location_label = 'Locatie:';
        } elseif (count($location_names) > 1) {
            $location_label = 'Locaties:';
        } else {
            $location_label = 'Locaties:'; // Fallback for edge cases
        }
    }
    
    // Group logs by category (if taxonomy filtering) or by location
    $logs_by_category = array();
    $logs_by_location = array();
    $most_active_location_name = '';
    
    // For email-based reports, group by location
    if ($skip_to_display && !empty($logs)) {
        foreach ($logs as $log) {
            $loc_id = $log['clb_log_location_id'];
            if (!isset($logs_by_location[$loc_id])) {
                $logs_by_location[$loc_id] = array(
                    'name' => $log['clb_log_location_name'],
                    'logs' => array()
                );
            }
            $logs_by_location[$loc_id]['logs'][] = $log;
        }
        
        // Sort by log count in descending order
        uasort($logs_by_location, function($a, $b) {
            return count($b['logs']) - count($a['logs']);
        });
        
        // Get the most active location (first item after sorting)
        if (!empty($logs_by_location)) {
            $first_location = reset($logs_by_location);
            $most_active_location_name = $first_location['name'];
        }
    } elseif ($filter_by_taxonomy && !empty($logs) && isset($taxonomy_data['terms']) && !empty($taxonomy_data['terms'])) {
        // Group logs by taxonomy and term (category), then by location within each category
        // Handle new format: multiple taxonomies with terms array
        $selected_terms_info = $taxonomy_data['terms'];
        
        // Build a map of location_id => array of taxonomy/term pairs for that location
        $location_to_terms = array();
        $location_ids_in_logs = array_unique(array_column($logs, 'clb_log_location_id'));
        
        // Build lookup map of selected terms by taxonomy and term_id
        $selected_terms_map = array();
        foreach ($selected_terms_info as $term_info) {
            $taxonomy_name = $term_info['taxonomy'];
            $term_id = absint($term_info['term_id']);
            if (!isset($selected_terms_map[$taxonomy_name])) {
                $selected_terms_map[$taxonomy_name] = array();
            }
            $selected_terms_map[$taxonomy_name][] = $term_id;
        }
        
        // For each location, get its terms and check if they match selected terms
        foreach ($location_ids_in_logs as $location_id) {
            foreach ($selected_terms_map as $taxonomy_name => $selected_term_ids) {
                $location_terms = wp_get_post_terms($location_id, $taxonomy_name, array('fields' => 'ids'));
                if (!is_wp_error($location_terms) && !empty($location_terms)) {
                    // Only include terms that were selected for this report
                    $matching_terms = array_intersect($location_terms, $selected_term_ids);
                    if (!empty($matching_terms)) {
                        if (!isset($location_to_terms[$location_id])) {
                            $location_to_terms[$location_id] = array();
                        }
                        foreach ($matching_terms as $term_id) {
                            $location_to_terms[$location_id][] = array(
                                'taxonomy' => $taxonomy_name,
                                'term_id' => $term_id
                            );
                        }
                    }
                }
            }
        }
        
        // Group logs by taxonomy and category (term)
        foreach ($logs as $log) {
            $loc_id = $log['clb_log_location_id'];
            if (isset($location_to_terms[$loc_id])) {
                // This location has matching terms assigned, group by taxonomy and term
                foreach ($location_to_terms[$loc_id] as $term_info) {
                    $taxonomy_name = $term_info['taxonomy'];
                    $term_id = $term_info['term_id'];
                    
                    // Create unique key combining taxonomy and term_id
                    $category_key = $taxonomy_name . '|' . $term_id;
                    
                    if (!isset($logs_by_category[$category_key])) {
                        $term = get_term($term_id, $taxonomy_name);
                        if ($term && !is_wp_error($term)) {
                            $taxonomy_obj = get_taxonomy($taxonomy_name);
                            $taxonomy_label = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $taxonomy_name;
                            
                            $logs_by_category[$category_key] = array(
                                'name' => $term->name,
                                'taxonomy' => $taxonomy_name,
                                'taxonomy_label' => $taxonomy_label,
                                'term_id' => $term_id,
                                'locations' => array()
                            );
                        } else {
                            continue; // Skip invalid term
                        }
                    }
                    
                    // Group by location within this category
                    if (!isset($logs_by_category[$category_key]['locations'][$loc_id])) {
                        $logs_by_category[$category_key]['locations'][$loc_id] = array(
                            'name' => $log['clb_log_location_name'],
                            'logs' => array()
                        );
                    }
                    
                    $logs_by_category[$category_key]['locations'][$loc_id]['logs'][] = $log;
                }
            }
        }
        
        // Calculate activity counts for each category and sort by activity (most to least)
        foreach ($logs_by_category as $category_key => &$category_data) {
            $category_total_activities = 0;
            foreach ($category_data['locations'] as $loc_data) {
                $category_total_activities += count($loc_data['logs']);
            }
            $category_data['total_activities'] = $category_total_activities;
        }
        unset($category_data); // Break reference
        
        // Sort categories by total activities (descending)
        uasort($logs_by_category, function($a, $b) {
            return $b['total_activities'] - $a['total_activities'];
        });
        
        // Sort locations within each category by activity count (descending)
        foreach ($logs_by_category as $category_key => &$category_data) {
            uasort($category_data['locations'], function($a, $b) {
                return count($b['logs']) - count($a['logs']);
            });
        }
        unset($category_data); // Break reference
        
        // Get most active location from all categories
        $max_location_count = 0;
        foreach ($logs_by_category as $category_data) {
            foreach ($category_data['locations'] as $loc_data) {
                $loc_count = count($loc_data['logs']);
                if ($loc_count > $max_location_count) {
                    $max_location_count = $loc_count;
                    $most_active_location_name = $loc_data['name'];
                }
            }
        }
    } elseif ($has_old_format) {
        // Backward compatibility: Handle old format (single taxonomy)
        $taxonomy_name = $taxonomy_data['taxonomy'];
        $selected_term_ids = array_map('absint', $taxonomy_data['term_ids']);
        
        // Build a map of location_id => array of term_ids for that location
        $location_to_terms = array();
        $location_ids_in_logs = array_unique(array_column($logs, 'clb_log_location_id'));
        
        foreach ($location_ids_in_logs as $location_id) {
            $location_terms = wp_get_post_terms($location_id, $taxonomy_name, array('fields' => 'ids'));
            if (!is_wp_error($location_terms) && !empty($location_terms)) {
                // Only include terms that were selected for this report
                $matching_terms = array_intersect($location_terms, $selected_term_ids);
                if (!empty($matching_terms)) {
                    $location_to_terms[$location_id] = $matching_terms;
                }
            }
        }
        
        // Group logs by category (term)
        foreach ($logs as $log) {
            $loc_id = $log['clb_log_location_id'];
            if (isset($location_to_terms[$loc_id])) {
                // This location has terms assigned, group by term
                foreach ($location_to_terms[$loc_id] as $term_id) {
                    $category_key = $taxonomy_name . '|' . $term_id;
                    
                    if (!isset($logs_by_category[$category_key])) {
                        $term = get_term($term_id, $taxonomy_name);
                        if ($term && !is_wp_error($term)) {
                            $taxonomy_obj = get_taxonomy($taxonomy_name);
                            $taxonomy_label = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $taxonomy_name;
                            
                            $logs_by_category[$category_key] = array(
                                'name' => $term->name,
                                'taxonomy' => $taxonomy_name,
                                'taxonomy_label' => $taxonomy_label,
                                'term_id' => $term_id,
                                'locations' => array()
                            );
                        } else {
                            continue; // Skip invalid term
                        }
                    }
                    
                    // Group by location within this category
                    if (!isset($logs_by_category[$category_key]['locations'][$loc_id])) {
                        $logs_by_category[$category_key]['locations'][$loc_id] = array(
                            'name' => $log['clb_log_location_name'],
                            'logs' => array()
                        );
                    }
                    
                    $logs_by_category[$category_key]['locations'][$loc_id]['logs'][] = $log;
                }
            }
        }
        
        // Calculate activity counts for each category and sort by activity (most to least)
        foreach ($logs_by_category as $category_key => &$category_data) {
            $category_total_activities = 0;
            foreach ($category_data['locations'] as $loc_data) {
                $category_total_activities += count($loc_data['logs']);
            }
            $category_data['total_activities'] = $category_total_activities;
        }
        unset($category_data); // Break reference
        
        // Sort categories by total activities (descending)
        uasort($logs_by_category, function($a, $b) {
            return $b['total_activities'] - $a['total_activities'];
        });
        
        // Sort locations within each category by activity count (descending)
        foreach ($logs_by_category as $category_key => &$category_data) {
            uasort($category_data['locations'], function($a, $b) {
                return count($b['logs']) - count($a['logs']);
            });
        }
        unset($category_data); // Break reference
        
        // Get most active location from all categories
        $max_location_count = 0;
        foreach ($logs_by_category as $category_data) {
            foreach ($category_data['locations'] as $loc_data) {
                $loc_count = count($loc_data['logs']);
                if ($loc_count > $max_location_count) {
                    $max_location_count = $loc_count;
                    $most_active_location_name = $loc_data['name'];
                }
            }
        }
    } else {
        // Normal grouping by location (no taxonomy filtering)
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $loc_id = $log['clb_log_location_id'];
                if (!isset($logs_by_location[$loc_id])) {
                    $logs_by_location[$loc_id] = array(
                        'name' => $log['clb_log_location_name'],
                        'logs' => array()
                    );
                }
                $logs_by_location[$loc_id]['logs'][] = $log;
            }
            
            // Sort by log count in descending order
            uasort($logs_by_location, function($a, $b) {
                return count($b['logs']) - count($a['logs']);
            });
            
            // Get the most active location (first item after sorting)
            if (!empty($logs_by_location)) {
                $first_location = reset($logs_by_location);
                $most_active_location_name = $first_location['name'];
            }
        }
    }
    
    // Display the report data
    ob_start();
    ?>
    <div class="brro-clb-report-content">
        <?php if ($skip_to_display && isset($email)) : ?>
        <div class="brro-clb-report-summary brro-clb-total-summary">
            Logs voor: 
            <strong><?php echo esc_html($email); ?></strong>
        </div>
        <?php endif; ?>
        <div class="brro-clb-report-summary brro-clb-total-summary">
            Periode: 
            <strong><?php echo esc_html($date_from_display); ?> - <?php echo esc_html($date_to_display); ?></strong>
        </div>
        
        <div class="brro-clb-report-summary brro-clb-total-summary">
            <?php echo esc_html($location_label); ?> 
            <strong>
            <?php 
            if ($locations === 'all') {
                echo esc_html($locations_display);
            } else {
                echo esc_html($locations_display);
            }
            ?>
            </strong>
        </div>
        
        <div class="brro-clb-report-summary brro-clb-total-summary">
            Totaal aantal logs: 
            <strong><?php echo esc_html($total_activities); ?></strong>
             <small>(Groenafval toegevoegd: <strong><?php echo esc_html($input_count); ?></strong>x | Compost geoogst: <strong><?php echo esc_html($output_count); ?></strong>x)</small>
        </div>
        <?php if (!$skip_to_display) : ?>
        <div class="brro-clb-report-summary brro-clb-total-summary">
            Totaal aantal unieke gebruikers: 
            <strong><?php echo esc_html($unique_users); ?></strong>
        </div>
        <?php endif; ?>
        <div class="brro-clb-report-summary brro-clb-total-summary">
            Totale hoeveelheid toegevoegde groenafval: 
            <strong><?php echo esc_html($total_input_weight); ?>kg</strong>
        </div>
        <div class="brro-clb-report-summary brro-clb-total-summary">
            Totale hoeveelheid geoogste compost: 
            <strong><?php echo esc_html($total_output_weight); ?>kg</strong>
        </div>
        <?php if (!empty($most_active_location_name) && count($location_names) > 1) : ?>
        <div class="brro-clb-report-summary brro-clb-total-summary">
            Meest actieve locatie: 
            <strong><?php echo esc_html($most_active_location_name); ?></strong>
        </div>
        <?php endif; ?>
        
        <?php if (empty($logs)) : ?>
            <div class="brro-clb-report-section brro-clb-report-complete-logs">
                <p><em>Geen logs gevonden voor deze periode en locatie(s).</em></p>
            </div>
        <?php else : 
            // Determine if we should show location column (only if more than 1 location)
            $show_location_column = ($filter_by_taxonomy || $locations === 'all' || count($location_names) > 1);
            
            // If taxonomy filtering, show per-category breakdown
            if ($filter_by_taxonomy && !empty($logs_by_category)) {
                // Display per-category sections
                foreach ($logs_by_category as $category_key => $category_data) :
                    $category_logs = array();
                    foreach ($category_data['locations'] as $loc_data) {
                        $category_logs = array_merge($category_logs, $loc_data['logs']);
                    }
                    
                    // Calculate statistics for this category
                    $cat_total_activities = count($category_logs);
                    $cat_input_count = 0;
                    $cat_output_count = 0;
                    $cat_total_input_weight = 0;
                    $cat_total_output_weight = 0;
                    
                    foreach ($category_logs as $log) {
                        if ($log['clb_log_activity'] === 'input') {
                            $cat_input_count++;
                            $cat_total_input_weight += floatval($log['clb_log_total_weight']);
                        } else {
                            $cat_output_count++;
                            $cat_total_output_weight += floatval($log['clb_log_total_weight']);
                        }
                    }
                    
                    // Count unique users for this category
                    $mapping = brro_clb_build_device_email_mapping();
                    $cat_unique_users = brro_clb_count_unique_users($category_logs, $mapping['device_to_email']);
                    $cat_total_input_weight_formatted = number_format_i18n($cat_total_input_weight, 2);
                    $cat_total_output_weight_formatted = number_format_i18n($cat_total_output_weight, 2);
                    ?>
                    <div class="brro-clb-report-section brro-clb-report-complete-logs">
                        <?php
                        // Display the report header using the helper function
                        if (function_exists('brro_clb_get_report_header')) {
                            echo brro_clb_get_report_header();
                        }
                        ?>
                        <h3><?php echo esc_html($category_data['taxonomy_label'] . ': ' . $category_data['name']); ?></h3>
                        <div class="brro-clb-report-summary">
                            Aantal logs: 
                            <strong><?php echo esc_html($cat_total_activities); ?></strong>
                            <small>(Groenafval toegevoegd: <strong><?php echo esc_html($cat_input_count); ?></strong>x | Compost geoogst: <strong><?php echo esc_html($cat_output_count); ?></strong>x)</small>
                        </div>
                        <div class="brro-clb-report-summary">
                            Aantal unieke gebruikers: 
                            <strong><?php echo esc_html($cat_unique_users); ?></strong>
                        </div>
                        <div class="brro-clb-report-summary">
                            Hoeveelheid toegevoegde groenafval: 
                            <strong><?php echo esc_html($cat_total_input_weight_formatted); ?>kg</strong>
                        </div>
                        <div class="brro-clb-report-summary">
                            Hoeveelheid geoogste compost: 
                            <strong><?php echo esc_html($cat_total_output_weight_formatted); ?>kg</strong>
                        </div>
                        
                        <?php
                        // Show locations within this category (always show location breakdown)
                        foreach ($category_data['locations'] as $loc_id => $loc_data) :
                            $loc_logs = $loc_data['logs'];
                            $loc_total_activities = count($loc_logs);
                            $loc_input_count = 0;
                            $loc_output_count = 0;
                            $loc_total_input_weight = 0;
                            $loc_total_output_weight = 0;
                            
                            foreach ($loc_logs as $log) {
                                if ($log['clb_log_activity'] === 'input') {
                                    $loc_input_count++;
                                    $loc_total_input_weight += floatval($log['clb_log_total_weight']);
                                } else {
                                    $loc_output_count++;
                                    $loc_total_output_weight += floatval($log['clb_log_total_weight']);
                                }
                            }
                            
                            $loc_unique_users = brro_clb_count_unique_users($loc_logs, $mapping['device_to_email']);
                            $loc_total_input_weight_formatted = number_format_i18n($loc_total_input_weight, 2);
                            $loc_total_output_weight_formatted = number_format_i18n($loc_total_output_weight, 2);
                            ?>
                            <div style="margin-top: 20px; margin-left: 20px;">
                                <strong><?php echo esc_html($loc_data['name']); ?></strong>
                                <div class="brro-clb-report-summary">
                                    Aantal logs: 
                                    <strong><?php echo esc_html($loc_total_activities); ?></strong>
                                    <small>(Groenafval toegevoegd: <strong><?php echo esc_html($loc_input_count); ?></strong>x | Compost geoogst: <strong><?php echo esc_html($loc_output_count); ?></strong>x)</small>
                                </div>
                                <div class="brro-clb-report-summary">
                                    Aantal unieke gebruikers: 
                                    <strong><?php echo esc_html($loc_unique_users); ?></strong>
                                </div>
                                <div class="brro-clb-report-summary">
                                    Hoeveelheid toegevoegde groenafval: 
                                    <strong><?php echo esc_html($loc_total_input_weight_formatted); ?>kg</strong>
                                </div>
                                <div class="brro-clb-report-summary">
                                    Hoeveelheid geoogste compost: 
                                    <strong><?php echo esc_html($loc_total_output_weight_formatted); ?>kg</strong>
                                </div>
                                <table class="report-logs-table">
                                    <thead>
                                        <tr>
                                            <th>Datum</th>
                                            <th>Activiteit</th>
                                            <th>Kilo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loc_data['logs'] as $log) : ?>
                                            <tr>
                                                <td><?php echo esc_html($log['clb_log_date']); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($log['clb_log_activity'] === 'input') {
                                                        echo 'Groenafval toegevoegd';
                                                    } else {
                                                        echo 'Compost geoogst';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo esc_html($log['clb_log_total_weight']); ?>kg</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach;
            }
            // If multiple locations (non-taxonomy) or single location, show a table for each location first
            elseif ($show_location_column && !empty($logs_by_location)) {
                // Use the already grouped and sorted logs_by_location array
                // (calculated earlier for the most active location summary)
                
                // Display a table for each location
                foreach ($logs_by_location as $loc_id => $location_data) : 
                    // Calculate statistics for this location
                    $loc_logs = $location_data['logs'];
                    $loc_total_activities = count($loc_logs);
                    $loc_input_count = 0;
                    $loc_output_count = 0;
                    $loc_total_input_weight = 0;
                    $loc_total_output_weight = 0;
                    
                    foreach ($loc_logs as $log) {
                        if ($log['clb_log_activity'] === 'input') {
                            $loc_input_count++;
                            $loc_total_input_weight += floatval($log['clb_log_total_weight']);
                        } else {
                            $loc_output_count++;
                            $loc_total_output_weight += floatval($log['clb_log_total_weight']);
                        }
                    }
                    
                    // Count unique users for this location using email-priority logic
                    $mapping = brro_clb_build_device_email_mapping();
                    $loc_unique_users = brro_clb_count_unique_users($loc_logs, $mapping['device_to_email']);
                    $loc_total_input_weight_formatted = number_format_i18n($loc_total_input_weight, 2);
                    $loc_total_output_weight_formatted = number_format_i18n($loc_total_output_weight, 2);
                    ?>
                    <div class="brro-clb-report-section brro-clb-report-complete-logs">
                        <?php
                        // Display the report header using the helper function
                        if (function_exists('brro_clb_get_report_header')) {
                            echo brro_clb_get_report_header();
                        }
                        ?>
                        <strong><?php echo esc_html($location_data['name']); ?></strong>
                        <div class="brro-clb-report-summary">
                            Aantal logs: 
                            <strong><?php echo esc_html($loc_total_activities); ?></strong>
                            <small>(Groenafval toegevoegd: <strong><?php echo esc_html($loc_input_count); ?></strong>x | Compost geoogst: <strong><?php echo esc_html($loc_output_count); ?></strong>x)</small>
                        </div>
                        <?php if (!$skip_to_display) : ?>
                        <div class="brro-clb-report-summary">
                            Aantal unieke gebruikers: 
                            <strong><?php echo esc_html($loc_unique_users); ?></strong>
                        </div>
                        <?php endif; ?>
                        <div class="brro-clb-report-summary">
                            Hoeveelheid toegevoegde groenafval: 
                            <strong><?php echo esc_html($loc_total_input_weight_formatted); ?>kg</strong>
                        </div>
                        <div class="brro-clb-report-summary">
                            Hoeveelheid geoogste compost: 
                            <strong><?php echo esc_html($loc_total_output_weight_formatted); ?>kg</strong>
                        </div>
                        <table class="report-logs-table">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Activiteit</th>
                                    <th>Kilo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($location_data['logs'] as $log) : ?>
                                    <tr>
                                        <td><?php echo esc_html($log['clb_log_date']); ?></td>
                                        <td>
                                            <?php 
                                            if ($log['clb_log_activity'] === 'input') {
                                                echo 'Groenafval toegevoegd';
                                            } else {
                                                echo 'Compost geoogst';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo esc_html($log['clb_log_total_weight']); ?>kg</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach;
            }
            ?>
            
            <div class="brro-clb-report-section brro-clb-report-complete-logs">
                <strong>Compleet Logboek:</strong>
                <table class="report-logs-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <?php if ($show_location_column) : ?>
                                <th>Locatie</th>
                            <?php endif; ?>
                            <th>Activiteit</th>
                            <th>Kilo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><?php echo esc_html($log['clb_log_date']); ?></td>
                                <?php if ($show_location_column) : ?>
                                    <td><?php echo esc_html($log['clb_log_location_name']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php 
                                    if ($log['clb_log_activity'] === 'input') {
                                        echo 'Groenafval toegevoegd';
                                    } else {
                                        echo 'Compost geoogst';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($log['clb_log_total_weight']); ?>kg</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}