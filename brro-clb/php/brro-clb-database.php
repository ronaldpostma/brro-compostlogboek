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
 *   NOTE: This field stores JSON in the new format: {"terms": [{"taxonomy": "taxonomy_name", "term_id": 1}, ...]} 
 *   or the legacy format: {"taxonomy": "taxonomy_name", "term_ids": [1, 2, 3]} for backward compatibility, or empty/null when not used.
 * 
 * ===============================================
 */

// Database table version for tracking schema changes
// Increment this when making schema changes to either table
define('BRRO_CLB_DB_VERSION', '1.2.0');

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
 * Ensure logs database table exists and is up to date
 * Checks table existence and schema version, creates/updates as needed
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
    } else {
        // Table exists, check if we need to update schema
        $current_version = get_option('brro_clb_db_version', '0');
        if (version_compare($current_version, BRRO_CLB_DB_VERSION, '<')) {
            // Update table structure
            brro_clb_create_log_table();
            // Update version after successful migration
            update_option('brro_clb_db_version', BRRO_CLB_DB_VERSION);
        }
        
        // Always check if required columns exist (regardless of version, in case migration failed)
        // This ensures the column is added even if version check didn't trigger or failed
        $column_was_added = false;
        if (!brro_clb_logs_column_exists('clb_log_email_hash')) {
            global $wpdb;
            $table_name = brro_clb_get_logs_table_name();
            
            // Add column
            $alter_result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN clb_log_email_hash varchar(64) DEFAULT NULL");
            if ($alter_result === false) {
                error_log('Brro CLB: Failed to add clb_log_email_hash column - ' . $wpdb->last_error);
            } else {
                // Add index on the new column
                $index_result = $wpdb->query("ALTER TABLE $table_name ADD INDEX clb_log_email_hash (clb_log_email_hash)");
                if ($index_result === false) {
                    // Index might already exist, check for error
                    if (!empty($wpdb->last_error)) {
                        error_log('Brro CLB: Failed to add index on clb_log_email_hash column - ' . $wpdb->last_error);
                    }
                }
                // Column added successfully, ensure version is updated
                update_option('brro_clb_db_version', BRRO_CLB_DB_VERSION);
                $column_was_added = true;
            }
        }
        
        // Run migration to hash existing emails if column exists
        // Check if migration is needed (logs with email but no hash)
        if (brro_clb_logs_column_exists('clb_log_email_hash')) {
            brro_clb_migrate_email_hashes();
        }
    }
}

/**
 * Migrate existing email addresses to include hash values
 * 
 * Processes logs that have encrypted emails but no hash.
 * Decrypts the email, hashes it, and updates the row.
 * Processes in batches to avoid memory issues on large datasets.
 * 
 * @return bool True if migration completed successfully, false otherwise
 */
function brro_clb_migrate_email_hashes() {
    global $wpdb;
    $table_name = brro_clb_get_logs_table_name();
    
    // Check if there are any logs that need migration
    $needs_migration = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table_name WHERE clb_log_email IS NOT NULL AND clb_log_email_hash IS NULL"
    );
    
    if (!$needs_migration || $needs_migration == 0) {
        // No migration needed
        return true;
    }
    
    // Process in batches of 100 to avoid memory issues
    $batch_size = 100;
    $offset = 0;
    $processed = 0;
    $errors = 0;
    
    // Check if hash function exists (should be in brro-clb-logging.php)
    if (!function_exists('brro_clb_hash_email') || !function_exists('brro_clb_decrypt_email')) {
        error_log('Brro CLB: Required functions (brro_clb_hash_email or brro_clb_decrypt_email) not available for email hash migration');
        return false;
    }
    
    while (true) {
        // Fetch batch of logs that need migration
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT clb_log_id, clb_log_email FROM $table_name 
             WHERE clb_log_email IS NOT NULL AND clb_log_email_hash IS NULL 
             ORDER BY clb_log_id ASC 
             LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ), ARRAY_A);
        
        if (empty($logs)) {
            // No more logs to process
            break;
        }
        
        // Process each log in the batch
        foreach ($logs as $log) {
            $log_id = $log['clb_log_id'];
            $encrypted_email = $log['clb_log_email'];
            
            // Decrypt the email
            $decrypted_email = brro_clb_decrypt_email($encrypted_email);
            
            if ($decrypted_email === false || $decrypted_email === null) {
                // Decryption failed - skip this log
                $errors++;
                if (current_user_can('manage_options')) {
                    error_log("Brro CLB: Failed to decrypt email for log ID $log_id during hash migration");
                }
                continue;
            }
            
            // Hash the decrypted email
            $email_hash = brro_clb_hash_email($decrypted_email);
            
            if (!$email_hash) {
                // Hashing failed - skip this log
                $errors++;
                if (current_user_can('manage_options')) {
                    error_log("Brro CLB: Failed to hash email for log ID $log_id during hash migration");
                }
                continue;
            }
            
            // Update the log with the hash
            $update_result = $wpdb->update(
                $table_name,
                array('clb_log_email_hash' => $email_hash),
                array('clb_log_id' => $log_id),
                array('%s'),
                array('%d')
            );
            
            if ($update_result === false) {
                // Update failed
                $errors++;
                if (current_user_can('manage_options')) {
                    error_log("Brro CLB: Failed to update hash for log ID $log_id - " . $wpdb->last_error);
                }
            } else {
                $processed++;
            }
        }
        
        $offset += $batch_size;
        
        // Safety check to prevent infinite loops
        if ($offset > 100000) {
            error_log('Brro CLB: Email hash migration stopped at 100000 records to prevent infinite loop');
            break;
        }
    }
    
    // Log migration results for admin users
    if (current_user_can('manage_options')) {
        error_log("Brro CLB: Email hash migration completed - Processed: $processed, Errors: $errors, Total needing migration: $needs_migration");
    }
    
    return ($errors == 0);
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
 * Ensure reports database table exists and is up to date
 * Checks table existence and schema version, creates/updates as needed
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
    } else {
        // Table exists, check if we need to update schema
        $current_version = get_option('brro_clb_db_version', '0');
        if (version_compare($current_version, BRRO_CLB_DB_VERSION, '<')) {
            // Update table structure if schema changed
            brro_clb_create_reports_table();
            
            // Update version after successful migration
            update_option('brro_clb_db_version', BRRO_CLB_DB_VERSION);
        }
        
        // Always check if required columns exist (regardless of version, in case migration failed)
        // This ensures the column is added even if version check didn't trigger or failed
        if (!brro_clb_reports_column_exists('clb_report_taxonomy_terms')) {
            global $wpdb;
            $table_name = brro_clb_get_reports_table_name();
            $alter_result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN clb_report_taxonomy_terms text DEFAULT NULL");
            if ($alter_result === false) {
                error_log('Brro CLB: Failed to add clb_report_taxonomy_terms column - ' . $wpdb->last_error);
            } else {
                // Column added successfully, ensure version is updated
                update_option('brro_clb_db_version', BRRO_CLB_DB_VERSION);
            }
        }
    }
}

/**
 * ===============================================
 * UNIFIED DATABASE MANAGEMENT
 * ===============================================
 */

/**
 * Ensure all database tables exist and are up to date
 * This function checks and creates/updates both logs and reports tables
 * Called on admin_init and plugin activation
 */
function brro_clb_ensure_all_tables_exist() {
    // Ensure logs table exists and is up to date
    brro_clb_ensure_logs_table_exists();
    
    // Ensure reports table exists and is up to date
    brro_clb_ensure_reports_table_exists();
}

/**
 * ===============================================
 * BACKWARD COMPATIBILITY FUNCTIONS
 * ===============================================
 * These functions maintain backward compatibility with existing code
 * that may reference the old function names
 */

/**
 * Get the full table name with WordPress prefix (logs table)
 * Backward compatibility function - use brro_clb_get_logs_table_name() for clarity
 * 
 * @return string Table name with prefix
 */
function brro_clb_get_table_name() {
    return brro_clb_get_logs_table_name();
}

/**
 * Check if the logs table exists
 * Backward compatibility function - use brro_clb_logs_table_exists() for clarity
 * 
 * @return bool True if table exists, false otherwise
 */
function brro_clb_table_exists() {
    return brro_clb_logs_table_exists();
}

/**
 * Check if a column exists in the logs table
 * Backward compatibility function - use brro_clb_logs_column_exists() for clarity
 * 
 * @param string $column_name Column name to check
 * @return bool True if column exists, false otherwise
 */
function brro_clb_column_exists($column_name) {
    return brro_clb_logs_column_exists($column_name);
}

/**
 * Ensure database table exists on plugin activation
 * Backward compatibility function - use brro_clb_ensure_all_tables_exist() for clarity
 */
function brro_clb_ensure_table_exists() {
    brro_clb_ensure_all_tables_exist();
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