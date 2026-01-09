# Accessibility Checker Plugin for CMS Builder

> **Note:** This plugin only works with CMS Builder, available for download at https://www.interactivetools.com/download/

A comprehensive WCAG 2.1 AA compliance checker that scans your CMS content for accessibility violations and helps you fix them.

## Features

### Accessibility Checks Performed

| Check                      | Severity | WCAG Criterion               |
| -------------------------- | -------- | ---------------------------- |
| **Missing Alt Text**       | Error    | 1.1.1 Non-text Content       |
| **Poor Alt Text**          | Warning  | 1.1.1 Non-text Content       |
| **Empty Links**            | Error    | 2.4.4 Link Purpose           |
| **Generic Link Text**      | Warning  | 2.4.4 Link Purpose           |
| **Skipped Heading Levels** | Warning  | 1.3.1 Info and Relationships |
| **Tables Without Headers** | Warning  | 1.3.1 Info and Relationships |
| **Empty Buttons**          | Error    | 4.1.2 Name, Role, Value      |
| **Missing Form Labels**    | Error    | 1.3.1, 4.1.2                 |

### Check Details

#### Missing Alt Text (Error)

Detects `<img>` tags without an `alt` attribute. All images must have alt text to be accessible to screen reader users.

#### Poor Alt Text (Warning)

Identifies alt text that:

-   Appears to be a filename (ends in .jpg, .png, etc.)
-   Is too short (configurable minimum, default 5 characters)
-   Uses generic patterns like "image of", "photo", "IMG\_", etc.

#### Empty Links (Error)

Finds `<a>` tags with no accessible text content. Checks for:

-   Text content
-   aria-label attribute
-   title attribute (fallback)
-   Images with alt text inside the link

#### Generic Link Text (Warning)

Detects non-descriptive link text including:

-   "click here"
-   "read more"
-   "learn more"
-   "more", "link", "here", etc.

#### Skipped Heading Levels (Warning)

Identifies heading hierarchy that jumps levels (e.g., h2 directly to h4). Proper heading structure is essential for screen reader navigation.

#### Tables Without Headers (Warning)

Finds data tables that lack `<th>` elements. Layout tables with `role="presentation"` are excluded.

#### Empty Buttons (Error)

Detects buttons without accessible names:

-   `<button>` elements with no text content
-   `<input type="button/submit">` without value attribute
-   Missing aria-label or title fallbacks

#### Missing Form Labels (Error)

Identifies form inputs without associated labels:

-   text, email, password, tel, url, number inputs
-   textarea elements
-   select elements
    Checks for label elements, aria-label, aria-labelledby, and title attributes.

## Installation

### Method 1: Upload via FTP

1. Upload the `accessibilityChecker` folder to `/cmsb/plugins/`
2. Copy `accessibilitychecker_menu.schema.php` to `/cmsb/data/schema/accessibilitychecker.schema.php`
3. Log into the CMS admin area
4. The plugin will automatically initialize

### Method 2: Command Line

```bash
# Navigate to plugins directory
cd /path/to/cmsb/plugins/

# Extract plugin (if packaged)
tar -xzf accessibilityChecker_v1.0.tar.gz

# Copy menu schema
cp accessibilityChecker/accessibilitychecker_menu.schema.php ../data/schema/accessibilitychecker.schema.php

# Set permissions
chmod 755 accessibilityChecker/
chmod 644 accessibilityChecker/*.php
chmod 755 accessibilityChecker/pluginSchemas/
chmod 644 accessibilityChecker/pluginSchemas/*.php
```

## Usage

### Dashboard

The dashboard provides an at-a-glance view of your site's accessibility health:

-   Last scan date and results
-   Current issue counts (errors, warnings, fixed, ignored)
-   Quick actions for scanning
-   Recent issues table with direct edit links

### Running Scans

Five scan types are available:

1. **Full Scan** - Scans all content in all enabled database tables
2. **Quick Scan** - Only rechecks records with existing issues (faster)
3. **Selected Tables** - Scan specific database tables only
4. **Template Files Only** - Scans PHP/HTML template files for accessibility issues
5. **Combined Scan** - Scans both database content and template files

#### Template File Scanning

Template scanning checks your website's PHP and HTML template files for accessibility issues that wouldn't be caught by database content scanning. This is useful for finding issues in:

-   Navigation menus and headers
-   Footer content
-   Sidebar widgets
-   Layout templates
-   Include files

The scanner automatically searches these directories:

-   `views/`, `templates/`, `includes/`, `layouts/`
-   Theme directories
-   Custom template locations

For template file issues, results show the file path instead of a database record. These require manual editing in your code editor.

### Managing Results

From the Scan Results page you can:

-   Filter by severity, status, issue type, or table
-   Sort by any column
-   Bulk select issues for batch actions
-   Mark issues as Fixed, Ignored, or Reopen them
-   Click Edit to go directly to the source record

### Settings

Configure the plugin behavior:

-   **General Settings** - Enable/disable the plugin
-   **Scan Schedule** - Set automatic scan frequency (daily/weekly/monthly)
-   **Checks to Perform** - Toggle individual checks on/off
-   **Email Notifications** - Configure email alerts after scans
-   **Ignore Patterns** - Specify text patterns to ignore
-   **Tables to Scan** - Select which content tables to scan
-   **Log Retention** - Set how long to keep old results

## Settings File

Settings are stored in `accessibilityChecker_settings.json` within the plugin directory. This file is automatically created on first use.

```json
{
	"enabled": true,
	"scheduledScans": true,
	"scanFrequency": "daily",
	"checks": {
		"missingAlt": true,
		"poorAlt": true,
		"minAltLength": 5,
		"emptyLinks": true,
		"genericLinkText": true,
		"skippedHeadings": true,
		"tablesWithoutHeaders": true,
		"emptyButtons": true,
		"missingFormLabels": true
	},
	"emailNotifications": true,
	"notificationEmail": "",
	"emailOnlyOnErrors": true,
	"ignorePatterns": {
		"altText": ["decorative", "spacer"],
		"linkText": []
	},
	"enabledTables": [],
	"logRetentionDays": 90
}
```

## Database Tables

The plugin creates two database tables:

### `_accessibilitychecker_results`

Stores individual accessibility issues found during scans.

### `_accessibilitychecker_scans`

Stores scan history including dates, statistics, and duration.

## Cron Jobs

The plugin registers two cron jobs:

1. **Scheduled Scan** - Runs at midnight based on configured frequency
2. **Cleanup** - Runs at 1 AM daily to remove old results based on retention setting

## Excluded Tables

The following are automatically excluded from scanning:

-   System tables (starting with `_`)
-   `accounts`, `uploads`, `convert_to_webp`
-   `indexnow`, `indexnow_log`
-   `linkchecker_results`, `linkchecker_scans`
-   `accessibilitychecker_results`, `accessibilitychecker_scans`
-   Menu group tables

## Best Practices

### For Content Editors

1. **Always add alt text** to images that convey information
2. Use **empty alt=""** only for truly decorative images
3. Write **descriptive link text** that makes sense out of context
4. Use **proper heading hierarchy** (h1 -> h2 -> h3)
5. **Label all form fields** with visible labels

### For Developers

1. Run scans after major content imports
2. Configure email notifications to catch new issues
3. Review the "Poor Alt Text" warnings - they often indicate fixable issues
4. Use Quick Scan after fixing issues to verify corrections

## Troubleshooting

### Plugin not appearing in menu

1. Check that the schema file is copied to `/cmsb/data/schema/`
2. Clear any CMS caches
3. Log out and log back in

### Scan takes too long

1. Use "Selected Tables" to scan fewer tables
2. Reduce the number of checks enabled
3. Run scans during off-peak hours

### False positives

1. Add ignore patterns in Settings for intentionally short alt text
2. Use the Ignore feature for specific issues that are acceptable

## Requirements

-   CMS Builder 3.50 or higher
-   PHP 8.0 or higher
-   MySQL 5.7 or higher

## Version History

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## Author

Sagentic Web Design
https://www.sagentic.com

## License

MIT License - See [LICENSE](LICENSE) file for details.
