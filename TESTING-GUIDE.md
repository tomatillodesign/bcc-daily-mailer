# 🧪 Testing Guide for BCC Daily Mailer

## Step-by-Step Testing Checklist

### ✅ Phase 1: Plugin Activation (2 minutes)

1. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "BCC Daily Mailer"
   - Click "Activate"
   - ✓ Should activate without errors

2. **Verify Dashboard**
   - Go to **BCC Mailer** in admin menu
   - ✓ Dashboard should load with all sections
   - ✓ System Status should show "🟢 PRODUCTION MODE"
   - ✓ Should show "0 Active Subscribers"
   - ✓ Cron should be registered for next 6:00 AM ET

3. **Check Activity Log**
   - Scroll to Activity Log section
   - ✓ Should see "plugin_activated" entry

---

### ✅ Phase 2: Add Test Subscribers (3 minutes)

1. **Add Subscriber Manually**
   - In "Subscribers Management" section
   - Enter your email address
   - Click "Add Subscriber"
   - ✓ Should see success message
   - ✓ Email should appear in subscribers table
   - ✓ Status should be "Active"
   - ✓ Source should be "manual_admin"
   - ✓ Activity Log should show "subscriber_added"

2. **Import Test CSV**
   - Use the included `test-subscribers.csv` file
   - Or create your own with these contents:
     ```
     email
     test1@example.com
     test2@example.com
     ```
   - Click "Upload & Import"
   - ✓ Should see "CSV imported: 2 added, 0 skipped, 0 errors"
   - ✓ Should now have 3 total subscribers
   - ✓ Activity Log should show "csv_import_completed"

3. **Verify Subscriber Data**
   - Check subscribers table
   - ✓ Each should have unique unsubscribe token
   - ✓ Each should have unsubscribe URL displayed
   - ✓ Subscribe dates should be current

---

### ✅ Phase 3: Configure Email Template (2 minutes)

1. **Edit Template**
   - Scroll to "Email Template Editor"
   - Customize the content (or leave defaults)
   - Example Button URLs:
     - Button 1: `https://example.com/submit`
     - Button 2: `https://example.com/dashboard`
   - Click "💾 Save Template"
   - ✓ Should see "Email template saved" success message
   - ✓ Activity Log should show "template_saved"

---

### ✅ Phase 4: Test Email Sending (5 minutes)

1. **Send Test Email to Yourself**
   - In "Quick Actions" section
   - Click "📧 Send Test Email to Me"
   - ✓ Should see "Test email sent to [your-admin-email]"
   - ✓ Check your email inbox
   - ✓ Email should arrive from "NC Birth Capacity Connector <mombabycmih@gmail.com>"
   - ✓ Subject should match your template
   - ✓ Both buttons should be visible and clickable
   - ✓ Unsubscribe link should be present (but won't work - it's a test token)
   - ✓ Activity Log should show "email_sent" with success status

2. **If Email Doesn't Arrive**
   - Check spam/junk folder
   - Verify Offload SES Lite is configured
   - Check Activity Log for error messages
   - Look at Debug JSON → check for errors
   - Verify mombabycmih@gmail.com is verified in SES

---

### ✅ Phase 5: Test Cron Scheduling (10 minutes)

**IMPORTANT**: On Local WP, you must reload a page to trigger WP-Cron!

1. **Schedule Test Send in 2 Minutes**
   - In "Testing Controls" section
   - Click "⏱️ Schedule Test Send in 2 Minutes"
   - ✓ Should see "Test send scheduled for 2 minutes from now"
   - ✓ Mode badge should change to "🔴 TESTING MODE"
   - ✓ Warning banner should appear
   - ✓ Countdown timer should show "2 minutes X seconds"
   - ✓ Activity Log should show "test_cron_scheduled"

2. **Wait for Countdown**
   - Watch the countdown timer
   - When it reaches about 10 seconds remaining...

3. **Trigger the Cron** ⚠️ CRITICAL STEP
   - **Reload this admin page** (F5 or Cmd+R)
   - Or visit any other page on your site
   - This triggers WP-Cron on Local WP

4. **Verify Batch Send**
   - Check Activity Log (may need to reload again)
   - ✓ Should see "batch_send_started"
   - ✓ Should see "batch_send_completed" with counts
   - ✓ Should see individual "email_sent" entries for each subscriber
   - ✓ Check your email - should have received the email
   - ✓ System Status should show "Last Batch Send" info

5. **Check Email Details**
   - Open the email you received
   - ✓ From: NC Birth Capacity Connector <mombabycmih@gmail.com>
   - ✓ Subject matches template
   - ✓ Content matches template
   - ✓ Unsubscribe link is present and unique to you

---

### ✅ Phase 6: Test Unsubscribe (2 minutes)

1. **Get Unsubscribe URL**
   - In Subscribers table, find your email
   - Copy the "Unsubscribe URL" (should be like `/unsubscribe/?token=xxxxx`)

2. **Visit Unsubscribe Page**
   - Paste the URL in your browser (on your .local site)
   - ✓ Should see confirmation page: "✓ Unsubscribed Successfully"
   - ✓ Should show your email address
   - ✓ Should have clean, styled page

3. **Verify Database Update**
   - Go back to BCC Mailer admin
   - Check Subscribers table
   - ✓ Your email status should now be "Unsubscribed"
   - ✓ Activity Log should show "unsubscribed" action

4. **Test Invalid Token**
   - Visit `/unsubscribe/?token=INVALID`
   - ✓ Should see error message
   - ✓ Activity Log should show "unsubscribe_failed"

---

### ✅ Phase 7: Test Manual Cron Fire (2 minutes)

1. **Restore Production Mode First**
   - Click "🟢 Restore Normal Daily Schedule (6:00 AM ET)"
   - ✓ Mode should change back to "🟢 PRODUCTION MODE"
   - ✓ Next send should show tomorrow at 6:00 AM ET

2. **Fire Cron Manually**
   - Click "🚀 Manually Fire Cron Now"
   - Confirm the dialog
   - ✓ Should execute immediately (no page reload needed)
   - ✓ Should see "Cron executed manually: X sent..."
   - ✓ Activity Log should show new batch_send entries
   - ✓ Check your email (if you re-subscribed)

---

### ✅ Phase 8: Test Gravity Forms Integration (3 minutes)

**Prerequisites**: Gravity Forms plugin must be active, Form ID 1 must exist

1. **Find Your Form**
   - Go to Forms → Forms in WordPress admin
   - Verify Form ID 1 exists
   - Note: The form should have an Email field (Field ID 3)

2. **Submit the Form**
   - Visit the page where Form ID 1 is displayed
   - Fill out the form with a new test email
   - Submit

3. **Verify Subscriber Added**
   - Go back to BCC Mailer admin
   - Check Subscribers table
   - ✓ New email should be in the list
   - ✓ Source should be "gravity_forms"
   - ✓ Source ID should show the entry ID
   - ✓ Activity Log should show "subscriber_added" with source: gravity_forms

4. **Test Duplicate Submission**
   - Submit the form again with the same email
   - ✓ Should be skipped (not added twice)
   - ✓ Activity Log should show "subscriber_add_skipped"

---

### ✅ Phase 9: Test CSV Export (1 minute)

1. **Export Subscribers**
   - Click "💾 Export Subscribers CSV"
   - ✓ Should download file: `bcc-subscribers-YYYY-MM-DD.csv`
   - ✓ Open file - should contain all subscribers
   - ✓ Columns: Email, Status, Source, Subscribe Date, Unsubscribe Date

---

### ✅ Phase 10: Test Bulk Actions (2 minutes)

1. **Delete a Subscriber**
   - In Subscribers table, click "Delete" on a test subscriber
   - Confirm
   - ✓ Should be removed from list
   - ✓ Activity Log should show "subscriber_deleted"

2. **Clear Old Logs**
   - Click "🗑️ Clear Old Logs"
   - Confirm
   - ✓ Should keep last 100 entries
   - ✓ Activity Log should show "logs_cleared"

---

### ✅ Phase 11: Test Debug Information (1 minute)

1. **View Debug JSON**
   - Scroll to "Debug Information" section
   - Click "Click to Show Debug JSON"
   - ✓ Should display formatted JSON
   - ✓ Should show:
     - Plugin version: 1.0.0
     - Current mode
     - Cron info with next run timestamp
     - Email settings
     - Subscriber counts
     - Database table names
     - All scheduled crons

2. **Verify Data Accuracy**
   - Check that subscriber counts match
   - Verify cron schedule is correct
   - Confirm email settings match what you saved

---

## 🎯 Success Criteria

After completing all phases, you should have:

- ✅ Plugin activated without errors
- ✅ 2-3 test subscribers added (manual + CSV)
- ✅ Email template configured and saved
- ✅ Test email received in your inbox
- ✅ Scheduled test send executed successfully
- ✅ Batch emails sent to all active subscribers
- ✅ Unsubscribe functionality working
- ✅ Activity log showing all actions
- ✅ Gravity Forms integration working (if tested)
- ✅ CSV export working
- ✅ Debug JSON displaying accurate data
- ✅ No errors in Activity Log (except expected test failures)

---

## 🐛 Common Issues & Solutions

### Issue: Emails Not Sending

**Symptoms**: Activity Log shows "email_failed"

**Solutions**:
1. Check Offload SES Lite is active and configured
2. Verify mombabycmih@gmail.com is verified in Amazon SES
3. Check Debug JSON for error messages
4. Test wp_mail() with a simple test email
5. Check PHP error logs

### Issue: Cron Not Firing on Local WP

**Symptoms**: Countdown reaches zero but nothing happens

**Solutions**:
1. **Reload the page** - this is required on Local WP
2. Visit any page on your site to trigger WP-Cron
3. Use "Manually Fire Cron Now" button instead
4. Check that cron is registered in System Status

### Issue: Gravity Forms Not Adding Subscribers

**Symptoms**: Form submits but subscriber not added

**Solutions**:
1. Verify Form ID is 1 (check Forms → Forms)
2. Verify Email field is Field ID 3 (check form editor)
3. Check Activity Log for "gravity_form_submission_failed"
4. Ensure Gravity Forms plugin is active
5. Test with a valid email address

### Issue: Unsubscribe Link Not Working

**Symptoms**: Clicking unsubscribe shows error

**Solutions**:
1. Verify URL format: `/unsubscribe/?token=xxxxx`
2. Check that token exists in database
3. Try copying URL directly from Subscribers table
4. Check Activity Log for error details

### Issue: Mode Stuck in Testing

**Symptoms**: Can't get back to production mode

**Solutions**:
1. Click "🟢 Restore Normal Daily Schedule"
2. If that fails, click "🗑️ Clear All Cron Schedules"
3. Then click "Restore Normal Daily Schedule" again
4. Check Debug JSON to verify mode changed

---

## 📊 Expected Activity Log Entries

After full testing, your Activity Log should include:

1. `plugin_activated` - Plugin was activated
2. `subscriber_added` (multiple) - Subscribers added manually
3. `csv_import_completed` - CSV was imported
4. `template_saved` - Email template was saved
5. `email_sent` (multiple) - Test emails sent
6. `test_cron_scheduled` - Test cron was scheduled
7. `batch_send_started` - Batch send began
8. `batch_send_completed` - Batch send finished
9. `unsubscribed` - Subscriber unsubscribed
10. `subscriber_deleted` - Subscriber was deleted
11. `logs_cleared` - Old logs were cleared
12. `cron_scheduled` - Daily cron was restored

---

## 🚀 Ready for Production?

Once all tests pass:

1. ✅ All emails arriving successfully
2. ✅ Unsubscribe working correctly
3. ✅ Cron scheduling reliable
4. ✅ No errors in Activity Log
5. ✅ Gravity Forms integration working
6. ✅ CSV import/export working

**Next Steps**:
1. Export plugin as ZIP
2. Install on WP Engine staging
3. Test with WP Engine Alternate Cron
4. Import production subscribers (46 emails)
5. Run parallel with Constant Contact
6. Monitor for 2 weeks
7. Full migration or rollback

---

**Testing Time**: ~30-35 minutes total  
**Last Updated**: December 5, 2025

