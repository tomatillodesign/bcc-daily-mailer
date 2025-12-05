<?php
/**
 * Plugin Name: BCC Daily Mailer
 * Plugin URI: https://mombaby.org
 * Description: Automated daily email reminders at 6:00 AM ET via Amazon SES with Gravity Forms integration
 * Version: 1.0.0
 * Author: CMIH
 * Author URI: https://med.unc.edu/cmih
 * Text Domain: bcc-daily-mailer
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('BCC_MAILER_VERSION', '1.0.0');
define('BCC_MAILER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BCC_MAILER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Enqueue Modal Trigger Script
 */
add_action('wp_enqueue_scripts', 'bcc_enqueue_modal_trigger');
function bcc_enqueue_modal_trigger() {
    wp_enqueue_script(
        'bcc-modal-trigger',
        BCC_MAILER_PLUGIN_URL . 'modal-trigger.js',
        [],
        BCC_MAILER_VERSION,
        true
    );
}

/**
 * Render Subscribe Modal in Footer (Yakstrap compatible)
 */
add_action('wp_footer', 'bcc_render_subscribe_modal');
function bcc_render_subscribe_modal() {
    $settings = get_option('bcc_email_settings', bcc_get_default_email_settings());
    $form_id = isset($settings['gf_form_id']) ? $settings['gf_form_id'] : '';
    
    if (empty($form_id)) {
        return; // No form configured
    }
    
    // Render yakstrap-compatible modal HTML
    ?>
    <div id="bccSubscribeModal" class="yak-modal modal fade" role="dialog" aria-modal="true" aria-hidden="true" style="display: none;">
        <div class="yak-modal-dialog modal-dialog" tabindex="-1">
            <div class="yak-modal-content modal-content">
                <div class="yak-modal-header modal-header">
                    <h2 class="yak-modal-title">Subscribe to Daily Email</h2>
                    <button type="button" class="yak-modal__close" aria-label="Close" data-bs-dismiss="modal">
                        <span aria-hidden="true">✕</span>
                    </button>
                </div>
                <div class="yak-modal-body modal-body">
                    <?php echo do_shortcode('[gravityform id="' . esc_attr($form_id) . '" title="true" description="true" ajax="true"]'); ?>
                </div>
                <div class="yak-modal-footer modal-footer">
                    <button type="button" class="button" data-bs-dismiss="modal">Close without submitting</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Plugin Activation
 */
register_activation_hook(__FILE__, 'bcc_mailer_activate');
function bcc_mailer_activate() {
    bcc_create_tables();
    bcc_migrate_settings(); // Ensure settings have all required keys
    bcc_restore_daily_cron();
    bcc_log('plugin_activated', null, 'success', ['version' => BCC_MAILER_VERSION]);
}

/**
 * Migrate/Update Settings on Activation
 * Ensures existing installations have all required settings keys
 */
function bcc_migrate_settings() {
    $settings = get_option('bcc_email_settings', []);
    $defaults = bcc_get_default_email_settings();
    
    // Merge with defaults (keeps existing values, adds missing keys)
    $updated_settings = array_merge($defaults, $settings);
    
    // Update if changed
    if ($settings !== $updated_settings) {
        update_option('bcc_email_settings', $updated_settings);
    }
}

/**
 * Plugin Deactivation
 */
register_deactivation_hook(__FILE__, 'bcc_mailer_deactivate');
function bcc_mailer_deactivate() {
    bcc_clear_all_crons();
    bcc_log('plugin_deactivated', null, 'info', ['version' => BCC_MAILER_VERSION]);
}

/**
 * Create Database Tables
 */
function bcc_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Subscribers table
    $subscribers_table = $wpdb->prefix . 'bcc_subscribers';
    $sql_subscribers = "CREATE TABLE IF NOT EXISTS $subscribers_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        first_name varchar(255) DEFAULT NULL,
        last_name varchar(255) DEFAULT NULL,
        phone varchar(50) DEFAULT NULL,
        organization varchar(255) DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        unsubscribe_token varchar(64) NOT NULL,
        source varchar(50) DEFAULT 'manual',
        source_id varchar(100) DEFAULT NULL,
        subscribe_date datetime NOT NULL,
        unsubscribe_date datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        UNIQUE KEY unsubscribe_token (unsubscribe_token),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // Logs table
    $logs_table = $wpdb->prefix . 'bcc_logs';
    $sql_logs = "CREATE TABLE IF NOT EXISTS $logs_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        timestamp datetime NOT NULL,
        action varchar(50) NOT NULL,
        email varchar(255) DEFAULT NULL,
        status varchar(20) NOT NULL,
        details longtext DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY timestamp (timestamp),
        KEY action (action),
        KEY email (email),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_subscribers);
    dbDelta($sql_logs);
    
    // Run database migrations
    bcc_migrate_database();
}

/**
 * Database Migrations - Add missing columns to existing tables
 */
function bcc_migrate_database() {
    global $wpdb;
    $subscribers_table = $wpdb->prefix . 'bcc_subscribers';
    
    // Simpler approach: just try to add columns, ignore errors if they exist
    $columns_to_add = [
        'first_name' => "ALTER TABLE $subscribers_table ADD COLUMN first_name varchar(255) DEFAULT NULL AFTER email",
        'last_name' => "ALTER TABLE $subscribers_table ADD COLUMN last_name varchar(255) DEFAULT NULL AFTER first_name",
        'phone' => "ALTER TABLE $subscribers_table ADD COLUMN phone varchar(50) DEFAULT NULL AFTER last_name",
        'organization' => "ALTER TABLE $subscribers_table ADD COLUMN organization varchar(255) DEFAULT NULL AFTER phone"
    ];
    
    $results = [];
    foreach ($columns_to_add as $column => $sql) {
        // Suppress errors (column might already exist)
        $wpdb->suppress_errors(true);
        $result = $wpdb->query($sql);
        $wpdb->suppress_errors(false);
        
        if ($result !== false) {
            $results[$column] = 'added';
        } else {
            // Check if column now exists (might have already existed)
            $check = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $subscribers_table,
                $column
            ));
            $results[$column] = $check > 0 ? 'exists' : 'failed';
        }
    }
    
    bcc_log('database_migration', null, 'info', $results);
    return $results;
}

/**
 * Add or Update Subscriber
 */
function bcc_add_subscriber($email, $source = 'manual', $source_id = null, $additional_data = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'bcc_subscribers';
    
    // Validate email
    if (!is_email($email)) {
        bcc_log('subscriber_add_failed', $email, 'error', ['reason' => 'Invalid email format', 'source' => $source]);
        return false;
    }
    
    // Check for existing subscriber
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE email = %s", $email));
    
    // Check which columns exist in the table
    global $wpdb;
    $table = $wpdb->prefix . 'bcc_subscribers';
    $columns = $wpdb->get_col("DESCRIBE $table", 0);
    
    // Prepare data - only include columns that exist
    $data = [
        'email' => $email,
        'updated_at' => current_time('mysql')
    ];
    $format = ['%s', '%s'];
    
    // Add optional columns if they exist in the table
    if (in_array('first_name', $columns) && isset($additional_data['first_name'])) {
        $data['first_name'] = sanitize_text_field($additional_data['first_name']);
        $format[] = '%s';
    }
    if (in_array('last_name', $columns) && isset($additional_data['last_name'])) {
        $data['last_name'] = sanitize_text_field($additional_data['last_name']);
        $format[] = '%s';
    }
    if (in_array('phone', $columns) && isset($additional_data['phone'])) {
        $data['phone'] = sanitize_text_field($additional_data['phone']);
        $format[] = '%s';
    }
    if (in_array('organization', $columns) && isset($additional_data['organization'])) {
        $data['organization'] = sanitize_text_field($additional_data['organization']);
        $format[] = '%s';
    }
    
    if ($existing) {
        // Update existing subscriber
        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $existing->id],
            $format,
            ['%d']
        );
        
        if ($result !== false) {
            bcc_log('subscriber_updated', $email, 'success', [
                'source' => $source,
                'source_id' => $source_id,
                'updated_fields' => array_keys(array_filter($additional_data))
            ]);
            return $existing->id;
        }
    } else {
        // Add new subscriber
        $data['status'] = 'active';
        $data['unsubscribe_token'] = wp_generate_password(32, false);
        $data['source'] = $source;
        $data['source_id'] = $source_id;
        $data['subscribe_date'] = current_time('mysql');
        $data['created_at'] = current_time('mysql');
        
        $format[] = '%s'; // status
        $format[] = '%s'; // token
        $format[] = '%s'; // source
        $format[] = '%s'; // source_id
        $format[] = '%s'; // subscribe_date
        $format[] = '%s'; // created_at
        
        $result = $wpdb->insert($table, $data, $format);
        
        if ($result) {
            bcc_log('subscriber_added', $email, 'success', [
                'source' => $source,
                'source_id' => $source_id,
                'token' => $data['unsubscribe_token'],
                'additional_data' => array_keys(array_filter($additional_data))
            ]);
            return $wpdb->insert_id;
        }
    }
    
    bcc_log('subscriber_add_failed', $email, 'error', ['reason' => $wpdb->last_error, 'source' => $source]);
    return false;
}

/**
 * Get All Active Subscribers
 */
function bcc_get_active_subscribers() {
    global $wpdb;
    $table = $wpdb->prefix . 'bcc_subscribers';
    return $wpdb->get_results("SELECT * FROM $table WHERE status = 'active' ORDER BY created_at ASC");
}

/**
 * Get All Subscribers (for admin display)
 */
function bcc_get_all_subscribers($search = '', $status_filter = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'bcc_subscribers';
    
    $where = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $where[] = 'email LIKE %s';
        $params[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    if (!empty($status_filter)) {
        $where[] = 'status = %s';
        $params[] = $status_filter;
    }
    
    $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
    
    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }
    
    $results = $wpdb->get_results($sql);
    
    // Always return an array, even if empty or error
    return is_array($results) ? $results : [];
}

/**
 * Delete Subscriber
 */
function bcc_delete_subscriber($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bcc_subscribers';
    
    $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if (!$subscriber) {
        return false;
    }
    
    $result = $wpdb->delete($table, ['id' => $id], ['%d']);
    
    if ($result) {
        bcc_log('subscriber_deleted', $subscriber->email, 'info', ['id' => $id]);
        return true;
    }
    
    return false;
}

/**
 * Unsubscribe by Token
 */
function bcc_unsubscribe_by_token($token) {
    global $wpdb;
    $table = $wpdb->prefix . 'bcc_subscribers';
    
    $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE unsubscribe_token = %s", $token));
    
    if (!$subscriber) {
        bcc_log('unsubscribe_failed', null, 'error', ['reason' => 'Invalid token', 'token' => $token]);
        return false;
    }
    
    if ($subscriber->status === 'unsubscribed') {
        bcc_log('unsubscribe_skipped', $subscriber->email, 'info', ['reason' => 'Already unsubscribed']);
        return $subscriber;
    }
    
    $result = $wpdb->update(
        $table,
        [
            'status' => 'unsubscribed',
            'unsubscribe_date' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ],
        ['id' => $subscriber->id],
        ['%s', '%s', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        bcc_log('unsubscribed', $subscriber->email, 'success', ['token' => $token]);
        return $subscriber;
    }
    
    return false;
}

/**
 * Send Email to Single Subscriber
 */
function bcc_send_email($subscriber, $is_test = false) {
    $settings = get_option('bcc_email_settings', bcc_get_default_email_settings());
    
    // Get email template
    $template = file_get_contents(BCC_MAILER_PLUGIN_DIR . 'email-template.html');
    
    // Generate unsubscribe URL
    $unsubscribe_url = home_url('/unsubscribe/?token=' . $subscriber->unsubscribe_token);
    
    // Replace tokens (escape HTML entities and convert line breaks)
    $replacements = [
        '{greeting}' => esc_html($settings['greeting']),
        '{body}' => nl2br(esc_html($settings['body'])),
        '{button1_text}' => esc_html($settings['button1_text']),
        '{button1_url}' => esc_url($settings['button1_url']),
        '{button2_text}' => esc_html($settings['button2_text']),
        '{button2_url}' => esc_url($settings['button2_url']),
        '{footer}' => esc_html($settings['footer']),
        '{unsubscribe_url}' => esc_url($unsubscribe_url)
    ];
    
    $message = str_replace(array_keys($replacements), array_values($replacements), $template);
    
    // Build From header with sender settings
    $from_name = isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');
    $from_email = isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');
    $reply_to = isset($settings['reply_to']) && !empty($settings['reply_to']) ? $settings['reply_to'] : $from_email;
    
    // Custom headers to override Offload SES Lite
    $headers = [
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $reply_to,
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    // Send email
    $sent = wp_mail($subscriber->email, $settings['subject'], $message, $headers);
    
    // Log result
    if ($sent) {
        bcc_log('email_sent', $subscriber->email, 'success', [
            'subject' => $settings['subject'],
            'is_test' => $is_test,
            'unsubscribe_url' => $unsubscribe_url
        ]);
    } else {
        global $phpmailer;
        $error = isset($phpmailer->ErrorInfo) ? $phpmailer->ErrorInfo : 'Unknown error';
        bcc_log('email_failed', $subscriber->email, 'error', [
            'error' => $error,
            'is_test' => $is_test
        ]);
    }
    
    return $sent;
}

/**
 * Send Batch to All Active Subscribers (called by cron)
 */
function bcc_send_batch() {
    // Check if today is a send day
    if (!bcc_is_send_day()) {
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        bcc_log('batch_send_skipped', null, 'info', [
            'reason' => 'Not a scheduled send day',
            'day' => $now->format('l')
        ]);
        return ['total' => 0, 'success' => 0, 'failed' => 0, 'skipped' => true];
    }
    
    bcc_log('batch_send_started', null, 'info', ['triggered_by' => 'cron']);
    
    $subscribers = bcc_get_active_subscribers();
    $total = count($subscribers);
    $success = 0;
    $failed = 0;
    
    foreach ($subscribers as $subscriber) {
        if (bcc_send_email($subscriber)) {
            $success++;
        } else {
            $failed++;
        }
        
        // Small delay to avoid rate limits
        usleep(100000); // 0.1 seconds
    }
    
    bcc_log('batch_send_completed', null, 'success', [
        'total' => $total,
        'success' => $success,
        'failed' => $failed
    ]);
    
    return ['total' => $total, 'success' => $success, 'failed' => $failed];
}

/**
 * Cron Callback
 */
add_action('bcc_daily_mailer_send_emails', 'bcc_send_batch');

/**
 * Schedule Daily Cron at Configured Time
 */
function bcc_restore_daily_cron() {
    // Clear all existing schedules first
    bcc_clear_all_crons();
    
    // Get settings
    $settings = get_option('bcc_email_settings', bcc_get_default_email_settings());
    $send_time = isset($settings['send_time']) ? $settings['send_time'] : '06:00';
    
    // Calculate next send time at configured hour
    $timezone = new DateTimeZone('America/New_York');
    $now = new DateTime('now', $timezone);
    $next_send = new DateTime('tomorrow ' . $send_time, $timezone);
    
    // If it's before send time today, schedule for today
    $send_today = new DateTime('today ' . $send_time, $timezone);
    if ($now < $send_today) {
        $next_send = $send_today;
    }
    
    // Convert to UTC timestamp for WordPress
    $next_send->setTimezone(new DateTimeZone('UTC'));
    $timestamp = $next_send->getTimestamp();
    
    // Schedule daily event
    wp_schedule_event($timestamp, 'daily', 'bcc_daily_mailer_send_emails');
    
    // Set mode to production
    update_option('bcc_mailer_mode', 'production');
    
    bcc_log('cron_scheduled', null, 'success', [
        'type' => 'daily',
        'next_run' => $next_send->format('Y-m-d H:i:s T'),
        'timestamp' => $timestamp,
        'send_time' => $send_time
    ]);
}

/**
 * Check if Today is a Send Day
 */
function bcc_is_send_day() {
    $settings = get_option('bcc_email_settings', bcc_get_default_email_settings());
    $send_days = isset($settings['send_days']) ? $settings['send_days'] : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    
    $timezone = new DateTimeZone('America/New_York');
    $now = new DateTime('now', $timezone);
    $today = strtolower($now->format('l')); // 'monday', 'tuesday', etc.
    
    return in_array($today, $send_days);
}

/**
 * Schedule Test Cron (X minutes from now)
 */
function bcc_schedule_test_cron($minutes = 2) {
    // Clear all existing schedules
    bcc_clear_all_crons();
    
    // Calculate timestamp
    $timestamp = time() + ($minutes * 60);
    
    // Schedule one-time event
    wp_schedule_single_event($timestamp, 'bcc_daily_mailer_send_emails');
    
    // Set mode to testing
    update_option('bcc_mailer_mode', 'testing');
    
    $timezone = wp_timezone();
    $scheduled_dt = new DateTime('@' . $timestamp);
    $scheduled_dt->setTimezone($timezone);
    
    bcc_log('test_cron_scheduled', null, 'info', [
        'minutes' => $minutes,
        'timestamp' => $timestamp,
        'scheduled_time' => $scheduled_dt->format('Y-m-d H:i:s T')
    ]);
    
    return $timestamp;
}

/**
 * Clear All Cron Schedules
 */
function bcc_clear_all_crons() {
    $timestamp = wp_next_scheduled('bcc_daily_mailer_send_emails');
    while ($timestamp) {
        wp_unschedule_event($timestamp, 'bcc_daily_mailer_send_emails');
        $timestamp = wp_next_scheduled('bcc_daily_mailer_send_emails');
    }
}

/**
 * Get Cron Info
 */
function bcc_get_cron_info() {
    $next_timestamp = wp_next_scheduled('bcc_daily_mailer_send_emails');
    $mode = get_option('bcc_mailer_mode', 'production');
    
    if (!$next_timestamp) {
        return [
            'registered' => false,
            'mode' => $mode,
            'next_run' => null,
            'next_run_timestamp' => null,
            'time_until' => null,
            'seconds_until' => null
        ];
    }
    
    // Convert to ET for display
    $timezone = new DateTimeZone('America/New_York');
    $next_run = new DateTime('@' . $next_timestamp);
    $next_run->setTimezone($timezone);
    
    $now = new DateTime('now', $timezone);
    $diff = $now->diff($next_run);
    
    // Calculate seconds until
    $seconds_until = $next_timestamp - time();
    
    // Format time until
    if ($diff->days > 0) {
        $time_until = sprintf('%d day%s %d hour%s', $diff->days, $diff->days > 1 ? 's' : '', $diff->h, $diff->h > 1 ? 's' : '');
    } elseif ($diff->h > 0) {
        $time_until = sprintf('%d hour%s %d minute%s', $diff->h, $diff->h > 1 ? 's' : '', $diff->i, $diff->i > 1 ? 's' : '');
    } elseif ($diff->i > 0) {
        $time_until = sprintf('%d minute%s %d second%s', $diff->i, $diff->i > 1 ? 's' : '', $diff->s, $diff->s > 1 ? 's' : '');
    } else {
        $time_until = sprintf('%d second%s', $diff->s, $diff->s > 1 ? 's' : '');
    }
    
    return [
        'registered' => true,
        'mode' => $mode,
        'next_run' => $next_run->format('l, F j, Y \a\t g:i A T'),
        'next_run_timestamp' => $next_timestamp,
        'time_until' => $time_until,
        'seconds_until' => $seconds_until
    ];
}

/**
 * Get Current Mode
 */
function bcc_get_mode() {
    return get_option('bcc_mailer_mode', 'production');
}

/**
 * Import CSV
 */
function bcc_import_csv($file_path) {
    if (!file_exists($file_path)) {
        return ['success' => false, 'error' => 'File not found'];
    }
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return ['success' => false, 'error' => 'Could not open file'];
    }
    
    $header = fgetcsv($handle);
    $email_column = array_search('email', array_map('strtolower', $header));
    
    if ($email_column === false) {
        fclose($handle);
        return ['success' => false, 'error' => 'No "email" column found in CSV'];
    }
    
    $added = 0;
    $skipped = 0;
    $errors = 0;
    
    while (($row = fgetcsv($handle)) !== false) {
        if (isset($row[$email_column])) {
            $email = trim($row[$email_column]);
            if (!empty($email)) {
                $result = bcc_add_subscriber($email, 'csv_import');
                if ($result) {
                    $added++;
                } else {
                    $skipped++;
                }
            }
        } else {
            $errors++;
        }
    }
    
    fclose($handle);
    
    bcc_log('csv_import_completed', null, 'success', [
        'added' => $added,
        'skipped' => $skipped,
        'errors' => $errors,
        'file' => basename($file_path)
    ]);
    
    return [
        'success' => true,
        'added' => $added,
        'skipped' => $skipped,
        'errors' => $errors
    ];
}

/**
 * Export CSV
 */
function bcc_export_csv() {
    $subscribers = bcc_get_all_subscribers();
    
    // Handle case where no subscribers or error
    if (!is_array($subscribers)) {
        $subscribers = [];
    }
    
    $timezone = wp_timezone();
    $now = new DateTime('now', $timezone);
    
    // Clean output buffer to prevent HTML/warnings in CSV
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bcc-subscribers-' . $now->format('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    fputcsv($output, ['Email', 'First Name', 'Last Name', 'Phone', 'Organization', 'Status', 'Source', 'Subscribe Date', 'Unsubscribe Date', 'Unsubscribe URL']);
    
    foreach ($subscribers as $subscriber) {
        $unsubscribe_url = home_url('/unsubscribe/?token=' . $subscriber->unsubscribe_token);
        
        fputcsv($output, [
            $subscriber->email,
            $subscriber->first_name ?? '',
            $subscriber->last_name ?? '',
            $subscriber->phone ?? '',
            $subscriber->organization ?? '',
            $subscriber->status,
            $subscriber->source,
            get_date_from_gmt($subscriber->subscribe_date, 'Y-m-d H:i:s'),
            $subscriber->unsubscribe_date ? get_date_from_gmt($subscriber->unsubscribe_date, 'Y-m-d H:i:s') : '',
            $unsubscribe_url
        ]);
    }
    
    fclose($output);
    
    // Log export (before exit)
    bcc_log('csv_exported', null, 'success', ['count' => count($subscribers)]);
    
    die();
}

/**
 * Logging Function
 */
function bcc_log($action, $email = null, $status = 'info', $details = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'bcc_logs';
    
    $wpdb->insert(
        $table,
        [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'email' => $email,
            'status' => $status,
            'details' => json_encode($details),
            'created_at' => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s']
    );
}

/**
 * Get Recent Logs
 */
function bcc_get_logs($limit = 100, $filter = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'bcc_logs';
    
    $where = '1=1';
    if (!empty($filter)) {
        $where = $wpdb->prepare('action LIKE %s OR status = %s', '%' . $wpdb->esc_like($filter) . '%', $filter);
    }
    
    return $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY timestamp DESC LIMIT $limit");
}

/**
 * Clear Old Logs
 */
function bcc_clear_old_logs($keep = 100) {
    global $wpdb;
    $table = $wpdb->prefix . 'bcc_logs';
    
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    
    if ($count > $keep) {
        $wpdb->query("DELETE FROM $table WHERE id NOT IN (SELECT id FROM (SELECT id FROM $table ORDER BY timestamp DESC LIMIT $keep) as t)");
        $deleted = $count - $keep;
        bcc_log('logs_cleared', null, 'info', ['deleted' => $deleted, 'kept' => $keep]);
        return $deleted;
    }
    
    return 0;
}

/**
 * Get Default Email Settings
 */
function bcc_get_default_email_settings() {
    return [
        'from_email' => get_option('admin_email'),
        'from_name' => get_bloginfo('name'),
        'reply_to' => get_option('admin_email'),
        'subject' => 'Daily Reminder: Submit Your Capacity Data',
        'greeting' => 'Hello,',
        'body' => 'This is your daily reminder to submit your hospital capacity data for today. Please click one of the buttons below to access the reporting system.',
        'button1_text' => 'Submit Capacity Data',
        'button1_url' => home_url(),
        'button2_text' => 'View Dashboard',
        'button2_url' => home_url(),
        'footer' => 'Questions? Contact us at cmih@med.unc.edu',
        'send_time' => '06:00',
        'send_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'gf_form_id' => '',
        'gf_email_field' => '',
        'gf_first_name_field' => '',
        'gf_last_name_field' => '',
        'gf_phone_field' => '',
        'gf_organization_field' => ''
    ];
}

/**
 * Gravity Forms Integration - Dynamic Form and Field Mapping
 */
add_action('gform_after_submission', 'bcc_handle_gravity_form_submission', 10, 2);
function bcc_handle_gravity_form_submission($entry, $form) {
    $settings = get_option('bcc_email_settings', bcc_get_default_email_settings());
    
    // Check if this is the configured form
    $configured_form_id = isset($settings['gf_form_id']) ? $settings['gf_form_id'] : '';
    if (empty($configured_form_id) || $form['id'] != $configured_form_id) {
        return; // Not the form we're monitoring
    }
    
    // Get field mappings
    $email_field = isset($settings['gf_email_field']) ? $settings['gf_email_field'] : '';
    $first_name_field = isset($settings['gf_first_name_field']) ? $settings['gf_first_name_field'] : '';
    $last_name_field = isset($settings['gf_last_name_field']) ? $settings['gf_last_name_field'] : '';
    $phone_field = isset($settings['gf_phone_field']) ? $settings['gf_phone_field'] : '';
    $organization_field = isset($settings['gf_organization_field']) ? $settings['gf_organization_field'] : '';
    
    // Extract email (required)
    $email = !empty($email_field) ? rgar($entry, $email_field) : '';
    
    if (empty($email) || !is_email($email)) {
        bcc_log('gravity_form_submission_failed', $email, 'error', [
            'reason' => 'Invalid or missing email',
            'entry_id' => $entry['id'],
            'form_id' => $form['id'],
            'email_field' => $email_field
        ]);
        return;
    }
    
    // Extract additional data
    $additional_data = [];
    
    if (!empty($first_name_field)) {
        $additional_data['first_name'] = rgar($entry, $first_name_field);
    }
    
    if (!empty($last_name_field)) {
        $additional_data['last_name'] = rgar($entry, $last_name_field);
    }
    
    if (!empty($phone_field)) {
        $additional_data['phone'] = rgar($entry, $phone_field);
    }
    
    if (!empty($organization_field)) {
        $additional_data['organization'] = rgar($entry, $organization_field);
    }
    
    // Add or update subscriber
    $result = bcc_add_subscriber($email, 'gravity_forms', $entry['id'], $additional_data);
    
    if ($result) {
        bcc_log('gravity_form_processed', $email, 'success', [
            'entry_id' => $entry['id'],
            'form_id' => $form['id'],
            'subscriber_id' => $result,
            'has_first_name' => !empty($additional_data['first_name']),
            'has_last_name' => !empty($additional_data['last_name']),
            'has_phone' => !empty($additional_data['phone']),
            'has_organization' => !empty($additional_data['organization'])
        ]);
    }
}

/**
 * Unsubscribe Handler
 */
add_action('template_redirect', 'bcc_handle_unsubscribe');
function bcc_handle_unsubscribe() {
    if (strpos($_SERVER['REQUEST_URI'], '/unsubscribe/') === false && !isset($_GET['token'])) {
        return;
    }
    
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    
    if (empty($token)) {
        wp_die('Invalid unsubscribe link. Please contact us if you need assistance.');
    }
    
    $subscriber = bcc_unsubscribe_by_token($token);
    
    if ($subscriber) {
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribed Successfully</title>
    <style>
        body { font-family: Arial, sans-serif; background: #eff0f1; padding: 40px 20px; text-align: center; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #13294b; margin-bottom: 20px; }
        p { color: #4a5568; line-height: 1.6; }
        .email { font-weight: bold; color: #4b9cd3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ Unsubscribed Successfully</h1>
        <p>The email address <span class="email">' . esc_html($subscriber->email) . '</span> has been removed from our mailing list.</p>
        <p>You will no longer receive daily reminder emails from NC Birth Capacity Connector.</p>
        <p style="margin-top: 30px; font-size: 14px; color: #718096;">If this was a mistake, please contact us at cmih@med.unc.edu</p>
    </div>
</body>
</html>';
        exit;
    } else {
        wp_die('Invalid or expired unsubscribe link. Please contact us at cmih@med.unc.edu if you need assistance.');
    }
}

/**
 * Admin Menu - Add to Settings (Administrator only)
 */
add_action('admin_menu', 'bcc_mailer_admin_menu');
function bcc_mailer_admin_menu() {
    add_options_page(
        'BCC Daily Mailer',
        'BCC Mailer',
        'manage_options', // Administrator capability
        'bcc-daily-mailer',
        'bcc_mailer_admin_page'
    );
}

/**
 * Admin Page
 */
function bcc_mailer_admin_page() {
    require_once BCC_MAILER_PLUGIN_DIR . 'admin-page.php';
}

/**
 * Admin Init - Migrate Settings
 */
add_action('admin_init', 'bcc_admin_init');
function bcc_admin_init() {
    // Ensure settings are up to date
    bcc_migrate_settings();
}

/**
 * Handle CSV Export (Administrator only)
 */
add_action('admin_post_bcc_export_csv', 'bcc_handle_export_csv');
function bcc_handle_export_csv() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Verify nonce
    check_admin_referer('bcc_export_csv');
    
    // Export CSV
    bcc_export_csv();
}

/**
 * Handle Admin Actions (Administrator only)
 */
add_action('admin_init', 'bcc_handle_admin_actions');
function bcc_handle_admin_actions() {
    if (!isset($_POST['bcc_action'])) {
        return;
    }
    
    // Require administrator capability
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    check_admin_referer('bcc_mailer_action');
    
    $action = sanitize_text_field($_POST['bcc_action']);
    
    switch ($action) {
        case 'send_test':
            $admin_email = get_option('admin_email');
            $subscriber = (object) [
                'email' => $admin_email,
                'unsubscribe_token' => 'TEST_TOKEN_NOT_VALID'
            ];
            bcc_send_email($subscriber, true);
            add_settings_error('bcc_mailer', 'test_sent', 'Test email sent to ' . $admin_email, 'success');
            break;
            
        case 'send_all':
            $result = bcc_send_batch();
            add_settings_error('bcc_mailer', 'batch_sent', sprintf('Sent to %d subscribers (%d succeeded, %d failed)', $result['total'], $result['success'], $result['failed']), 'success');
            break;
            
        case 'schedule_test_2':
            bcc_schedule_test_cron(2);
            add_settings_error('bcc_mailer', 'test_scheduled', 'Test send scheduled for 2 minutes from now', 'success');
            break;
            
        case 'schedule_test_5':
            bcc_schedule_test_cron(5);
            add_settings_error('bcc_mailer', 'test_scheduled', 'Test send scheduled for 5 minutes from now', 'success');
            break;
            
        case 'fire_cron_now':
            $result = bcc_send_batch();
            add_settings_error('bcc_mailer', 'cron_fired', sprintf('Cron executed manually: %d sent (%d succeeded, %d failed)', $result['total'], $result['success'], $result['failed']), 'success');
            break;
            
        case 'restore_daily':
            bcc_restore_daily_cron();
            add_settings_error('bcc_mailer', 'daily_restored', 'Daily schedule restored (6:00 AM ET)', 'success');
            break;
            
        case 'clear_crons':
            bcc_clear_all_crons();
            add_settings_error('bcc_mailer', 'crons_cleared', 'All cron schedules cleared', 'success');
            break;
            
        case 'clear_logs':
            $deleted = bcc_clear_old_logs(100);
            add_settings_error('bcc_mailer', 'logs_cleared', sprintf('Cleared %d old log entries', $deleted), 'success');
            break;
            
        case 'add_subscriber':
            $email = sanitize_email($_POST['subscriber_email']);
            if (bcc_add_subscriber($email, 'manual_admin')) {
                add_settings_error('bcc_mailer', 'subscriber_added', 'Subscriber added: ' . $email, 'success');
            } else {
                add_settings_error('bcc_mailer', 'subscriber_failed', 'Could not add subscriber (duplicate or invalid email)', 'error');
            }
            break;
            
        case 'delete_subscriber':
            $id = intval($_POST['subscriber_id']);
            if (bcc_delete_subscriber($id)) {
                add_settings_error('bcc_mailer', 'subscriber_deleted', 'Subscriber deleted', 'success');
            }
            break;
            
        case 'import_csv':
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $result = bcc_import_csv($_FILES['csv_file']['tmp_name']);
                if ($result['success']) {
                    add_settings_error('bcc_mailer', 'csv_imported', sprintf('CSV imported: %d added, %d skipped, %d errors', $result['added'], $result['skipped'], $result['errors']), 'success');
                } else {
                    add_settings_error('bcc_mailer', 'csv_failed', 'CSV import failed: ' . $result['error'], 'error');
                }
            }
            break;
            
        case 'save_template':
            $send_days = [];
            if (isset($_POST['send_days']) && is_array($_POST['send_days'])) {
                $send_days = array_map('sanitize_text_field', $_POST['send_days']);
            }
            
            // Sanitize body as textarea (preserves line breaks, allows special chars)
            $body = wp_unslash($_POST['body']);
            $body = sanitize_textarea_field($body);
            
            $settings = [
                'from_email' => sanitize_email($_POST['from_email']),
                'from_name' => sanitize_text_field($_POST['from_name']),
                'reply_to' => sanitize_email($_POST['reply_to']),
                'subject' => sanitize_text_field($_POST['subject']),
                'greeting' => sanitize_text_field($_POST['greeting']),
                'body' => $body,
                'button1_text' => sanitize_text_field($_POST['button1_text']),
                'button1_url' => esc_url_raw($_POST['button1_url']),
                'button2_text' => sanitize_text_field($_POST['button2_text']),
                'button2_url' => esc_url_raw($_POST['button2_url']),
                'footer' => sanitize_text_field(wp_unslash($_POST['footer'])),
                'send_time' => sanitize_text_field($_POST['send_time']),
                'send_days' => $send_days,
                'gf_form_id' => isset($_POST['gf_form_id']) ? sanitize_text_field($_POST['gf_form_id']) : '',
                'gf_email_field' => isset($_POST['gf_email_field']) ? sanitize_text_field($_POST['gf_email_field']) : '',
                'gf_first_name_field' => isset($_POST['gf_first_name_field']) ? sanitize_text_field($_POST['gf_first_name_field']) : '',
                'gf_last_name_field' => isset($_POST['gf_last_name_field']) ? sanitize_text_field($_POST['gf_last_name_field']) : '',
                'gf_phone_field' => isset($_POST['gf_phone_field']) ? sanitize_text_field($_POST['gf_phone_field']) : '',
                'gf_organization_field' => isset($_POST['gf_organization_field']) ? sanitize_text_field($_POST['gf_organization_field']) : ''
            ];
            update_option('bcc_email_settings', $settings);
            
            // Reschedule cron with new time if in production mode
            if (bcc_get_mode() === 'production') {
                bcc_restore_daily_cron();
            }
            
            bcc_log('template_saved', null, 'success', $settings);
            add_settings_error('bcc_mailer', 'template_saved', 'Settings saved and cron rescheduled', 'success');
            break;
            
        case 'migrate_database':
            bcc_migrate_database();
            add_settings_error('bcc_mailer', 'db_migrated', 'Database migration completed successfully', 'success');
            break;
    }
    
    set_transient('bcc_admin_notices', get_settings_errors('bcc_mailer'), 30);
    
    wp_redirect(admin_url('admin.php?page=bcc-daily-mailer'));
    exit;
}

