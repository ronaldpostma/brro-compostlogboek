<?php
/**
 * Reports display functions - frontend report rendering and HTML generation
 * ===============================================
 * This file contains the functions for generating frontend report HTML output.
 * Handles both report ID-based reports and email-based user reports. Groups
 * logs by location or taxonomy categories and formats the output for display.
 * ===============================================
 * Index
 * - Report header generation (brro_clb_get_report_header)
 * - Frontend report generation (brro_clb_get_rapport)
 */
if (!defined('ABSPATH')) exit;

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
        // Handle taxonomy filter (format: multiple taxonomies with terms array)
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

