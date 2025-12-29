<?php
/**
 * Logging functions - database, wp-admin page, form submission, form generation
 * ===============================================
 * This file contains the functions for logging activities to the database,
 * displaying the logs in the wp-admin page, handling the form submission,
 * and generating the log form HTML.
 * ===============================================
 * Index
 * - Email Encryption Functions (encrypt, decrypt, hash, mask, get_logs_by_email)
 * - Admin page (brro_clb_logs_page)
 * - Form submission handling (brro_clb_handle_form_submission)
 * - Log form HTML generation (brro_clb_get_log_form)
 */









/**
 * Email Encryption Functions
 * ===============================================
 * These functions handle encryption, decryption, and masking of email addresses
 * for privacy protection. Emails are encrypted before saving to the database
 * and can be decrypted when needed (e.g., for sending confirmation emails).
 * ===============================================
 */

/**
 * Encrypt email address for database storage
 * 
 * Uses AES-256-CBC encryption with WordPress authentication salt as the key.
 * Each encryption uses a unique initialization vector (IV) for security.
 * The IV is prepended to the encrypted data for decryption.
 * 
 * @param string $email Email address to encrypt
 * @return string|false Encrypted email (base64 encoded) or false on failure
 */
function brro_clb_encrypt_email($email) {
    // Return null if email is empty (allows NULL in database)
    if (empty($email)) {
        return null;
    }
    
    // Check if OpenSSL is available
    if (!function_exists('openssl_encrypt')) {
        error_log('Brro CLB: OpenSSL not available for email encryption');
        return false;
    }
    
    // Use WordPress authentication salt as encryption key
    // This ensures the key is unique per WordPress installation
    $key = wp_salt('auth');
    
    // Generate a random initialization vector (IV) for this encryption
    // IV length depends on cipher method (AES-256-CBC requires 16 bytes)
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    $iv = openssl_random_pseudo_bytes($iv_length);
    
    // Encrypt the email using AES-256-CBC
    $encrypted = openssl_encrypt($email, 'AES-256-CBC', $key, 0, $iv);
    
    // Check if encryption was successful
    if ($encrypted === false) {
        error_log('Brro CLB: Email encryption failed');
        return false;
    }
    
    // Prepend IV to encrypted string and base64 encode for safe storage
    // IV is needed for decryption, so we store it with the encrypted data
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt email address from database
 * 
 * Extracts the IV from the stored data and uses it to decrypt the email.
 * Uses the same WordPress authentication salt as the encryption function.
 * 
 * @param string $encrypted_email Encrypted email string from database (base64 encoded)
 * @return string|false Decrypted email address or false on failure
 */
function brro_clb_decrypt_email($encrypted_email) {
    // Return null if encrypted email is empty
    if (empty($encrypted_email)) {
        return null;
    }
    
    // Check if OpenSSL is available
    if (!function_exists('openssl_decrypt')) {
        error_log('Brro CLB: OpenSSL not available for email decryption');
        return false;
    }
    
    // Use the same WordPress authentication salt as encryption
    $key = wp_salt('auth');
    
    // Decode from base64
    $data = base64_decode($encrypted_email);
    
    if ($data === false) {
        error_log('Brro CLB: Failed to decode encrypted email');
        return false;
    }
    
    // Extract IV and encrypted data
    // IV length is always 16 bytes for AES-256-CBC
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    
    // Decrypt using the extracted IV
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    
    if ($decrypted === false) {
        error_log('Brro CLB: Email decryption failed');
        return false;
    }
    
    return $decrypted;
}

/**
 * Hash email address for database searching
 * 
 * Creates a SHA256 hash of the normalized (lowercased) email address.
 * This enables efficient indexed database lookups while maintaining privacy.
 * The hash is deterministic - same email always produces same hash.
 * 
 * @param string $email Email address to hash
 * @return string|null SHA256 hash (64-character hex string) or null if email is empty/invalid
 */
function brro_clb_hash_email($email) {
    // Return null if email is empty
    if (empty($email)) {
        return null;
    }
    
    // Sanitize and normalize email (lowercase for case-insensitive matching)
    $sanitized_email = sanitize_email($email);
    if (!$sanitized_email) {
        return null;
    }
    
    // Normalize to lowercase for case-insensitive matching
    $normalized_email = strtolower($sanitized_email);
    
    // Hash using SHA256 (produces 64-character hex string)
    return hash('sha256', $normalized_email);
}

/**
 * Mask email address for privacy-safe display
 * 
 * Shows first 2 characters of local part, masks the rest.
 * Example: "john@example.com" becomes "jo****@example.com"
 * Domain part is always shown in full for identification purposes.
 * 
 * @param string $email Email address to mask
 * @return string Masked email address (e.g., "jo****@example.com") or "Anoniem" if invalid
 */
function brro_clb_mask_email($email) {
    // Return "Anoniem" if email is empty
    if (empty($email)) {
        return 'Anoniem';
    }
    
    // Split email into local part (before @) and domain part (after @)
    $parts = explode('@', $email);
    
    // Validate email format (should have exactly 2 parts)
    if (count($parts) !== 2) {
        return 'Anoniem';
    }
    
    $local = $parts[0];  // Part before @
    $domain = $parts[1]; // Part after @
    
    // Show first 2 characters of local part, mask the rest with asterisks
    // Minimum 2 asterisks for privacy, more if local part is longer
    $masked_local = substr($local, 0, 2) . str_repeat('*', max(2, strlen($local) - 2));
    
    // Return masked local part + @ + full domain
    return $masked_local . '@' . $domain;
}

/**
 * Find log entries by email address
 * 
 * Hashes the search email and queries the database using the indexed hash column.
 * This is much more efficient than decrypting all emails for comparison.
 * 
 * @param string $email Email address to search for
 * @return array Array of log entries matching the email, empty array if none found
 */
function brro_clb_get_logs_by_email($email) {
    global $wpdb;
    $table_name = brro_clb_get_table_name();
    
    // Hash the search email (function handles sanitization and normalization)
    $email_hash = brro_clb_hash_email($email);
    
    // If hashing failed (invalid email), return empty array
    if (!$email_hash) {
        return array();
    }
    
    // Query database for entries with matching email hash (uses indexed column)
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE clb_log_email_hash = %s ORDER BY clb_log_date DESC, clb_log_time DESC",
        $email_hash
    ), ARRAY_A);
    
    return $logs ? $logs : array();
}

// Output the admin page content
function brro_clb_logs_page() {
    global $wpdb;
    $table_name = brro_clb_get_table_name();
    
    // Get all log entries, ordered by most recent first
    $logs = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY clb_log_date DESC, clb_log_time DESC",
        ARRAY_A
    );
    
    ?>
    <div class="wrap">
        <h1>Compost Logboek</h1>
        
        <?php if (empty($logs)): ?>
            <p>Er zijn nog geen logboek entries.</p>
        <?php else: ?>
            <p>Totaal aantal entries: <strong><?php echo count($logs); ?></strong></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Datum</th>
                        <th>Tijd</th>
                        <th>Locatie</th>
                        <th>Activiteit</th>
                        <th>Gewicht (kg)</th>
                        <th>Email</th>
                        <th>Device ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(str_pad($log['clb_log_id'], 6, '0', STR_PAD_LEFT)); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($log['clb_log_date']))); ?></td>
                            <td><?php echo esc_html(substr($log['clb_log_time'], 0, 5)); ?></td>
                            <td>
                                <strong><?php echo esc_html($log['clb_log_location_name']); ?></strong><br>
                                <small>ID: <?php echo esc_html($log['clb_log_location_id']); ?></small>
                            </td>
                            <td>
                                <?php 
                                $activity_label = ($log['clb_log_activity'] === 'input') ? 'Groenafval toevoegen' : 'Compost oogsten';
                                echo esc_html($activity_label);
                                ?>
                            </td>
                            <td><?php echo esc_html(number_format_i18n($log['clb_log_total_weight'], 2)); ?></td>
                            <td>
                                <?php 
                                // Decrypt and mask email for privacy-safe display
                                if (!empty($log['clb_log_email'])) {
                                    $decrypted_email = brro_clb_decrypt_email($log['clb_log_email']);
                                    if ($decrypted_email) {
                                        // Display masked email (e.g., "jo****@example.com")
                                        echo esc_html(brro_clb_mask_email($decrypted_email));
                                    } else {
                                        // Decryption failed, show generic message
                                        echo '<em>Versleuteld</em>';
                                    }
                                } else {
                                    // No email provided
                                    echo '<em>Anoniem</em>';
                                }
                                ?>
                            </td>
                            <td><code><?php echo esc_html($log['clb_log_device_id']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Handle form submission and save to database
 * Hooked into 'init' to catch POST submissions
 */
function brro_clb_handle_form_submission() {
    // Only process if form was submitted
    if (!isset($_POST['compostlogboek_submit'])) {
        return;
    }
    
    global $wpdb;
    $table_name = brro_clb_get_table_name();
    
    // Validate required fields
    $required_fields = array(
        'clb_action',
        'clb_unit_type',
        'clb_total_weight',
        'clb_location_post_id',
        'clb_location_post_title',
        'clb_device_id'
    );
    
    $errors = array();
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = sprintf('Veld "%s" is verplicht.', $field);
        }
    }
    
    // If there are errors, store them and return
    if (!empty($errors)) {
        // Store errors in transient to display on page reload
        set_transient('brro_clb_form_errors', $errors, 30);
        return;
    }
    
    // Prepare email data (encrypted and hashed)
    $email_encrypted = null;
    $email_hash = null;
    $sanitized_email = null;
    if (!empty($_POST['clb_user_email'])) {
        $sanitized_email = sanitize_email($_POST['clb_user_email']);
        $email_encrypted = brro_clb_encrypt_email($sanitized_email);
        // Hash the email for efficient searching (hash before encryption, using plain email)
        $email_hash = brro_clb_hash_email($sanitized_email);
    }
    
    // Sanitize and prepare data
    $data = array(
        'clb_log_date' => current_time('Y-m-d'),
        'clb_log_time' => current_time('H:i:s'),
        'clb_log_location_id' => absint($_POST['clb_location_post_id']),
        'clb_log_location_name' => sanitize_text_field($_POST['clb_location_post_title']),
        'clb_log_activity' => sanitize_text_field($_POST['clb_action']),
        'clb_log_total_weight' => floatval($_POST['clb_total_weight']),
        // Encrypt email address before saving to database for privacy protection
        // If email is provided, sanitize it first, then encrypt it
        // If encryption fails, the email will be null (anonymous entry)
        'clb_log_email' => $email_encrypted,
        'clb_log_email_hash' => $email_hash,
        'clb_log_device_id' => sanitize_text_field($_POST['clb_device_id'])
    );
    
    // Insert into database
    $result = $wpdb->insert($table_name, $data, array(
        '%s', // date
        '%s', // time
        '%d', // location_id
        '%s', // location_name
        '%s', // activity
        '%f', // total_weight
        '%s', // email (nullable)
        '%s', // email_hash (nullable)
        '%s'  // device_id
    ));
    
    if ($result === false) {
        // Database error
        set_transient('brro_clb_form_errors', array('Er is een fout opgetreden bij het opslaan. Probeer het opnieuw.'), 30);
        error_log('Brro CLB: Database insert failed - ' . $wpdb->last_error);
    } else {
        // Success - store success message
        $log_id = $wpdb->insert_id;
        
        // Extract form data for dynamic message
        $action = isset($data['clb_log_activity']) ? $data['clb_log_activity'] : '';
        $unit_type = isset($_POST['clb_unit_type']) ? sanitize_text_field($_POST['clb_unit_type']) : '';
        $total_weight = floatval($_POST['clb_total_weight']);
        $unit_amount = isset($_POST['unit_amount']) ? floatval($_POST['unit_amount']) : 0;
        
        // Determine activity text
        if ($action === 'input') {
            $activity_text = 'groenafval toegevoegd';
        } else {
            $activity_text = 'compost geoogst';
        }
        
        // Determine amount and unit to display
        $display_amount = 0;
        $display_unit = 'kilo';
        
        if ($unit_type === 'kg') {
            // For kg, show total weight
            $display_amount = $total_weight;
            $display_unit = 'kilo';
        } elseif ($unit_type === 'liter') {
            // For liter, show unit_amount if available, otherwise fallback to total weight
            if ($unit_amount > 0) {
                $display_amount = $unit_amount;
                $display_unit = 'liter';
            } else {
                $display_amount = $total_weight;
                $display_unit = 'kilo';
            }
        } else {
            // For custom units, show total weight in kg
            $display_amount = $total_weight;
            $display_unit = 'kilo';
        }
        
        // Format the message
        $formatted_amount = number_format_i18n($display_amount, 1);
        $location_name = isset($data['clb_log_location_name']) ? $data['clb_log_location_name'] : '';
        $success_message = sprintf(
            'Bedankt voor het loggen van je activiteit: je hebt %s %s %s bij "%s".',
            $formatted_amount,
            $display_unit,
            $activity_text,
            $location_name
        );
        
        set_transient('brro_clb_form_success', $success_message, 30);
        
        // Store email address if provided (for success message link)
        if ($sanitized_email) {
            set_transient('brro_clb_form_success_email', $sanitized_email, 30);
        }
        
        // Redirect to prevent duplicate submissions on page refresh
        $redirect_url = remove_query_arg('submitted');
        $redirect_url = add_query_arg('submitted', 'success', $redirect_url);
        wp_safe_redirect($redirect_url);
        exit;
    }
}

// Hook into init to catch form submissions
add_action('init', 'brro_clb_handle_form_submission');

/**
 * Get the log form HTML (reusable function)
 * Used in custom templates
 * 
 * @param int|null $location_post_id Optional location post ID. If not provided,
 *   gets the ID from the current post (when used in templates)
 * @return string Form HTML
 */
function brro_clb_get_log_form($location_post_id = null) {
    // If location ID not provided, get it from current post (used in templates)
    if ($location_post_id === null) {
        if (is_singular()) {
            global $post;
            $location_post_id = $post->ID;
        } else {
            $location_post_id = 0;
        }
    } else {
        // Ensure it's an integer
        $location_post_id = absint($location_post_id);
    }
    
    $location_post_title = '';
    
    if ($location_post_id > 0) {
        // Check if using custom post type from settings
        $options = get_option('brro_clb_settings', array());
        $locations_choice = isset($options['brro_clb_locations_choice']) ? sanitize_text_field($options['brro_clb_locations_choice']) : 'clb-l';
        
        if ($locations_choice === 'own') {
            $post_type = isset($options['brro_clb_locations']) ? sanitize_text_field($options['brro_clb_locations']) : 'post';
        } else {
            $post_type = 'brro_clb_locations';
        }
        
        $location_post = get_post($location_post_id);
        if ($location_post && $location_post->post_type === $post_type && $location_post->post_status === 'publish') {
            $location_post_title = $location_post->post_title;
        } else {
            // Post doesn't exist, wrong post type, or isn't published - reset ID
            $location_post_id = 0;
        }
    }
    
    // Display success/error messages
    $success_message = get_transient('brro_clb_form_success');
    $success_email = get_transient('brro_clb_form_success_email');
    $error_messages = get_transient('brro_clb_form_errors');
    
    // Get settings for volume weights (needed in form)
    $options = get_option('brro_clb_settings', array());

    // Get maximum input amount for kilo and liter from settings (fallback to 100)
    $max_unit_amount = isset($options['brro_clb_max_unit_amount']) ? floatval($options['brro_clb_max_unit_amount']) : 100;
    if ($max_unit_amount <= 0) {
        $max_unit_amount = 100;
    }
    
    ob_start();
    ?>
    
    <?php if ($success_message): ?>
        <div class="notice notice-success" style="padding: 12px; margin-bottom: 20px; background-color: #d4edda; border-left: 4px solid #28a745; color: #155724;">
            <p style="margin: 0;"><strong>Gelukt!</strong> <?php echo esc_html($success_message); ?> <strong>Ga zo door!</strong></p>
            <?php if ($success_email): ?>
            <p id="total_log_message" style="margin: 0;">Benieuwd hoeveel je in totaal hebt gelogd tot nu toe? <a href="<?php echo esc_url(home_url('/?rapport=' . urlencode($success_email))); ?>" style="text-decoration: underline;color: #28a745;">Klik hier om je logboek geschiedenis te bekijken!</a></p>
            <?php endif; ?>
        </div>
        <?php 
        delete_transient('brro_clb_form_success');
        delete_transient('brro_clb_form_success_email');
        ?>
    <?php endif; ?>
    
    <?php if ($error_messages): ?>
        <div class="notice notice-error" style="padding: 12px; margin-bottom: 20px; background-color: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">
            <p style="margin: 0;"><strong>Fout:</strong></p>
            <ul style="margin: 8px 0 0 20px;">
                <?php foreach ($error_messages as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php delete_transient('brro_clb_form_errors'); ?>
    <?php endif; ?>
    
    <form id="compostlogboek_formulier" method="post" action="">
        <!-- Title of the form and location title -->
        <div class="clb-location-info">
            <span class="clb-location-id">Logboek locatie ID: <?php echo esc_html($location_post_id); ?></span>
            <h2><?php echo esc_html($location_post_title); ?></h2>
            <p class="clb-description">
                Volg het onderstaande formulier om je activiteit vast te leggen in het logboek voor "<?php echo esc_html($location_post_title); ?>".
            </p>
        </div>
        <!-- Action row -->
        <div class="clb-form-row clb-action">    
            <h3 class="clb-form-title">Welke actie wil je vastleggen?<span class="clb_tooltip_icon">?</span></h3>
            <div class="clb_tooltip">
                Kies of je groenafval toevoegt of compost oogst.
            </div>
            <input type="radio" id="input" name="clb_action" value="input" style="display: none;">
            <label class="clb-label-btn" for="input">
                <img src="<?php echo plugins_url('img/noun-trash-6869140.png', dirname(__FILE__)); ?>" alt="Groenafval toevoegen">
                Groenafval toevoegen
            </label>
            <input type="radio" id="output" name="clb_action" value="output" style="display: none;">
            <label class="clb-label-btn" for="output">
                <img src="<?php echo plugins_url('img/noun-compost-6898126.png', dirname(__FILE__)); ?>" alt="Compost oogsten">
                Compost oogsten
            </label>
        </div>
        <div id="clb-post-action-content" style="display: none;">
            <!-- Units row -->
            <div class="clb-form-row clb-units">    
                <h3 class="clb-form-title">Hoeveelheid:<span class="clb_tooltip_icon">?</span></h3>
                <div class="clb_tooltip">
                    Vul een aantal en eenheid in. Bijvoorbeeld: 2.5 kg, 1.5 liter, of een emmer, kruiwagen, bak etc...
                </div>
                <!-- number field, default val 1, steps of 0.25, with a text suffix of kg -->
                <span class="clb-unit-helper">Ik heb...</span>
                <div class="clb-unit-amount-buttons" style="display: flex; align-items: center; justify-content: center; width: 100%;">
                    <div id="unit_amount_decrease" class="button clb-unit-amount-button" role="button"1>-</div>
                    <input type="number" id="unit_amount" name="unit_amount" value="1" step="0.5" min="0.5" max="<?php echo esc_attr($max_unit_amount); ?>" style="color:black;width: 72px;pointer-events: none;padding:0;background-color:transparent;">
                    <div id="unit_amount_increase" class="button clb-unit-amount-button" role="button">+</div>
                </div>
                <div class="clb-unit-buttons clb-kilo-liter-buttons">
                    <!-- default units kg and liter -->
                    <input type="radio" id="unit_type_kg" name="clb_unit_type" value="kg" style="display: none;">
                    <label class="clb-label-btn" for="unit_type_kg">
                        <img src="<?php echo plugins_url('img/clb-unit-kg.png', dirname(__FILE__)); ?>" alt="Kg">
                        Kg
                    </label>
                    <?php
                    // get from settings brro_clb_input_volweight and brro_clb_output_volweight
                    $input_volweight = isset($options['brro_clb_input_volweight']) ? floatval($options['brro_clb_input_volweight']) : 0;
                    $output_volweight = isset($options['brro_clb_output_volweight']) ? floatval($options['brro_clb_output_volweight']) : 0;
                    ?>
                    <?php if ($input_volweight > 0 && $output_volweight > 0): ?>
                    <input type="radio" id="unit_type_liter" name="clb_unit_type" value="liter" data-input-weight="<?php echo esc_attr($input_volweight); ?>" data-output-weight="<?php echo esc_attr($output_volweight); ?>" style="display: none;">
                    <label class="clb-label-btn" for="unit_type_liter">
                        <img src="<?php echo plugins_url('img/clb-unit-l.png', dirname(__FILE__)); ?>" alt="Liter">
                        Liter
                    </label>
                </div>
                <div class="clb-unit-buttons clb-custom-buttons">
                    <span class="clb-unit-helper">...of een:</span>
                    <?php endif; ?>
                    <!-- custom units from settings -->
                    <?php
                    // Settings already loaded above, no need to reload
                    
                    // Input units (shown when action is "input")
                    for ($i = 1; $i <= 4; $i++) {
                        $title_key = 'brro_clb_inputunit_' . $i . '_title';
                        $icon_key  = 'brro_clb_inputunit_' . $i . '_icon';
                        $weight_key = 'brro_clb_inputunit_' . $i . '_weight';

                        $title = isset($options[$title_key]) ? trim($options[$title_key]) : '';
                        $icon  = isset($options[$icon_key]) ? trim($options[$icon_key]) : '';
                        $weight = isset($options[$weight_key]) ? floatval($options[$weight_key]) : 0;

                        // Only show if both title and icon have values
                        if (!empty($title) && !empty($icon)) {
                            $unit_id = 'unit_type_inputunit_' . $i;
                            $unit_value = 'inputunit_' . $i;
                            ?>
                            <input type="radio" id="<?php echo esc_attr($unit_id); ?>" name="clb_unit_type" value="<?php echo esc_attr($unit_value); ?>" data-weight="<?php echo esc_attr($weight); ?>" style="display: none;">
                            <label class="clb-label-btn input-only" for="<?php echo esc_attr($unit_id); ?>" style="display: none;">
                                <img src="<?php echo esc_url($icon); ?>" alt="<?php echo esc_attr($title); ?>">
                                <?php echo esc_html($title); ?>
                            </label>
                            <?php
                        }
                    }
                    
                    // Output units (shown when action is "output")
                    for ($i = 1; $i <= 4; $i++) {
                        $title_key = 'brro_clb_outputunit_' . $i . '_title';
                        $icon_key  = 'brro_clb_outputunit_' . $i . '_icon';
                        $weight_key = 'brro_clb_outputunit_' . $i . '_weight';
                        $title = isset($options[$title_key]) ? trim($options[$title_key]) : '';
                        $icon  = isset($options[$icon_key]) ? trim($options[$icon_key]) : '';
                        $weight = isset($options[$weight_key]) ? floatval($options[$weight_key]) : 0;
                        
                        // Only show if both title and icon have values
                        if (!empty($title) && !empty($icon)) {
                            $unit_id = 'unit_type_outputunit_' . $i;
                            $unit_value = 'outputunit_' . $i;
                            ?>
                            <input type="radio" id="<?php echo esc_attr($unit_id); ?>" name="clb_unit_type" value="<?php echo esc_attr($unit_value); ?>" data-weight="<?php echo esc_attr($weight); ?>" style="display: none;">
                            <label class="clb-label-btn output-only" for="<?php echo esc_attr($unit_id); ?>" style="display: none;">
                                <img src="<?php echo esc_url($icon); ?>" alt="<?php echo esc_attr($title); ?>">
                                <?php echo esc_html($title); ?>
                            </label>
                            <?php
                        }
                    }
                    ?>
                </div>
                <span class="clb-unit-helper">...<span class="input-only" style="display: none;"> groenafval toegevoegd.</span> <span class="output-only" style="display: none;"> compost geoogst.</span></span>
            </div>

            <!-- User email row -->
            <div class="clb-form-row clb-user-email">
                <div class="clb-form-title">Je emailadres<span class="clb_tooltip_icon">?</span></div>
                <div class="clb_tooltip">
                    <b>Optioneel:</b> Je kunt een email ontvangen met een bevestiging van je logboek activiteit, en je kunt je eigen logboek later opnieuw bekijken. Anoniem loggen is ook mogelijk.
                </div>
                <input type="email" id="clb_user_email" name="clb_user_email" value="">
                <!-- check to save the email address to a cookie or local storage -->
                <div style="width:100%">
                    <input type="checkbox" id="clb_user_email_save" name="clb_user_email_save" value="1">
                    <label for="clb_user_email_save">Onthoud mijn emailadres voor de volgende keer</label>
                </div>
            </div>

            <!-- Rows filled in by jQuery, not editable by the user -->
            <div class="clb-form-row clb-hidden">
                <div class="clb-form-title">Totaal gewicht</div>
                <input type="text" id="clb_total_weight" name="clb_total_weight" value="">
            </div>
            <div class="clb-form-row clb-hidden">
                <div class="clb-form-title">Device ID</div>
                <input type="text" id="clb_device_id" name="clb_device_id" value="">
            </div>
            <div class="clb-form-row clb-hidden">
                <div class="clb-form-title">Compost location post ID</div>
                <input type="text" id="clb_location_post_id" name="clb_location_post_id" value="<?php echo esc_attr($location_post_id); ?>">
            </div>
            <div class="clb-form-row clb-hidden">
                <div class="clb-form-title">Compost location post title</div>
                <input type="text" id="clb_location_post_title" name="clb_location_post_title" value="<?php echo esc_attr($location_post_title); ?>">
            </div>



            <p class="clb-description clb_privacy_policy">
                Door dit formulier te gebruiken, ga je akkoord met de <span class="clb_privacy_policy_trigger" style="font-weight: bold;text-decoration: underline;cursor:pointer;">verwerking van deze gegevens</span>.
            </p>
            <div class="clb_privacy_policy_content" style="display:none;">
                <ul>
                    <li>Je activiteit wordt vastgelegd in het logboek voor deze locatie.</li>
                    <li>Rapportages van het logboek worden gemaakt op basis van deze data en kunnen gedeeld worden met derden.</li>
                    <li>Je emailadres wordt beveiligd (versleuteld en onleesbaar) opgeslagen en wordt enkel gebruikt om je een bevestiging te sturen en zodat je je eigen logboek later opnieuw te kunt bekijken.</li>
                    <li>Je emailadres wordt nooit gedeeld met derden.</li>
                    <li>Er wordt een willekeurige sleutel gegenereerd om je als unieke gebruiker te kunnen herkennen, ook als je je emailadres niet wilt gebruiken. Dit is volledig anoniem.</li>
                </ul>
            </div>

            <input type="submit" name="compostlogboek_submit" value="Activiteit vastleggen" disabled>
        </div>
    </form>
    <?php
    return ob_get_clean();
}