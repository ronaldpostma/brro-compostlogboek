<?php
/**
 * Reports data functions - data retrieval, processing, and statistics calculation
 * ===============================================
 * This file contains the functions for retrieving report data from the database,
 * calculating statistics, building device-email mappings, and counting unique users.
 * These functions are used by both admin and frontend report generation.
 * ===============================================
 * Index
 * - Device-email mapping (brro_clb_build_device_email_mapping)
 * - Unique user counting (brro_clb_count_unique_users)
 * - Report data retrieval and statistics (brro_clb_get_report_data)
 */
if (!defined('ABSPATH')) exit;

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
            
            // Check for taxonomy filter (format: multiple taxonomies)
            if ($taxonomy_data && isset($taxonomy_data['terms']) && !empty($taxonomy_data['terms'])) {
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

