<?php
/**
 * Logging admin functions - admin page display
 * ===============================================
 * This file contains the function for displaying logs in the WordPress admin interface.
 * Shows all log entries in a table format with masked email addresses for privacy.
 * ===============================================
 * Index
 * - Admin logs page (brro_clb_logs_page)
 */
if (!defined('ABSPATH')) exit;

/**
 * Output the admin page content
 */
function brro_clb_logs_page() {
    global $wpdb;
    $table_name = brro_clb_get_logs_table_name();
    
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

