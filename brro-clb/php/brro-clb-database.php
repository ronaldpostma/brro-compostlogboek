<?php
/** Database functions
 * ===============================================
 * 
 * For the logs, this plugin has its own database table 'brro_clb_logs' with the following columns:
 * - clb_log_id (number, auto-increment, first entry starts at 000001)
 * - clb_log_date (date, format YYYY-MM-DD)
 * - clb_log_time (time, format HH:MM)
 * - clb_log_location_id (number representing a post id for the location, from the form upon submit)
 * - clb_log_location_name (string, name of the location, from the form upon submit)
 * - clb_log_activity (string, taken from the form upon submit)
 * - clb_log_total_weight (number, from the form upon submit)
 * - clb_log_email (string, encrypted email of the user who submitted the log)
 *   NOTE: Email addresses are encrypted using AES-256-CBC before storage for privacy protection.
 *   Use brro_clb_decrypt_email() to decrypt when needed (e.g., for sending confirmation emails).
 * - clb_log_email_hash (string, SHA256 hash of normalized email address for efficient searching)
 *   NOTE: Hash is calculated from lowercased email before encryption, enabling fast indexed lookups
 *   while maintaining privacy. Used for email-based log searches.
 * - clb_log_device_id (string, device id of the user who submitted the log from local storage)
 * 
 * For the reports, this plugin has its own database table 'brro_clb_reports' with the following columns:
 * - clb_report_id (number, auto-increment, first entry starts at 1)
 * - clb_report_date_made (date, format YYYY-MM-DD representing the date the report was made i.e. when the form was submitted)
 * - clb_report_date_from (date, format YYYY-MM-DD from the form field 'From Date')
 * - clb_report_date_to (date, format YYYY-MM-DD from the form field 'To Date')
 * - clb_report_locations (text, JSON-encoded string: either 'all' or array of post IDs representing selected locations)
 *   NOTE: This field stores either the string 'all' or a JSON-encoded array of location post IDs.
 *   Use json_encode() when saving and json_decode() when reading.
 * - clb_report_taxonomy_terms (text, JSON-encoded string: contains taxonomy terms when filtering by taxonomy)
 *   NOTE: This field stores JSON in the format: {"terms": [{"taxonomy": "taxonomy_name", "term_id": 1}, ...]} 
 *   or empty/null when not used.
 * 
 * ===============================================
 */

// Database table version for tracking schema changes
// Increment this when making schema changes to either table
define('BRRO_CLB_DB_VERSION', '1.0.0');

/**
 * ===============================================
 * LOGS TABLE FUNCTIONS
 * ===============================================
 */

/**
 * Create or update the logs database table
 * Uses WordPress dbDelta() for safe table creation/updates
 * 
 * @return bool True on success, false on failure
 */
function brro_clb_create_log_table() {
    global $wpdb;
    
    // Get table name with WordPress prefix
    $table_name = $wpdb->prefix . 'brro_clb_logs';
    
    // Get charset and collation from WordPress database
    $charset_collate = $wpdb->get_charset_collate();
    
    // SQL statement for table creation
    // Note: dbDelta() is very particular about formatting - no trailing commas, proper spacing
    $sql = "CREATE TABLE $table_name (
        clb_log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        clb_log_date date NOT NULL,
        clb_log_time time NOT NULL,
        clb_log_location_id bigint(20) UNSIGNED NOT NULL,
        clb_log_location_name varchar(255) NOT NULL,
        clb_log_activity varchar(50) NOT NULL,
        clb_log_total_weight decimal(10,2) NOT NULL,
        clb_log_email varchar(255) DEFAULT NULL,
        clb_log_email_hash varchar(64) DEFAULT NULL,
        clb_log_device_id varchar(50) NOT NULL,
        PRIMARY KEY  (clb_log_id),
        KEY clb_log_date (clb_log_date),
        KEY clb_log_location_id (clb_log_location_id),
        KEY clb_log_device_id (clb_log_device_id),
        KEY clb_log_activity (clb_log_activity),
        KEY clb_log_email_hash (clb_log_email_hash)
    ) $charset_collate;";
    
    // Include WordPress upgrade functions
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Use dbDelta() to create or update table safely
    dbDelta($sql);
    
    // Check if table was created successfully
    $table_exists = brro_clb_logs_table_exists();
    
    if ($table_exists) {
        return true;
    }
    
    return false;
}

/**
 * Check if the logs table exists
 * 
 * @return bool True if table exists, false otherwise
 */
function brro_clb_logs_table_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'brro_clb_logs';
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
}

/**
 * Get the full logs table name with WordPress prefix
 * 
 * @return string Table name with prefix
 */
function brro_clb_get_logs_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'brro_clb_logs';
}

/**
 * Check if a column exists in the logs table
 * 
 * @param string $column_name Column name to check
 * @return bool True if column exists, false otherwise
 */
function brro_clb_logs_column_exists($column_name) {
    global $wpdb;
    $table_name = brro_clb_get_logs_table_name();
    
    if (!brro_clb_logs_table_exists()) {
        return false;
    }
    
    $column = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = %s 
        AND TABLE_NAME = %s 
        AND COLUMN_NAME = %s",
        DB_NAME,
        $table_name,
        $column_name
    ));
    
    return !empty($column);
}

/**
 * Ensure logs database table exists
 * Creates the table if it doesn't exist
 */
function brro_clb_ensure_logs_table_exists() {
    // Check if table exists
    if (!brro_clb_logs_table_exists()) {
        // Try to create it
        $created = brro_clb_create_log_table();
        if (!$created) {
            // Log error if creation fails
            error_log('Brro CLB: Failed to create logs database table');
        } else {
            // Store database version after successful creation
            update_option('brro_clb_db_version', BRRO_CLB_DB_VERSION);
        }
    }
}

/**
 * ===============================================
 * REPORTS TABLE FUNCTIONS
 * ===============================================
 */

/**
 * Create or update the reports database table
 * Uses WordPress dbDelta() for safe table creation/updates
 * 
 * @return bool True on success, false on failure
 */
function brro_clb_create_reports_table() {
    global $wpdb;
    
    // Get table name with WordPress prefix
    $table_name = $wpdb->prefix . 'brro_clb_reports';
    
    // Get charset and collation from WordPress database
    $charset_collate = $wpdb->get_charset_collate();
    
    // SQL statement for table creation
    // Note: dbDelta() is very particular about formatting - no trailing commas, proper spacing
    // clb_report_locations is stored as TEXT with JSON encoding (either 'all' or array of post IDs)
    // clb_report_taxonomy_terms is stored as TEXT with JSON encoding (taxonomy name and term IDs array)
    $sql = "CREATE TABLE $table_name (
        clb_report_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        clb_report_date_made date NOT NULL,
        clb_report_date_from date NOT NULL,
        clb_report_date_to date NOT NULL,
        clb_report_locations text NOT NULL,
        clb_report_taxonomy_terms text DEFAULT NULL,
        PRIMARY KEY  (clb_report_id),
        KEY clb_report_date_made (clb_report_date_made),
        KEY clb_report_date_from (clb_report_date_from),
        KEY clb_report_date_to (clb_report_date_to)
    ) $charset_collate;";
    
    // Include WordPress upgrade functions
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Use dbDelta() to create or update table safely
    dbDelta($sql);
    
    // Check if table was created successfully
    $table_exists = brro_clb_reports_table_exists();
    
    if ($table_exists) {
        return true;
    }
    
    return false;
}

/**
 * Check if the reports table exists
 * 
 * @return bool True if table exists, false otherwise
 */
function brro_clb_reports_table_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'brro_clb_reports';
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
}

/**
 * Get the full reports table name with WordPress prefix
 * 
 * @return string Table name with prefix
 */
function brro_clb_get_reports_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'brro_clb_reports';
}

/**
 * Check if a column exists in the reports table
 * 
 * @param string $column_name Column name to check
 * @return bool True if column exists, false otherwise
 */
function brro_clb_reports_column_exists($column_name) {
    global $wpdb;
    $table_name = brro_clb_get_reports_table_name();
    
    if (!brro_clb_reports_table_exists()) {
        return false;
    }
    
    $column = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = %s 
        AND TABLE_NAME = %s 
        AND COLUMN_NAME = %s",
        DB_NAME,
        $table_name,
        $column_name
    ));
    
    return !empty($column);
}

/**
 * Ensure reports database table exists
 * Creates the table if it doesn't exist
 */
function brro_clb_ensure_reports_table_exists() {
    // Check if table exists
    if (!brro_clb_reports_table_exists()) {
        // Try to create it
        $created = brro_clb_create_reports_table();
        if (!$created) {
            // Log error if creation fails
            error_log('Brro CLB: Failed to create reports database table');
        } else {
            // Store database version after successful creation
            update_option('brro_clb_db_version', BRRO_CLB_DB_VERSION);
        }
    }
}

/**
 * ===============================================
 * UNIFIED DATABASE MANAGEMENT
 * ===============================================
 */

/**
 * Ensure all database tables exist
 * This function checks and creates both logs and reports tables
 * Called on admin_init and plugin activation
 */
function brro_clb_ensure_all_tables_exist() {
    // Ensure logs table exists
    brro_clb_ensure_logs_table_exists();
    
    // Ensure reports table exists
    brro_clb_ensure_reports_table_exists();
}

/**
 * ===============================================
 * HOOKS AND INITIALIZATION
 * ===============================================
 */

// Run table check on admin init (safety measure)
// This ensures tables exist even if activation hook didn't run
add_action('admin_init', 'brro_clb_ensure_all_tables_exist');

// Run table creation on plugin activation
// Note: We need to register the hook with the main plugin file path
// Since this file is included from brro-clb.php, we use dirname to get the plugin directory
// If brro_clb_dir constant is available (defined in main plugin file), use it; otherwise use dirname
if (defined('brro_clb_dir')) {
    $main_plugin_file = brro_clb_dir . 'brro-clb.php';
} else {
    $main_plugin_file = dirname(dirname(__FILE__)) . '/brro-clb.php';
}
register_activation_hook($main_plugin_file, 'brro_clb_ensure_all_tables_exist');