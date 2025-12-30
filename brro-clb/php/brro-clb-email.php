<?php
/**
 * Email security functions - encryption, decryption, hashing, masking, and retrieval
 * ===============================================
 * This file contains functions for handling email addresses securely. Emails are
 * encrypted before saving to the database and can be decrypted when needed. These
 * functions are used by both logging and reports modules.
 * ===============================================
 * Index
 * - Email encryption (brro_clb_encrypt_email)
 * - Email decryption (brro_clb_decrypt_email)
 * - Email hashing (brro_clb_hash_email)
 * - Email masking (brro_clb_mask_email)
 * - Log retrieval by email (brro_clb_get_logs_by_email)
 */
if (!defined('ABSPATH')) exit;

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
    $table_name = brro_clb_get_logs_table_name();
    
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

