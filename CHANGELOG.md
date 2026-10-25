# Changelog

All notable changes to the Accessibility Checker plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.01] - 2026-10-24

### Fixed
- Scheduled scans and cleanup never ran from cron. They were registered below the plugin's command-line check, and `webadmin/cron.php` runs from the command line, so the jobs only appeared on the Background Tasks page. They are now registered before that check
- Notification emails now send through the CMS mail settings (`sendMessage()`) instead of PHP `mail()`
- The email subject and Dashboard link no longer depend on HTTP_HOST, which doesn't exist under cron. They use the CMS Website URL and Admin URL settings
- Both tasks create their tables first if they don't exist yet, and return a summary line for the Background Tasks log

### Changed
- Scheduled scan runs at a time unique to each site between 0:00 and 4:59 (cleanup an hour later), so sites on the same server don't all scan at once
- Default Scan Frequency is now weekly (was daily) and Email Notifications default to off (was on). Sites that saved their own settings keep them

## [1.00] - 2026-01-09

### Added
- Initial release
- **Dashboard** with at-a-glance statistics and recent issues
- **Scan Results** page with filtering, sorting, and bulk actions
- **Run Scan** page with Full, Quick, Selected Tables, Template Files, and Combined scan options
- **Settings** page for comprehensive configuration
- **Scan History** page showing all past scans
- **Help** page with documentation

### Accessibility Checks
- Missing alt text detection (Error)
- Poor alt text detection including filenames, too short, and generic patterns (Warning)
- Empty links without accessible text content (Error)
- Generic link text like "click here", "read more" (Warning)
- Skipped heading levels in content (Warning)
- Tables without header cells (Warning)
- Empty buttons without accessible names (Error)
- Form inputs missing associated labels (Error)

### Features
- Database content scanning across all CMS tables
- Template file scanning for PHP/HTML files in views, templates, includes directories
- Automatic scheduled scans (daily/weekly/monthly)
- Email notifications after scans
- Configurable ignore patterns for alt text and link text
- Table selection for targeted scanning
- Quick scan option for rechecking previous issues only
- Bulk actions: Mark Fixed, Mark Ignored, Recheck, Reopen
- Direct edit links to source records
- Issue status tracking (Open, Fixed, Ignored)
- Log retention with automatic cleanup
- Duplicate detection prevents repeat issues on rescan

### Security
- CSRF protection on all state-changing operations
- Input validation and sanitization
- SQL injection prevention with parameterized queries
- XSS protection with proper output encoding

### Accessibility (Plugin UI)
- ARIA labels on navigation and controls
- Proper table header scope attributes
- Screen reader text for icon-only buttons
- Keyboard accessible controls
- Proper heading hierarchy

## Requirements
- CMS Builder 3.50+
- PHP 8.0+
- MySQL 5.7+
