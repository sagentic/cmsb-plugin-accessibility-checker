<?php
/*
Plugin Name: Accessibility Checker
Description: Scans database content fields for WCAG 2.1 AA violations including missing alt text, empty links, generic link text, skipped headings, and more
Version: 1.01
CMS Version Required: 3.50
Author: Sagentic Web Design
Author URI: https://www.sagentic.com
*/

// Plugin constants
$GLOBALS['ACCESSIBILITYCHECKER_PLUGIN'] = true;
$GLOBALS['ACCESSIBILITYCHECKER_VERSION'] = '1.01';

// Load helper functions
require_once __DIR__ . '/accessibilityChecker_functions.php';

// Register scheduled tasks (dispatched by webadmin/cron.php, which runs from the command line,
// so these must be registered before the command-line check below).
// Each site gets its own time between 0:00 and 4:59 so sites don't all scan at once;
// the scan itself only runs when it's due by the Scan Frequency setting.
$accessibilityChecker_slot = crc32(__DIR__) % 300;
addCronJob('accessibilityChecker_scheduledScan', 'Accessibility Checker - Scheduled Scan', sprintf('%d %d * * *', $accessibilityChecker_slot % 60, intdiv($accessibilityChecker_slot, 60)));
addCronJob('accessibilityChecker_cleanup', 'Accessibility Checker - Cleanup Old Results', sprintf('%d %d * * *', $accessibilityChecker_slot % 60, intdiv($accessibilityChecker_slot, 60) + 1));
unset($accessibilityChecker_slot);

// Don't register anything else from command-line
if (inCLI()) {
	return;
}

// Initialize plugin on admin login
addAction('admin_postlogin', 'accessibilityChecker_pluginInit', null, -999);

// Admin UI - only load when in admin area
if (defined('IS_CMS_ADMIN')) {
	require_once __DIR__ . '/accessibilityChecker_admin.php';

	// Register plugin menu pages
	pluginAction_addHandlerAndLink(t('Dashboard'), 'accessibilityChecker_adminDashboard', 'admins');
	pluginAction_addHandlerAndLink(t('Scan Results'), 'accessibilityChecker_adminResults', 'admins');
	pluginAction_addHandlerAndLink(t('Run Scan'), 'accessibilityChecker_adminRunScan', 'admins');
	pluginAction_addHandlerAndLink(t('Settings'), 'accessibilityChecker_adminSettings', 'admins');
	pluginAction_addHandlerAndLink(t('Scan History'), 'accessibilityChecker_adminHistory', 'admins');
	pluginAction_addHandlerAndLink(t('Help'), 'accessibilityChecker_adminHelp', 'admins');
}

/**
 * Initialize plugin - create database tables if needed
 */
function accessibilityChecker_pluginInit(): void
{
	// Create tables if they don't exist
	accessibilityChecker_createTablesIfNeeded();
}

/**
 * Process scheduled scan (called by daily cron)
 * Returns a summary for the Background Tasks log
 */
function accessibilityChecker_scheduledScan(): string
{
	accessibilityChecker_createTablesIfNeeded();
	$settings = accessibilityChecker_loadPluginSettings();

	if (!$settings['scheduledScans']) {
		return 'Scheduled scans disabled';
	}

	$lastScan = $settings['lastScanDate'];
	$frequency = $settings['scanFrequency'];

	// Check if scan is due
	$shouldScan = false;
	if (!$lastScan) {
		$shouldScan = true;
	} else {
		$lastScanTime = strtotime($lastScan);
		switch ($frequency) {
			case 'daily':
				$shouldScan = (time() - $lastScanTime) >= 86400;
				break;
			case 'weekly':
				$shouldScan = (time() - $lastScanTime) >= 604800;
				break;
			case 'monthly':
				$shouldScan = (time() - $lastScanTime) >= 2592000;
				break;
		}
	}

	if (!$shouldScan) {
		return "Not due (last scan {$lastScan}, {$frequency})";
	}

	$results = accessibilityChecker_runScheduledScan();
	accessibilityChecker_sendNotificationEmail($results);

	return sprintf('Scanned %d records in %d tables: %d errors, %d warnings',
		$results['recordsScanned'], $results['tablesScanned'], $results['errors'], $results['warnings']);
}

/**
 * Cleanup old results based on retention setting
 * Called by daily cron; returns a summary for the Background Tasks log
 */
function accessibilityChecker_cleanup(): string
{
	global $TABLE_PREFIX;

	accessibilityChecker_createTablesIfNeeded();
	$settings = accessibilityChecker_loadPluginSettings();
	$retentionDays = $settings['logRetentionDays'];
	if ($retentionDays <= 0) {
		return 'Retention disabled'; // Retention disabled
	}

	$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

	// Clean up results table (only fixed/ignored items older than retention)
	$query = "DELETE FROM `{$TABLE_PREFIX}_accessibilitychecker_results` WHERE `createdDate` < ? AND (`status` = 'fixed' OR `status` = 'ignored')";
	mysqli()->query(mysql_escapef($query, $cutoffDate));

	// Clean up scans table
	$query = "DELETE FROM `{$TABLE_PREFIX}_accessibilitychecker_scans` WHERE `createdDate` < ?";
	mysqli()->query(mysql_escapef($query, $cutoffDate));

	return "Removed results older than {$retentionDays} days";
}
