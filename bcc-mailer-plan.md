[1 tool called]

Here's the complete plan file content. You can now select all and copy:

---

```markdown
---
name: Daily Email Plugin
overview: Build a streamlined WordPress plugin for automated daily functional emails at 6:00 AM using Amazon SES, with Gravity Forms integration for subscriptions and simple unsubscribe functionality.
todos:
  - id: plugin-foundation
    content: Create plugin structure, activation hooks, and database tables
    status: pending
  - id: admin-interface
    content: Build admin menu with dashboard, settings, and subscriber management pages
    status: pending
  - id: ses-integration
    content: Integrate SES email sending (wp_mail or AWS SDK based on decision)
    status: pending
  - id: email-template
    content: Create editable HTML email template with token replacement
    status: pending
  - id: cron-setup
    content: Register daily cron at 6:00 AM with batch sending logic
    status: pending
  - id: subscriber-management
    content: Build subscriber CRUD operations and CSV import/export
    status: pending
  - id: gravity-forms-hook
    content: Integrate with Gravity Forms submission (or build fallback form)
    status: pending
  - id: unsubscribe-system
    content: Implement token-based unsubscribe handler and confirmation page
    status: pending
  - id: logging
    content: Add email send logging and admin dashboard display
    status: pending
  - id: testing
    content: Test all functionality on Local WP including manual sends and cron
    status: pending
---

# Daily Email Automation Plugin for WordPress

## Overview

Create a streamlined WordPress plugin that automates daily functional reminder emails at 6:00 AM using Amazon SES. Built for internal/professional use (hospital capacity reporting), not marketing. Uses Gravity Forms for subscriptions and provides simple unsubscribe functionality.

## Simplified Scope

- **No marketing features**: No A/B testing, engagement tracking, or double opt-in
- **Functional emails only**: Daily reminders for hospital staff to submit capacity data
- **Small scale**: ~46 subscribers, no performance optimization needed
- **Testing on Local WP first**: Build and test locally before deploying to production
- **Constant Contact backup**: Keep CC active during testing period

## Core Components

### 1. Plugin Structure

- Plugin folder: `wp-content/plugins/bcc-daily-mailer/`
- Main file: `bcc-daily-mailer.php` with WordPress plugin headers
- Simple class-based structure (no over-engineering)
- Activation hook to create database tables
- Deactivation hook to clean up cron events

### 2. Amazon SES Integration

- **Decision needed before coding**: Do you have WP Mail SMTP plugin configured for SES, or should we use AWS SDK directly?
  - **Option A**: Use existing WP Mail SMTP config (simpler, uses `wp_mail()`)
  - **Option B**: Use AWS SDK for PHP directly (more control, requires credentials in plugin)
- Email sending function with error handling
- Retry logic for transient failures
- **Assumption**: SES is production-ready (out of sandbox, domain verified with DKIM/SPF)

### 3. Database Schema

Single table: `wp_bcc_subscribers`

```sql
id (bigint, auto_increment, primary key)
email (varchar 255, unique, indexed)
status (varchar 20: 'active' or 'unsubscribed')
unsubscribe_token (varchar 64, unique, indexed)
subscribe_date (datetime)
unsubscribe_date (datetime, nullable)
created_at (datetime)
updated_at (datetime)
```

Optional logging table: `wp_bcc_email_logs`

```sql
id (bigint, auto_increment, primary key)
email (varchar 255)
status (varchar 20: 'sent' or 'failed')
error_message (text, nullable)
sent_at (datetime)
```

### 4. Subscriber Management

- Admin page: "BCC Daily Mailer" in WordPress admin menu
- Subscriber list view with search/filter
- Manual add/remove subscribers
- CSV import (upload file with email column)
- CSV export for backup
- Bulk actions: activate, deactivate, delete

### 5. Email Template

- Store in WordPress options: `bcc_email_settings`
- Editable fields in admin:
  - Email subject line
  - Greeting text
  - Main message body
  - Button 1 text + URL
  - Button 2 text + URL
  - Footer contact email
- HTML template with inline CSS (email-safe)
- Automatic unsubscribe link insertion
- Preview function in admin

### 6. Daily Cron Scheduling

- Register on plugin activation: `wp_schedule_event()`
- Recurrence: 'daily' at 6:00 AM Eastern Time
- Hook: `bcc_daily_mailer_send_emails`
- Callback function:

  1. Query all active subscribers
  2. Get email template from options
  3. Loop through subscribers
  4. Replace {unsubscribe_url} token with subscriber-specific link
  5. Send via SES
  6. Log result

- Manual "Send Now" button in admin for testing
- Display next scheduled send time in admin

### 7. Gravity Forms Integration

- Hook into Gravity Forms submission: `gform_after_submission`
- **Decision needed**: Which Gravity Form ID should we listen to?
- Extract email field from submission
- Validate email format
- Check for duplicates
- Generate unsubscribe token
- Insert into database with status 'active'
- **Fallback**: If no Gravity Form, provide simple shortcode form `[bcc_subscribe]`

### 8. Unsubscribe System

- URL structure: `https://mombaby.org/unsubscribe/?token={unique_token}`
- Handler uses `template_redirect` hook
- Process:

  1. Check if URL is `/unsubscribe/`
  2. Validate token parameter
  3. Look up subscriber by token
  4. Update status to 'unsubscribed'
  5. Set unsubscribe_date
  6. Display confirmation message (simple HTML page)

- No confirmation prompt (one-click unsubscribe)
- Unsubscribe link in every email footer

### 9. Admin Interface

- Main settings page with tabs:
  - **Dashboard**: Stats, last send time, manual send button
  - **Email Template**: Edit email content
  - **Subscribers**: List, search, import/export
  - **Settings**: SES config (if not using WP Mail SMTP), timezone, send time
  - **Logs**: Recent send history
- Simple WordPress admin styling (no custom CSS needed)

## Implementation Timeline (1 Hour)

### Phase 1: Foundation (15 min)

- Create plugin structure and main file
- Database table creation on activation
- Basic admin menu and settings page

### Phase 2: Email Sending (20 min)

- SES integration (wp_mail or SDK)
- Email template system with token replacement
- Test send function

### Phase 3: Cron + Subscribers (15 min)

- Register daily cron event
- Batch send logic
- Subscriber management (add/remove/list)

### Phase 4: Forms + Unsubscribe (10 min)

- Gravity Forms hook OR simple shortcode form
- Unsubscribe handler
- CSV import for existing 46 subscribers

## Critical Decisions Needed Before Coding

### Decision 1: SES Configuration Method

**Question**: Do you already have WP Mail SMTP (or similar) plugin configured for SES on mombaby.org?

- **If YES**: We'll use `wp_mail()` and leverage existing config (simpler)
- **If NO**: We'll use AWS SDK for PHP and store credentials in plugin settings

**Recommendation**: Use existing WP Mail SMTP if available - less code, easier to maintain

### Decision 2: Gravity Forms Setup

**Question**: Will you create the Gravity Form for the landing page, or should we build a fallback form?

- **Option A**: You create Gravity Form, we just hook into it (provide Form ID)
- **Option B**: We build simple shortcode form as primary method
- **Option C**: We build both (Gravity Forms preferred, shortcode as backup)

**Recommendation**: Option A - you handle Gravity Form design, we handle backend integration

### Decision 3: Timezone for 6:00 AM Send

**Question**: What timezone should 6:00 AM be in?

- Eastern Time (likely, since UNC Chapel Hill)
- Server time (WP Engine default)
- Configurable in admin

**Recommendation**: Hardcode Eastern Time initially, can make configurable later

### Decision 4: Email From Address

**Question**: What should the "From" address be?

- `cmih@med.unc.edu` (matches current CC emails)
- `noreply@mombaby.org`
- Other?

**Must be**: Verified in Amazon SES

## Key Risks & Mitigations

### Risk 1: SES Deliverability vs Constant Contact

**Concern**: SES may have lower deliverability than CC's established infrastructure

**Mitigation**:

- Run parallel with CC for 2 weeks minimum
- Monitor bounce rates in SES console
- Ensure SPF/DKIM/DMARC properly configured
- Ask recipients to whitelist sender
- Keep CC as rollback option

### Risk 2: WP-Cron Timing Precision

**Concern**: Even with WP Engine Alternate Cron, exact 6:00 AM not guaranteed

**Reality Check**:

- WP Engine Alternate Cron checks every minute ✅
- Will trigger between 6:00-6:01 AM (acceptable)
- Much more reliable than standard WP-Cron

**Mitigation**: Monitor logs for drift, alert if send happens after 6:15 AM

### Risk 3: Plugin Conflicts on Local WP

**Concern**: Local environment may not perfectly match production

**Mitigation**:

- Test SES sending from local (will work if credentials correct)
- Test cron timing manually (trigger via WP-CLI or admin button)
- Document any environment-specific settings
- Plan staging deployment before production

### Risk 4: Missing SES Credentials/Access

**Concern**: May not have SES credentials readily available

**Mitigation**:

- Identify this upfront (next decision point)
- Can build entire plugin and test with dummy credentials
- Only need real credentials for actual sending

## Testing Checklist (On Local WP)

- [ ] Plugin activates without errors
- [ ] Database tables created correctly
- [ ] Can add subscriber manually via admin
- [ ] Can import CSV with 46 emails
- [ ] Email template saves and loads from options
- [ ] Manual "Send Now" button sends test email via SES
- [ ] Unsubscribe link works and updates database
- [ ] Cron event registered (check with WP-CLI: `wp cron event list`)
- [ ] Gravity Form submission adds subscriber (if using GF)
- [ ] Logs capture send success/failure
- [ ] Admin dashboard displays correct stats

## Files to Create

```
wp-content/plugins/bcc-daily-mailer/
├── bcc-daily-mailer.php (main plugin file)
├── includes/
│   ├── class-database.php (table creation/queries)
│   ├── class-email-sender.php (SES integration)
│   ├── class-subscriber-manager.php (CRUD operations)
│   ├── class-cron-handler.php (scheduled sends)
│   └── class-unsubscribe-handler.php (token validation)
├── admin/
│   ├── class-admin-menu.php (admin pages)
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── email-template.php
│   │   ├── subscribers.php
│   │   └── settings.php
├── templates/
│   ├── email-template.html (HTML email)
│   └── unsubscribe-page.html (confirmation page)
└── assets/
    └── admin.css (minimal admin styling)
```

## Post-Build Next Steps

1. Test thoroughly on Local WP
2. Export plugin as ZIP
3. Install on WP Engine staging environment (if available)
4. Test with WP Engine Alternate Cron enabled
5. Import 46 subscribers
6. Run parallel with Constant Contact for 2 weeks
7. Monitor deliverability and timing
8. Decide on full migration or rollback

## Final Confirmation Needed

Before we start coding, please confirm:

1. **SES Setup**: Do you have WP Mail SMTP configured, or should we use AWS SDK directly?
2. **Gravity Form**: Will you create the form and provide the Form ID, or should we build a simple form?
3. **From Email**: What email address should send from? (must be SES-verified)
4. **Timezone**: 6:00 AM Eastern Time? (NC timezone)
5. **Access**: Will you have the Local WP site ready to share/work on?

Once confirmed, we can start building immediately.
```