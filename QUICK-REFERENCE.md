# 🚀 BCC Daily Mailer - Quick Reference

## Essential Commands & Actions

### 🔴 MOST IMPORTANT FOR LOCAL WP
**After scheduling a test send, you MUST reload a page to trigger WP-Cron!**

---

## 📍 Quick Access
- **Admin Dashboard**: WordPress Admin → BCC Mailer (left sidebar)
- **Plugin Files**: `/wp-content/plugins/bcc-daily-mailer/`

---

## ⚡ Common Actions

### Send Test Email Right Now
1. Go to BCC Mailer admin
2. Click "📧 Send Test Email to Me"
3. Check your admin email inbox

### Schedule Test Send in 2 Minutes
1. Click "⏱️ Schedule Test Send in 2 Minutes"
2. Wait for countdown to reach ~10 seconds
3. **Reload the page** (F5 or Cmd+R) ← CRITICAL!
4. Check Activity Log for results

### Send to All Subscribers Immediately
1. Click "🚀 Manually Fire Cron Now"
2. Confirm dialog
3. Check Activity Log

### Add Subscriber Manually
1. Scroll to "Subscribers Management"
2. Enter email in "Add Subscriber" field
3. Click "Add Subscriber"

### Import CSV
1. Prepare CSV with "email" column header
2. Scroll to "Subscribers Management"
3. Click "Upload & Import"
4. Select CSV file

### Switch Modes
- **To Testing**: Click "Schedule Test Send in 2 Minutes"
- **To Production**: Click "🟢 Restore Normal Daily Schedule"

---

## 🎨 Email Template Tokens

Use these in your email template:
- `{greeting}` - Greeting text
- `{body}` - Main message body
- `{button1_text}` - First button text
- `{button1_url}` - First button URL
- `{button2_text}` - Second button text
- `{button2_url}` - Second button URL
- `{footer}` - Footer text
- `{unsubscribe_url}` - Unique unsubscribe link (auto-generated)

---

## 🔍 Troubleshooting Quick Checks

### Email Not Sending?
1. Check Activity Log for errors
2. Verify Offload SES Lite is active
3. Confirm mombabycmih@gmail.com is verified in SES
4. Look at Debug JSON for error details

### Cron Not Firing?
1. **Reload the page!** (Local WP requirement)
2. Check System Status - is cron registered?
3. Try "Manually Fire Cron Now" instead
4. Check Debug JSON → `all_scheduled_crons`

### Subscriber Not Added?
1. Check Activity Log for errors
2. Verify email format is valid
3. Check for duplicate (already exists)
4. For Gravity Forms: verify Form ID 1, Field ID 3

---

## 📊 Mode Indicators

| Badge | Meaning | Next Send |
|-------|---------|-----------|
| 🟢 PRODUCTION MODE | Normal operation | Tomorrow 6:00 AM ET |
| 🔴 TESTING MODE | Test schedule active | X minutes from now |

---

## 🗂️ File Locations

```
bcc-daily-mailer/
├── bcc-daily-mailer.php       ← Main plugin logic
├── admin-page.php              ← Admin dashboard UI
├── email-template.html         ← Email HTML template
├── test-subscribers.csv        ← Sample CSV
├── README.md                   ← Full documentation
├── TESTING-GUIDE.md            ← Step-by-step testing
└── QUICK-REFERENCE.md          ← This file
```

---

## 🔐 Configuration

| Setting | Value |
|---------|-------|
| From Email | mombabycmih@gmail.com |
| From Name | NC Birth Capacity Connector |
| Timezone | America/New_York (ET) |
| Production Send Time | 6:00 AM ET daily |
| Gravity Form ID | 1 |
| Email Field ID | 3 |
| Cron Hook | bcc_daily_mailer_send_emails |

---

## 📋 CSV Format

Your CSV must have this format:

```csv
email
subscriber1@example.com
subscriber2@example.com
subscriber3@example.com
```

- Header row MUST be "email" (lowercase)
- One email per line
- Other columns will be ignored

---

## 🧪 Testing Workflow

1. **Activate Plugin** → Check System Status
2. **Add Test Subscribers** → Import CSV or add manually
3. **Configure Template** → Edit and save
4. **Send Test Email** → Verify receipt
5. **Schedule Test Send** → Wait + Reload page
6. **Check Activity Log** → Verify success
7. **Test Unsubscribe** → Copy URL and visit
8. **Restore Production** → Switch back to daily mode

---

## 🚨 Emergency Actions

### Stop All Scheduled Sends
1. Click "🗑️ Clear All Cron Schedules"
2. Confirm

### Reset to Production Mode
1. Click "🟢 Restore Normal Daily Schedule"
2. Verify mode badge shows 🟢 PRODUCTION MODE

### Clear Logs
1. Click "🗑️ Clear Old Logs"
2. Keeps last 100 entries

---

## 📞 Support

**Questions?** Contact: cmih@med.unc.edu

---

**Version**: 1.0.0  
**Last Updated**: December 5, 2025

