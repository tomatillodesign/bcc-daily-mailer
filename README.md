# BCC Daily Mailer Plugin

Automated daily email reminders at 6:00 AM ET via Amazon SES with Gravity Forms integration.

## 🚀 Quick Start

### 1. Activate the Plugin

1. Go to WordPress Admin → Plugins
2. Find "BCC Daily Mailer" and click "Activate"
3. Plugin will automatically:
   - Create database tables (`wp_bcc_subscribers` and `wp_bcc_logs`)
   - Schedule daily cron at 6:00 AM ET
   - Log activation

### 2. Access the Admin Dashboard

- Go to **BCC Mailer** in the WordPress admin menu (left sidebar)
- You'll see a comprehensive dashboard with all controls in one place

### 3. Import Test Subscribers

1. Use the included `test-subscribers.csv` file (or create your own)
2. CSV must have an "email" column header
3. Go to **Subscribers Management** section
4. Click "Upload & Import" and select your CSV

### 4. Configure Email Template

1. Scroll to **Email Template Editor** section
2. Customize:
   - Subject line
   - Greeting
   - Main message body
   - Button 1 & 2 (text + URLs)
   - Footer text
3. Click "Save Template"

### 5. Test Email Sending

**Option A: Send Test to Yourself**
- Click "📧 Send Test Email to Me" button
- Check your admin email inbox

**Option B: Schedule Test Send in 2 Minutes**
- Click "⏱️ Schedule Test Send in 2 Minutes"
- **IMPORTANT FOR LOCAL WP**: After 2 minutes, reload any page on your site to trigger WP-Cron
- Check Activity Log to see results

**Option C: Fire Cron Immediately**
- Click "🚀 Manually Fire Cron Now"
- Sends to all active subscribers immediately

## ⚙️ Configuration

### Email Settings
- **From Email**: mombabycmih@gmail.com
- **From Name**: NC Birth Capacity Connector
- **Method**: Uses `wp_mail()` with custom headers to override Offload SES Lite default sender

### Gravity Forms Integration
- **Form ID**: 1
- **Email Field**: 3
- Automatically adds subscribers when form is submitted

### Cron Schedule
- **Production Mode**: Daily at 6:00 AM Eastern Time
- **Testing Mode**: Custom schedule (2 or 5 minutes from now)

## 🧪 Testing on Local WP

### Important Notes for .local Sites

1. **WP-Cron Trigger**: On Local WP, cron events are triggered by page loads (not alternate cron)
   - After scheduling a test send, **reload any page** when countdown reaches zero
   - Or visit the admin dashboard to trigger the cron

2. **Testing Controls**: Use the Testing Controls section to:
   - Schedule sends for 2 or 5 minutes from now
   - Manually fire cron immediately
   - Switch between testing and production modes

3. **Mode Indicator**: 
   - 🟢 PRODUCTION MODE = Daily at 6:00 AM ET
   - 🔴 TESTING MODE = Custom test schedule active

4. **Always Restore**: After testing, click "🟢 Restore Normal Daily Schedule" to return to production mode

## 📊 Admin Dashboard Sections

### 1. System Status
- Current mode (production/testing)
- Real-time countdown to next send
- Subscriber counts
- Last batch send results
- Configuration details

### 2. Testing Controls
- Schedule test sends (2 or 5 minutes)
- Manually fire cron now
- Restore normal daily schedule
- Clear all cron schedules

### 3. Quick Actions
- Send test email to admin
- Send to all subscribers now
- Clear old logs
- Export subscribers CSV

### 4. Subscribers Management
- Add subscribers manually
- Import CSV
- View all subscribers with status
- Delete subscribers
- See unsubscribe URLs

### 5. Email Template Editor
- Edit all email content
- Save template
- Token: `{unsubscribe_url}` (auto-replaced)

### 6. Activity Log
- Last 100 log entries
- Color-coded by status (green=success, red=error, blue=info)
- Shows all actions: emails sent, subscribers added, cron executions, errors

### 7. Debug Information
- Raw JSON of all system data
- Cron schedule details
- Database table info
- WordPress/PHP versions

## 🔍 Troubleshooting

### Emails Not Sending

1. **Check Offload SES Lite**: Ensure it's configured and working
2. **Test wp_mail()**: Send test email to yourself
3. **Check Activity Log**: Look for error messages
4. **Verify SES**: Ensure mombabycmih@gmail.com is verified in Amazon SES

### Cron Not Firing

1. **Local WP**: Remember to reload pages to trigger cron
2. **Check System Status**: Verify cron is registered
3. **Use Manual Fire**: Click "Manually Fire Cron Now" to test
4. **Check Debug JSON**: Look at `all_scheduled_crons` section

### Subscribers Not Added from Gravity Forms

1. **Verify Form ID**: Should be Form ID 1
2. **Check Email Field**: Should be Field ID 3
3. **Check Activity Log**: Look for `gravity_form_submission_failed` errors
4. **Test Form**: Submit form and check Subscribers list

### Unsubscribe Not Working

1. **Check URL**: Should be `/unsubscribe/?token=xxx`
2. **Verify Token**: Check subscriber's unsubscribe_token in database
3. **Check Activity Log**: Look for unsubscribe actions
4. **Test Link**: Copy unsubscribe URL from Subscribers table and visit it

## 📁 File Structure

```
bcc-daily-mailer/
├── bcc-daily-mailer.php      # Main plugin file (all logic)
├── admin-page.php             # Admin dashboard (all UI)
├── email-template.html        # HTML email template
├── test-subscribers.csv       # Sample CSV for testing
├── README.md                  # This file
└── gravityforms-export-*.json # Gravity Forms backup
```

## 🔐 Security Features

- Unique unsubscribe tokens (32 characters, URL-safe)
- Nonce verification on all admin actions
- Email validation on all inputs
- Sanitized database queries
- Capability checks (`manage_options`)

## 📝 Database Tables

### wp_bcc_subscribers
- Stores all subscribers with status (active/unsubscribed)
- Unique email and unsubscribe_token
- Tracks source (manual, csv_import, gravity_forms)

### wp_bcc_logs
- Comprehensive activity logging
- Last 100 entries displayed in admin
- JSON details for debugging

## 🚀 Going to Production

1. Test thoroughly on Local WP
2. Export plugin as ZIP (entire folder)
3. Install on WP Engine staging
4. Verify WP Engine Alternate Cron is enabled
5. Import production subscribers (46 emails)
6. Run parallel with Constant Contact for 2 weeks
7. Monitor deliverability and timing
8. Decide on full migration or rollback

## 📧 Support

Questions? Contact: cmih@med.unc.edu

---

**Version**: 1.0.0  
**Author**: CMIH  
**License**: Proprietary

