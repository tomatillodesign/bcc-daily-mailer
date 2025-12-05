<?php
/**
 * BCC Daily Mailer - Admin Page
 * Single comprehensive dashboard with all functionality
 * Requires Administrator capability
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions (Administrator only)
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Get data
$cron_info = bcc_get_cron_info();
$subscribers = bcc_get_all_subscribers();
$active_count = count(array_filter($subscribers, function($s) { return $s->status === 'active'; }));
$unsubscribed_count = count(array_filter($subscribers, function($s) { return $s->status === 'unsubscribed'; }));
$logs = bcc_get_logs(100);
$settings = get_option('bcc_email_settings', bcc_get_default_email_settings());

// Get last batch send info
$last_batch = array_filter($logs, function($log) { return $log->action === 'batch_send_completed'; });
$last_batch = !empty($last_batch) ? reset($last_batch) : null;

// Display admin notices
$notices = get_transient('bcc_admin_notices');
if ($notices) {
    delete_transient('bcc_admin_notices');
    foreach ($notices as $notice) {
        echo '<div class="notice notice-' . $notice['type'] . ' is-dismissible"><p>' . $notice['message'] . '</p></div>';
    }
}
?>

<div class="wrap">
    <h1>🔔 BCC Daily Mailer</h1>
    <?php 
    $send_time_display = isset($settings['send_time']) ? date('g:i A', strtotime($settings['send_time'])) : '6:00 AM';
    $from_name = isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');
    $from_email = isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');
    ?>
    <p class="description">Automated daily email reminders at <?php echo esc_html($send_time_display); ?> ET from <?php echo esc_html($from_name); ?> (<?php echo esc_html($from_email); ?>) via Amazon SES</p>
    
    <style>
        .bcc-section { background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .bcc-section h2 { margin-top: 0; border-bottom: 2px solid #13294b; padding-bottom: 10px; color: #13294b; }
        .bcc-mode-badge { display: inline-block; padding: 8px 16px; border-radius: 4px; font-weight: bold; font-size: 14px; margin-bottom: 15px; }
        .bcc-mode-production { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .bcc-mode-testing { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .bcc-countdown { font-size: 24px; font-weight: bold; color: #13294b; margin: 10px 0; }
        .bcc-stat { display: inline-block; margin: 10px 20px 10px 0; padding: 15px; background: #eff0f1; border-radius: 4px; border: 1px solid #d0d1d2; }
        .bcc-stat-label { font-size: 12px; color: #646970; text-transform: uppercase; }
        .bcc-stat-value { font-size: 24px; font-weight: bold; color: #13294b; }
        .bcc-button-group { margin: 15px 0; }
        .bcc-button-group .button { margin-right: 10px; margin-bottom: 10px; }
        .bcc-button-group .button-primary { background: #13294b; border-color: #13294b; }
        .bcc-button-group .button-primary:hover { background: #0d1a30; border-color: #0d1a30; }
        .bcc-button-group .button-secondary { color: #13294b; border-color: #13294b; }
        .bcc-button-group .button-secondary:hover { color: #0d1a30; border-color: #0d1a30; }
        .bcc-warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; }
        .bcc-info { background: #e3f2fd; border-left: 4px solid #4b9cd3; padding: 12px; margin: 15px 0; }
        .bcc-subscribers-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .bcc-subscribers-table th { background: #eff0f1; padding: 10px; text-align: left; border-bottom: 2px solid #13294b; color: #13294b; }
        .bcc-subscribers-table td { padding: 10px; border-bottom: 1px solid #c3c4c7; }
        .bcc-status-active { color: #00a32a; font-weight: bold; }
        .bcc-status-unsubscribed { color: #646970; }
        .bcc-logs-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
        .bcc-logs-table th { background: #eff0f1; padding: 8px; text-align: left; border-bottom: 2px solid #13294b; color: #13294b; }
        .bcc-logs-table td { padding: 8px; border-bottom: 1px solid #e0e0e0; }
        .bcc-log-success { background: #d4edda; }
        .bcc-log-error { background: #f8d7da; }
        .bcc-log-info { background: #e3f2fd; }
        .bcc-log-details { font-size: 11px; color: #646970; margin-top: 5px; max-width: 400px; overflow: hidden; text-overflow: ellipsis; }
        .bcc-debug { background: #eff0f1; padding: 15px; border: 1px solid #d0d1d2; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto; max-height: 500px; }
        .bcc-form-table { width: 100%; }
        .bcc-form-table th { width: 200px; text-align: left; padding: 10px 10px 10px 0; vertical-align: top; }
        .bcc-form-table td { padding: 10px 0; }
        .bcc-form-table input[type="text"], .bcc-form-table input[type="url"], .bcc-form-table input[type="time"], .bcc-form-table textarea { width: 100%; max-width: 600px; }
        .bcc-form-table textarea { min-height: 100px; }
        .bcc-days-checkboxes { display: flex; gap: 15px; flex-wrap: wrap; }
        .bcc-days-checkboxes label { display: flex; align-items: center; gap: 5px; }
    </style>
    
    <!-- Section 1: System Status -->
    <div class="bcc-section">
        <h2>📊 System Status</h2>
        
        <?php if ($cron_info['mode'] === 'testing'): ?>
            <div class="bcc-mode-badge bcc-mode-testing">🔴 TESTING MODE</div>
            <div class="bcc-warning">
                <strong>⚠️ Testing Mode Active</strong> - Production daily schedule is disabled. Remember to restore normal schedule when done testing.
            </div>
        <?php else: ?>
            <div class="bcc-mode-badge bcc-mode-production">🟢 PRODUCTION MODE</div>
        <?php endif; ?>
        
        <?php if ($cron_info['registered']): ?>
            <div class="bcc-countdown" id="bcc-countdown">
                Next send: <?php echo $cron_info['next_run']; ?> (in <span id="bcc-time-remaining"><?php echo $cron_info['time_until']; ?></span>)
            </div>
            
            <script>
                // Countdown timer
                let secondsRemaining = <?php echo $cron_info['seconds_until']; ?>;
                
                function updateCountdown() {
                    if (secondsRemaining <= 0) {
                        document.getElementById('bcc-time-remaining').textContent = 'any moment now...';
                        return;
                    }
                    
                    const days = Math.floor(secondsRemaining / 86400);
                    const hours = Math.floor((secondsRemaining % 86400) / 3600);
                    const minutes = Math.floor((secondsRemaining % 3600) / 60);
                    const seconds = secondsRemaining % 60;
                    
                    let display = '';
                    if (days > 0) display += days + ' day' + (days > 1 ? 's' : '') + ' ';
                    if (hours > 0) display += hours + ' hour' + (hours > 1 ? 's' : '') + ' ';
                    if (minutes > 0) display += minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ';
                    if (days === 0 && hours === 0) display += seconds + ' second' + (seconds > 1 ? 's' : '');
                    
                    document.getElementById('bcc-time-remaining').textContent = display.trim();
                    secondsRemaining--;
                }
                
                setInterval(updateCountdown, 1000);
            </script>
        <?php else: ?>
            <div class="bcc-warning">
                <strong>⚠️ No Cron Scheduled</strong> - Click "Restore Normal Daily Schedule" to set up automated sends.
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <div class="bcc-stat">
                <div class="bcc-stat-label">Active Subscribers</div>
                <div class="bcc-stat-value"><?php echo $active_count; ?></div>
            </div>
            <div class="bcc-stat">
                <div class="bcc-stat-label">Unsubscribed</div>
                <div class="bcc-stat-value"><?php echo $unsubscribed_count; ?></div>
            </div>
            <div class="bcc-stat">
                <div class="bcc-stat-label">Total Subscribers</div>
                <div class="bcc-stat-value"><?php echo count($subscribers); ?></div>
            </div>
        </div>
        
        <?php if ($last_batch): ?>
            <?php $batch_details = json_decode($last_batch->details, true); ?>
            <div class="bcc-info" style="margin-top: 15px;">
                <strong>Last Batch Send:</strong> <?php echo get_date_from_gmt($last_batch->timestamp, 'F j, Y \a\t g:i A T'); ?><br>
                Sent to <?php echo $batch_details['total']; ?> subscribers 
                (<?php echo $batch_details['success']; ?> succeeded, <?php echo $batch_details['failed']; ?> failed)
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 15px; padding: 10px; background: #eff0f1; border-radius: 4px; border: 1px solid #d0d1d2;">
            <strong style="color: #13294b;">Configuration:</strong><br>
            From: NC Birth Capacity Connector &lt;mombabycmih@gmail.com&gt;<br>
            Timezone: America/New_York (Eastern Time)<br>
            Send Time: <?php echo esc_html(isset($settings['send_time']) ? $settings['send_time'] : '06:00'); ?> ET<br>
            Send Days: <?php 
                $selected_days = isset($settings['send_days']) ? $settings['send_days'] : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                $day_labels = ['monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun'];
                $display_days = array_map(function($day) use ($day_labels) { return isset($day_labels[$day]) ? $day_labels[$day] : $day; }, $selected_days);
                echo esc_html(implode(', ', $display_days));
            ?><br>
            Gravity Form: <?php 
                $gf_form_id = isset($settings['gf_form_id']) && !empty($settings['gf_form_id']) ? $settings['gf_form_id'] : 'Not configured';
                echo esc_html($gf_form_id);
            ?><br>
            Cron Hook: bcc_daily_mailer_send_emails
        </div>
        
        <div class="bcc-info" style="margin-top: 15px;">
            <strong>📌 Local WP Note:</strong> You're on a .local site, so WP-Cron is triggered by page loads (not alternate cron). 
            After scheduling a test send, <strong>reload this page</strong> or visit any page on your site when the countdown reaches zero to trigger the cron.
        </div>
    </div>
    
    <!-- Section 2: Testing Controls -->
    <div class="bcc-section">
        <h2>🧪 Testing Controls</h2>
        <p class="description">Schedule test sends to verify functionality without waiting until 6:00 AM</p>
        
        <div class="bcc-button-group">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="schedule_test_2">
                <button type="submit" class="button button-secondary">⏱️ Schedule Test Send in 2 Minutes</button>
            </form>
            
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="schedule_test_5">
                <button type="submit" class="button button-secondary">⏱️ Schedule Test Send in 5 Minutes</button>
            </form>
            
            <form method="post" style="display: inline;" onsubmit="return confirm('This will send emails to all active subscribers immediately. Continue?');">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="fire_cron_now">
                <button type="submit" class="button button-secondary">🚀 Manually Fire Cron Now</button>
            </form>
        </div>
        
        <div class="bcc-button-group">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="restore_daily">
                <?php 
                $send_time = isset($settings['send_time']) ? $settings['send_time'] : '06:00';
                $send_time_display = date('g:i A', strtotime($send_time));
                ?>
                <button type="submit" class="button button-primary">🟢 Restore Normal Daily Schedule (<?php echo esc_html($send_time_display); ?> ET)</button>
            </form>
            
            <form method="post" style="display: inline;" onsubmit="return confirm('This will clear all scheduled sends. Continue?');">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="clear_crons">
                <button type="submit" class="button button-secondary">🗑️ Clear All Cron Schedules</button>
            </form>
        </div>
        
        <div class="bcc-info" style="margin-top: 15px;">
            <strong>Next scheduled send will email:</strong> <?php echo $active_count; ?> active subscriber<?php echo $active_count !== 1 ? 's' : ''; ?>
        </div>
    </div>
    
    <!-- Section 3: Quick Actions -->
    <div class="bcc-section">
        <h2>⚡ Quick Actions</h2>
        
        <div class="bcc-button-group">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="send_test">
                <button type="submit" class="button button-secondary">📧 Send Test Email to Me</button>
            </form>
            
            <form method="post" style="display: inline;" onsubmit="return confirm('Send to all <?php echo $active_count; ?> active subscribers now?');">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="send_all">
                <button type="submit" class="button button-secondary">📤 Send to All Active Subscribers Now</button>
            </form>
            
            <form method="post" style="display: inline;" onsubmit="return confirm('Clear old logs (keep last 100)?');">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="clear_logs">
                <button type="submit" class="button button-secondary">🗑️ Clear Old Logs</button>
            </form>
        </div>
    </div>
    
    <!-- Section 4: Subscribers Management -->
    <div class="bcc-section">
        <h2>👥 Subscribers Management</h2>
        
        <div style="margin-bottom: 20px;">
            <h3>Add Subscriber</h3>
            <form method="post" style="display: flex; gap: 10px; align-items: center;">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="add_subscriber">
                <input type="email" name="subscriber_email" placeholder="email@example.com" required style="width: 300px;">
                <button type="submit" class="button button-primary">Add Subscriber</button>
            </form>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3>Import CSV</h3>
            <form method="post" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center;">
                <?php wp_nonce_field('bcc_mailer_action'); ?>
                <input type="hidden" name="bcc_action" value="import_csv">
                <input type="file" name="csv_file" accept=".csv" required>
                <button type="submit" class="button button-secondary">Upload & Import</button>
            </form>
            <p class="description">CSV must have an "email" column header. Other columns will be ignored.</p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3>Export CSV</h3>
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=bcc_export_csv'), 'bcc_export_csv'); ?>" class="button button-secondary">💾 Export Subscribers CSV</a>
            <p class="description">Download all subscribers (active and unsubscribed) as a CSV file.</p>
        </div>
        
        <h3>Current Subscribers (<?php echo count($subscribers); ?>)</h3>
        
        <?php if (empty($subscribers)): ?>
            <p>No subscribers yet. Add one above or import a CSV.</p>
        <?php else: ?>
            <table class="bcc-subscribers-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Phone</th>
                        <th>Organization</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Subscribed Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $subscriber): ?>
                        <tr>
                            <td><?php echo esc_html($subscriber->email); ?></td>
                            <td><?php echo esc_html($subscriber->first_name ?? '-'); ?></td>
                            <td><?php echo esc_html($subscriber->last_name ?? '-'); ?></td>
                            <td><?php echo esc_html($subscriber->phone ?? '-'); ?></td>
                            <td><?php echo esc_html($subscriber->organization ?? '-'); ?></td>
                            <td class="bcc-status-<?php echo $subscriber->status; ?>">
                                <?php echo ucfirst($subscriber->status); ?>
                            </td>
                            <td><?php echo esc_html($subscriber->source); ?></td>
                            <td><?php echo get_date_from_gmt($subscriber->subscribe_date, 'M j, Y g:i A'); ?></td>
                            <td>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this subscriber?');">
                                    <?php wp_nonce_field('bcc_mailer_action'); ?>
                                    <input type="hidden" name="bcc_action" value="delete_subscriber">
                                    <input type="hidden" name="subscriber_id" value="<?php echo $subscriber->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Section 5: Email Template & Schedule Settings -->
    <div class="bcc-section">
        <h2>⚙️ Email Template & Schedule Settings</h2>
        
        <form method="post" id="bcc-settings-form">
            <?php wp_nonce_field('bcc_mailer_action'); ?>
            <input type="hidden" name="bcc_action" value="save_template">
            
            <h3 style="color: #13294b; margin-top: 0;">📅 Send Schedule</h3>
            <table class="bcc-form-table">
                <tr>
                    <th><label for="send_time">Send Time (ET):</label></th>
                    <td>
                        <select id="send_time" name="send_time" required>
                            <?php
                            $current_time = isset($settings['send_time']) ? $settings['send_time'] : '06:00';
                            for ($hour = 0; $hour < 24; $hour++) {
                                for ($minute = 0; $minute < 60; $minute += 15) {
                                    $time_value = sprintf('%02d:%02d', $hour, $minute);
                                    $time_display = date('g:i A', strtotime($time_value));
                                    $selected = ($time_value === $current_time) ? 'selected' : '';
                                    echo '<option value="' . $time_value . '" ' . $selected . '>' . $time_display . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <p class="description">Time in Eastern Time (America/New_York timezone)</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Send on Days:</label></th>
                    <td>
                        <div class="bcc-days-checkboxes">
                            <?php
                            $days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
                            $selected_days = isset($settings['send_days']) ? $settings['send_days'] : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                            foreach ($days as $value => $label):
                                $checked = in_array($value, $selected_days) ? 'checked' : '';
                            ?>
                                <label>
                                    <input type="checkbox" name="send_days[]" value="<?php echo $value; ?>" <?php echo $checked; ?>>
                                    <?php echo $label; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description">Emails will only be sent on checked days. Cron still runs daily but skips non-selected days.</p>
                    </td>
                </tr>
            </table>
            
            <h3 style="color: #13294b; margin-top: 30px;">📧 Sender Settings</h3>
            <table class="bcc-form-table">
                <tr>
                    <th><label for="from_email">From Email:</label></th>
                    <td>
                        <input type="email" id="from_email" name="from_email" value="<?php echo esc_attr(isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email')); ?>" required>
                        <p class="description">Must be verified in Amazon SES</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="from_name">From Name:</label></th>
                    <td>
                        <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr(isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name')); ?>" required>
                        <p class="description">Display name for the sender</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="reply_to">Reply-To Email:</label></th>
                    <td>
                        <input type="email" id="reply_to" name="reply_to" value="<?php echo esc_attr(isset($settings['reply_to']) ? $settings['reply_to'] : get_option('admin_email')); ?>">
                        <p class="description">Optional. Where replies should go (defaults to From Email if empty)</p>
                    </td>
                </tr>
            </table>
            
            <h3 style="color: #13294b; margin-top: 30px;">✉️ Email Content</h3>
            <table class="bcc-form-table">
                <tr>
                    <th><label for="subject">Subject Line:</label></th>
                    <td><input type="text" id="subject" name="subject" value="<?php echo esc_attr($settings['subject']); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="greeting">Greeting:</label></th>
                    <td><input type="text" id="greeting" name="greeting" value="<?php echo esc_attr($settings['greeting']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="body">Main Message:</label></th>
                    <td><textarea id="body" name="body" required><?php echo esc_textarea($settings['body']); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="button1_text">Button 1 Text:</label></th>
                    <td><input type="text" id="button1_text" name="button1_text" value="<?php echo esc_attr($settings['button1_text']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="button1_url">Button 1 URL:</label></th>
                    <td><input type="url" id="button1_url" name="button1_url" value="<?php echo esc_attr($settings['button1_url']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="button2_text">Button 2 Text:</label></th>
                    <td><input type="text" id="button2_text" name="button2_text" value="<?php echo esc_attr($settings['button2_text']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="button2_url">Button 2 URL:</label></th>
                    <td><input type="url" id="button2_url" name="button2_url" value="<?php echo esc_attr($settings['button2_url']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="footer">Footer Text:</label></th>
                    <td><input type="text" id="footer" name="footer" value="<?php echo esc_attr($settings['footer']); ?>"></td>
                </tr>
            </table>
            
            <p class="description">Use <code>{unsubscribe_url}</code> in your template - it will be replaced automatically with each subscriber's unique link.</p>
            
            <h3 style="color: #13294b; margin-top: 30px;">📋 Gravity Forms Integration</h3>
            <p class="description">Configure which Gravity Form should add subscribers and map form fields to subscriber data.</p>
            
            <?php
            // Get all Gravity Forms and their fields if available
            $gf_forms = [];
            $gf_forms_fields = [];
            if (class_exists('GFAPI')) {
                $gf_forms = GFAPI::get_forms();
                // Load fields for each form
                foreach ($gf_forms as $gf_form) {
                    $form_details = GFAPI::get_form($gf_form['id']);
                    if ($form_details && isset($form_details['fields'])) {
                        $gf_forms_fields[$gf_form['id']] = $form_details['fields'];
                    }
                }
            }
            $has_gf = !empty($gf_forms);
            ?>
            
            <table class="bcc-form-table">
                <tr>
                    <th><label for="gf_form_id">Gravity Form:</label></th>
                    <td>
                        <?php if ($has_gf): ?>
                            <select id="gf_form_id" name="gf_form_id" onchange="bccSwitchFormFields(this.value)">
                                <option value="">-- Select a Form --</option>
                                <?php foreach ($gf_forms as $gf_form): ?>
                                    <option value="<?php echo esc_attr($gf_form['id']); ?>" <?php selected(isset($settings['gf_form_id']) ? $settings['gf_form_id'] : '', $gf_form['id']); ?>>
                                        <?php echo esc_html($gf_form['title']); ?> (ID: <?php echo $gf_form['id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select the form that should add subscribers when submitted.</p>
                        <?php else: ?>
                            <input type="text" id="gf_form_id" name="gf_form_id" value="<?php echo esc_attr(isset($settings['gf_form_id']) ? $settings['gf_form_id'] : ''); ?>" placeholder="Enter Form ID">
                            <p class="description">Gravity Forms plugin not detected. Enter Form ID and field IDs manually.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php if ($has_gf): ?>
                <div id="bcc-field-mapping" style="<?php echo empty($settings['gf_form_id']) ? 'display:none;' : ''; ?>">
                    <h4 style="color: #13294b; margin-top: 20px;">Field Mapping</h4>
                    <p class="description">Select which fields from your form should map to subscriber data.</p>
                    
                    <table class="bcc-form-table">
                        <tr>
                            <th><label for="gf_email_field">Email Field:</label></th>
                            <td>
                                <select id="gf_email_field" name="gf_email_field" required>
                                    <option value="">-- Select Email Field --</option>
                                    <?php foreach ($gf_forms_fields as $form_id => $fields): ?>
                                        <?php foreach ($fields as $field): ?>
                                            <?php if ($field->type === 'email'): ?>
                                                <option value="<?php echo esc_attr($field->id); ?>" 
                                                        data-form-id="<?php echo esc_attr($form_id); ?>"
                                                        <?php selected(isset($settings['gf_email_field']) ? $settings['gf_email_field'] : '', $field->id); ?>>
                                                    <?php echo esc_html($field->label); ?> (Field <?php echo $field->id; ?>)
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Required. The field that contains the email address.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="gf_first_name_field">First Name Field:</label></th>
                            <td>
                                <select id="gf_first_name_field" name="gf_first_name_field">
                                    <option value="">-- Select First Name Field (Optional) --</option>
                                    <?php foreach ($gf_forms_fields as $form_id => $fields): ?>
                                        <?php foreach ($fields as $field): ?>
                                            <?php if ($field->type === 'name' && isset($field->inputs) && is_array($field->inputs)): ?>
                                                <?php foreach ($field->inputs as $input): ?>
                                                    <?php if (stripos($input['label'], 'first') !== false): ?>
                                                        <option value="<?php echo esc_attr($input['id']); ?>" 
                                                                data-form-id="<?php echo esc_attr($form_id); ?>"
                                                                <?php selected(isset($settings['gf_first_name_field']) ? $settings['gf_first_name_field'] : '', $input['id']); ?>>
                                                            <?php echo esc_html($field->label); ?> - <?php echo esc_html($input['label']); ?> (Field <?php echo $input['id']; ?>)
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php elseif ($field->type === 'text' && stripos($field->label, 'first') !== false): ?>
                                                <option value="<?php echo esc_attr($field->id); ?>" 
                                                        data-form-id="<?php echo esc_attr($form_id); ?>"
                                                        <?php selected(isset($settings['gf_first_name_field']) ? $settings['gf_first_name_field'] : '', $field->id); ?>>
                                                    <?php echo esc_html($field->label); ?> (Field <?php echo $field->id; ?>)
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="gf_last_name_field">Last Name Field:</label></th>
                            <td>
                                <select id="gf_last_name_field" name="gf_last_name_field">
                                    <option value="">-- Select Last Name Field (Optional) --</option>
                                    <?php foreach ($gf_forms_fields as $form_id => $fields): ?>
                                        <?php foreach ($fields as $field): ?>
                                            <?php if ($field->type === 'name' && isset($field->inputs) && is_array($field->inputs)): ?>
                                                <?php foreach ($field->inputs as $input): ?>
                                                    <?php if (stripos($input['label'], 'last') !== false): ?>
                                                        <option value="<?php echo esc_attr($input['id']); ?>" 
                                                                data-form-id="<?php echo esc_attr($form_id); ?>"
                                                                <?php selected(isset($settings['gf_last_name_field']) ? $settings['gf_last_name_field'] : '', $input['id']); ?>>
                                                            <?php echo esc_html($field->label); ?> - <?php echo esc_html($input['label']); ?> (Field <?php echo $input['id']; ?>)
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php elseif ($field->type === 'text' && stripos($field->label, 'last') !== false): ?>
                                                <option value="<?php echo esc_attr($field->id); ?>" 
                                                        data-form-id="<?php echo esc_attr($form_id); ?>"
                                                        <?php selected(isset($settings['gf_last_name_field']) ? $settings['gf_last_name_field'] : '', $field->id); ?>>
                                                    <?php echo esc_html($field->label); ?> (Field <?php echo $field->id; ?>)
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="gf_phone_field">Phone Field:</label></th>
                            <td>
                                <select id="gf_phone_field" name="gf_phone_field">
                                    <option value="">-- Select Phone Field (Optional) --</option>
                                    <?php foreach ($gf_forms_fields as $form_id => $fields): ?>
                                        <?php foreach ($fields as $field): ?>
                                            <?php if ($field->type === 'phone'): ?>
                                                <option value="<?php echo esc_attr($field->id); ?>" 
                                                        data-form-id="<?php echo esc_attr($form_id); ?>"
                                                        <?php selected(isset($settings['gf_phone_field']) ? $settings['gf_phone_field'] : '', $field->id); ?>>
                                                    <?php echo esc_html($field->label); ?> (Field <?php echo $field->id; ?>)
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="gf_organization_field">Organization/Affiliation Field:</label></th>
                            <td>
                                <select id="gf_organization_field" name="gf_organization_field">
                                    <option value="">-- Select Organization Field (Optional) --</option>
                                    <?php foreach ($gf_forms_fields as $form_id => $fields): ?>
                                        <?php foreach ($fields as $field): ?>
                                            <?php if ($field->type === 'text' && (stripos($field->label, 'organization') !== false || stripos($field->label, 'affiliation') !== false || stripos($field->label, 'company') !== false)): ?>
                                                <option value="<?php echo esc_attr($field->id); ?>" 
                                                        data-form-id="<?php echo esc_attr($form_id); ?>"
                                                        <?php selected(isset($settings['gf_organization_field']) ? $settings['gf_organization_field'] : '', $field->id); ?>>
                                                    <?php echo esc_html($field->label); ?> (Field <?php echo $field->id; ?>)
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <script>
                function bccSwitchFormFields(formId) {
                    // Show/hide field mapping section
                    var mappingSection = document.getElementById('bcc-field-mapping');
                    if (formId) {
                        mappingSection.style.display = 'block';
                    } else {
                        mappingSection.style.display = 'none';
                        return;
                    }
                    
                    // Show only options for selected form
                    var selects = ['gf_email_field', 'gf_first_name_field', 'gf_last_name_field', 'gf_phone_field', 'gf_organization_field'];
                    
                    selects.forEach(function(selectId) {
                        var select = document.getElementById(selectId);
                        if (!select) return;
                        
                        // Store current value
                        var currentValue = select.value;
                        var currentValueBelongsToForm = false;
                        
                        // Show/hide options based on form ID
                        var options = select.querySelectorAll('option');
                        options.forEach(function(option) {
                            if (option.value === '') {
                                option.style.display = 'block'; // Always show "Select..." option
                            } else {
                                var optionFormId = option.getAttribute('data-form-id');
                                if (optionFormId === formId) {
                                    option.style.display = 'block';
                                    // Check if current value belongs to this form
                                    if (option.value === currentValue) {
                                        currentValueBelongsToForm = true;
                                    }
                                } else {
                                    option.style.display = 'none';
                                }
                            }
                        });
                        
                        // Only reset if current value doesn't belong to the selected form
                        if (!currentValueBelongsToForm && currentValue !== '') {
                            select.value = '';
                        }
                    });
                }
                
                // Initialize on page load
                document.addEventListener('DOMContentLoaded', function() {
                    var formSelect = document.getElementById('gf_form_id');
                    if (formSelect && formSelect.value) {
                        bccSwitchFormFields(formSelect.value);
                    }
                });
                </script>
            <?php else: ?>
                <!-- Fallback for manual entry when GF not available -->
                <table class="bcc-form-table">
                    <tr>
                        <th><label for="gf_email_field">Email Field ID:</label></th>
                        <td>
                            <input type="text" id="gf_email_field" name="gf_email_field" value="<?php echo esc_attr(isset($settings['gf_email_field']) ? $settings['gf_email_field'] : ''); ?>" placeholder="e.g., 3">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gf_first_name_field">First Name Field ID:</label></th>
                        <td>
                            <input type="text" id="gf_first_name_field" name="gf_first_name_field" value="<?php echo esc_attr(isset($settings['gf_first_name_field']) ? $settings['gf_first_name_field'] : ''); ?>" placeholder="e.g., 1.3">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gf_last_name_field">Last Name Field ID:</label></th>
                        <td>
                            <input type="text" id="gf_last_name_field" name="gf_last_name_field" value="<?php echo esc_attr(isset($settings['gf_last_name_field']) ? $settings['gf_last_name_field'] : ''); ?>" placeholder="e.g., 1.6">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gf_phone_field">Phone Field ID:</label></th>
                        <td>
                            <input type="text" id="gf_phone_field" name="gf_phone_field" value="<?php echo esc_attr(isset($settings['gf_phone_field']) ? $settings['gf_phone_field'] : ''); ?>" placeholder="e.g., 4">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gf_organization_field">Organization Field ID:</label></th>
                        <td>
                            <input type="text" id="gf_organization_field" name="gf_organization_field" value="<?php echo esc_attr(isset($settings['gf_organization_field']) ? $settings['gf_organization_field'] : ''); ?>" placeholder="e.g., 5">
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
            
            <div class="bcc-button-group">
                <button type="submit" class="button button-primary">💾 Save All Settings</button>
            </div>
        </form>
    </div>
    
    <!-- Section 7: Activity Log -->
    <div class="bcc-section">
        <h2>📋 Activity Log (Last 100 Entries)</h2>
        
        <?php if (empty($logs)): ?>
            <p>No activity logged yet.</p>
        <?php else: ?>
            <details>
                <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                    Click to Show Activity Log (<?php echo count($logs); ?> entries)
                </summary>
                <div style="margin-top: 10px;">
                    <table class="bcc-logs-table">
                        <thead>
                            <tr>
                                <th>Timestamp (ET)</th>
                                <th>Action</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php 
                                $row_class = '';
                                if ($log->status === 'success') $row_class = 'bcc-log-success';
                                elseif ($log->status === 'error') $row_class = 'bcc-log-error';
                                elseif ($log->status === 'info') $row_class = 'bcc-log-info';
                                
                                $details = json_decode($log->details, true);
                                $details_display = '';
                                if (!empty($details)) {
                                    if (isset($details['error'])) {
                                        $details_display = 'Error: ' . $details['error'];
                                    } elseif (isset($details['reason'])) {
                                        $details_display = 'Reason: ' . $details['reason'];
                                    } else {
                                        $details_display = json_encode($details);
                                    }
                                }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo get_date_from_gmt($log->timestamp, 'M j, Y g:i:s A'); ?></td>
                                    <td><?php echo esc_html($log->action); ?></td>
                                    <td><?php echo esc_html($log->email ?: '-'); ?></td>
                                    <td><strong><?php echo esc_html($log->status); ?></strong></td>
                                    <td>
                                        <div class="bcc-log-details" title="<?php echo esc_attr($details_display); ?>">
                                            <?php echo esc_html($details_display); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </div>
    
    <!-- Section 8: Debug Information -->
    <div class="bcc-section">
        <h2>🔍 Debug Information</h2>
        <p class="description">Raw system data for troubleshooting</p>
        
        <details>
            <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                Click to Show Debug JSON
            </summary>
            <div class="bcc-debug" style="margin-top: 10px;">
                <pre><?php
                $debug_info = [
                    'plugin_version' => BCC_MAILER_VERSION,
                    'wordpress_version' => get_bloginfo('version'),
                    'php_version' => PHP_VERSION,
                    'current_time_utc' => current_time('mysql', true),
                    'current_time_local' => current_time('mysql'),
                    'timezone_string' => wp_timezone_string(),
                    'gmt_offset' => get_option('gmt_offset'),
                    'mode' => bcc_get_mode(),
                    'cron_info' => $cron_info,
                    'email_settings' => $settings,
                    'subscriber_counts' => [
                        'total' => count($subscribers),
                        'active' => $active_count,
                        'unsubscribed' => $unsubscribed_count
                    ],
                    'database_tables' => [
                        'subscribers' => $GLOBALS['wpdb']->prefix . 'bcc_subscribers',
                        'logs' => $GLOBALS['wpdb']->prefix . 'bcc_logs'
                    ],
                    'all_scheduled_crons' => array_filter(
                        _get_cron_array(),
                        function($timestamp) {
                            $events = _get_cron_array()[$timestamp];
                            return isset($events['bcc_daily_mailer_send_emails']);
                        },
                        ARRAY_FILTER_USE_KEY
                    )
                ];
                echo json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                ?></pre>
            </div>
        </details>
    </div>
</div>


