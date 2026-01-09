<?php

/**
 * Accessibility Checker Plugin - Admin UI Pages
 *
 * @package AccessibilityChecker
 */


/**
 * Generate plugin navigation bar
 *
 * @param string $currentPage Current page identifier
 * @return string HTML for navigation bar
 */
function accessibilityChecker_getPluginNav(string $currentPage): string
{
	$pages = [
		'dashboard' => ['label' => t('Dashboard'), 'action' => 'accessibilityChecker_adminDashboard'],
		'results' => ['label' => t('Scan Results'), 'action' => 'accessibilityChecker_adminResults'],
		'run_scan' => ['label' => t('Run Scan'), 'action' => 'accessibilityChecker_adminRunScan'],
		'settings' => ['label' => t('Settings'), 'action' => 'accessibilityChecker_adminSettings'],
		'history' => ['label' => t('History'), 'action' => 'accessibilityChecker_adminHistory'],
		'help' => ['label' => t('Help'), 'action' => 'accessibilityChecker_adminHelp'],
	];

	$html = '<nav aria-label="' . t('Accessibility Checker plugin navigation') . '"><div class="btn-group" role="group" style="margin-bottom:20px">';
	foreach ($pages as $key => $page) {
		$isActive = ($key === $currentPage);
		$btnClass = $isActive ? 'btn btn-primary' : 'btn btn-default';
		$ariaCurrent = $isActive ? ' aria-current="page"' : '';
		$html .= '<a href="?_pluginAction=' . urlencode($page['action']) . '" class="' . $btnClass . '"' . $ariaCurrent . '>' . $page['label'] . '</a>';
	}
	$html .= '</div></nav>';

	return $html;
}

/**
 * Get advanced actions menu array
 *
 * @return array Advanced actions array for adminUI
 */
function accessibilityChecker_getAdvancedActions(): array
{
	return [
		t('Quick Scan') => '?_pluginAction=accessibilityChecker_adminDashboard&_action=quickScan',
		t('Clear Old Scans') => '?_pluginAction=accessibilityChecker_adminDashboard&_action=clearOldScans',
		t('Clear All Results') => '?_pluginAction=accessibilityChecker_adminDashboard&_action=clearResults',
	];
}

/**
 * Dashboard page - Main plugin overview
 */
function accessibilityChecker_adminDashboard(): void
{
	global $SETTINGS;

	// Handle form submissions
	if (($_REQUEST['_action'] ?? '') === 'quickScan') {
		accessibilityChecker_runQuickScan();
		\alert(t('Quick scan completed'));
		\redirectBrowserToURL('?_pluginAction=' . __FUNCTION__);
	}

	if (($_REQUEST['_action'] ?? '') === 'clearOldScans') {
		accessibilityChecker_cleanupOldScans();
		\alert(t('Old scan history cleared'));
		\redirectBrowserToURL('?_pluginAction=' . __FUNCTION__);
	}

	if (($_REQUEST['_action'] ?? '') === 'clearResults') {
		if (($_REQUEST['_confirm'] ?? '') === 'yes') {
			$count = accessibilityChecker_clearAllResults();
			\alert(t('Cleared %d accessibility issues', $count));
			\redirectBrowserToURL('?_pluginAction=' . __FUNCTION__);
		} else {
			// Show confirmation page
			$adminUI = [];
			$adminUI['PAGE_TITLE'] = [
				t("Plugins") => '?menu=admin&action=plugins',
				t("Accessibility Checker") => '?_pluginAction=' . __FUNCTION__,
				t("Clear Results"),
			];

			$content = '';
			$content .= accessibilityChecker_getPluginNav('dashboard');
			$content .= '<div class="alert alert-warning" style="margin-bottom:20px">';
			$content .= '<h4 style="margin-top:0"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ' . t('Confirm Clear Results') . '</h4>';
			$content .= '<p>' . t('This will permanently delete ALL accessibility check results and scan history from the database. This provides a completely fresh start.') . '</p>';
			$content .= '<p><strong>' . t('This action cannot be undone.') . '</strong></p>';
			$content .= '</div>';

			$content .= '<p>';
			$content .= '<a href="?_pluginAction=' . __FUNCTION__ . '&_action=clearResults&_confirm=yes" class="btn btn-danger">';
			$content .= '<i class="fa fa-trash" aria-hidden="true"></i> ' . t('Yes, Clear All Results');
			$content .= '</a> ';
			$content .= '<a href="?_pluginAction=' . __FUNCTION__ . '" class="btn btn-default">' . t('Cancel') . '</a>';
			$content .= '</p>';

			$adminUI['CONTENT'] = $content;
			\adminUI($adminUI);
			return;
		}
	}

	// Get data for display
	$lastScan = accessibilityChecker_getLastScanInfo();
	$stats = accessibilityChecker_getScanStats();
	$recentIssues = accessibilityChecker_getRecentIssues(10);

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Accessibility Checker"),
	];

	$adminUI['ADVANCED_ACTIONS'] = accessibilityChecker_getAdvancedActions();

	$content = '';

	// Plugin navigation
	$content .= accessibilityChecker_getPluginNav('dashboard');

	// Load settings
	$pluginSettings = accessibilityChecker_loadPluginSettings();

	// Last Scan Status Section
	$content .= '<div class="separator"><div>' . t('Last Scan Status') . '</div></div>';

	$content .= '<p class="help-block" style="margin-top:0;margin-bottom:15px">' . t('Monitor your site\'s accessibility health at a glance. Results from your most recent scan are shown below.') . '</p>';

	$content .= '<div class="form-horizontal">';

	// Last Scan Date
	$content .= '<div class="form-group" style="margin-bottom:8px">';
	$content .= '<label class="col-sm-2 control-label" style="padding-top:0">' . t('Last Scan') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<p class="form-control-static" style="padding-top:0;padding-bottom:0;margin-bottom:0;min-height:0">';
	if ($lastScan) {
		$content .= '<strong>' . date('Y-m-d H:i:s', strtotime($lastScan['startTime'])) . '</strong>';
		$scanAge = time() - strtotime($lastScan['startTime']);
		if ($scanAge > 604800) { // 1 week
			$content .= ' <span class="badge" style="background-color:#dc3545;color:#fff;margin-left:8px">' . t('Scan Overdue') . '</span>';
		}
	} else {
		$content .= '<span style="color:#6c757d">' . t('No scans yet') . '</span>';
	}
	$content .= '</p></div></div>';

	// Records Scanned
	$content .= '<div class="form-group" style="margin-bottom:8px">';
	$content .= '<label class="col-sm-2 control-label" style="padding-top:0">' . t('Records Scanned') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<p class="form-control-static" style="padding-top:0;padding-bottom:0;margin-bottom:0;min-height:0"><strong>' . ($lastScan ? intval($lastScan['recordsScanned']) : 0) . '</strong></p>';
	$content .= '</div></div>';

	// Issues Found
	$content .= '<div class="form-group" style="margin-bottom:8px">';
	$content .= '<label class="col-sm-2 control-label" style="padding-top:0">' . t('Issues Found') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<p class="form-control-static" style="padding-top:0;padding-bottom:0;margin-bottom:0;min-height:0">';
	$issuesFound = $lastScan ? (intval($lastScan['errorsFound']) + intval($lastScan['warningsFound'])) : 0;
	if ($lastScan && $issuesFound > 0) {
		$content .= '<strong style="color:#dc3545">' . $issuesFound . '</strong>';
	} else {
		$content .= '<strong style="color:#28a745">' . $issuesFound . '</strong>';
	}
	$content .= '</p></div></div>';

	// Auto Scan Status
	$content .= '<div class="form-group" style="margin-bottom:8px">';
	$content .= '<label class="col-sm-2 control-label" style="padding-top:0">' . t('Auto Scan') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<p class="form-control-static" style="padding-top:0;padding-bottom:0;margin-bottom:0;min-height:0">';
	if ($pluginSettings['scheduledScans'] ?? false) {
		$content .= '<strong style="color:#28a745"><i class="fa-duotone fa-solid fa-check" aria-hidden="true"></i> ' . t('Enabled') . '</strong>';
		$content .= ' <span class="text-muted" style="margin-left:8px">(' . \htmlencode($pluginSettings['scanFrequency']) . ')</span>';
	} else {
		$content .= '<strong style="color:#dc3545"><i class="fa-duotone fa-solid fa-xmark" aria-hidden="true"></i> ' . t('Disabled') . '</strong>';
	}
	$content .= '</p></div></div>';

	$content .= '</div>'; // end form-horizontal

	// Statistics Section
	$content .= '<div class="separator"><div>' . t('Issue Statistics') . '</div></div>';

	$content .= '<div class="row g-3 mb-4">';

	// Helper function for stat card
	$renderStatCard = function ($label, $count, $color) {
		$html = '<div class="col-6 col-lg-3">';
		$html .= '<div class="border rounded-3 p-3 h-100 text-center">';
		$html .= '<div class="text-uppercase small fw-semibold mb-3">' . $label . '</div>';
		$html .= '<div class="fs-2 fw-bold" style="color:' . $color . '">' . $count . '</div>';
		$html .= '</div></div>';
		return $html;
	};

	$content .= $renderStatCard(t('Errors'), $stats['errors'], '#dc3545');
	$content .= $renderStatCard(t('Warnings'), $stats['warnings'], '#ffc107');
	$content .= $renderStatCard(t('Fixed'), $stats['fixed'], '#28a745');
	$content .= $renderStatCard(t('Ignored'), $stats['ignored'], '#6c757d');

	$content .= '</div>';

	// Quick Actions Section
	$content .= '<div class="separator"><div>' . t('Quick Actions') . '</div></div>';

	$content .= '<p class="help-block" style="margin-top:0;margin-bottom:15px">' . t('Quick Scan only rechecks records with existing issues, making it faster than a full scan. Use this to verify if reported issues have been fixed.') . '</p>';

	$content .= '<div style="margin-bottom:20px">';
	$content .= '<a href="?_pluginAction=' . __FUNCTION__ . '&_action=quickScan" class="btn btn-primary"><i class="fa-duotone fa-solid fa-magnifying-glass" aria-hidden="true"></i> ' . t('Run Quick Scan') . '</a> ';
	$content .= '<a href="?_pluginAction=accessibilityChecker_adminResults" class="btn btn-default"><i class="fa-duotone fa-solid fa-list" aria-hidden="true"></i> ' . t('View All Results') . '</a> ';
	$content .= '<a href="?_pluginAction=accessibilityChecker_adminSettings" class="btn btn-default"><i class="fa-duotone fa-solid fa-gear" aria-hidden="true"></i> ' . t('Settings') . '</a>';
	$content .= '</div>';

	// Recent Issues Section
	$content .= '<div class="separator"><div>' . t('Recent Issues') . '</div></div>';

	if (empty($recentIssues)) {
		$content .= '<p style="color:#28a745"><i class="fa-duotone fa-solid fa-check-circle" aria-hidden="true"></i> ' . t('No accessibility issues found!') . '</p>';
	} else {
		// Status legend
		$content .= '<div class="alert alert-info" style="margin-bottom:15px;padding:10px 15px">';
		$content .= '<strong>' . t('Severity Guide:') . '</strong> ';
		$content .= '<span class="badge" style="background-color:#dc3545;color:#fff;margin:0 5px">' . t('Error') . '</span> ' . t('Critical issues that must be fixed') . ' &bull; ';
		$content .= '<span class="badge" style="background-color:#ffc107;color:#212529;margin:0 5px">' . t('Warning') . '</span> ' . t('Best practice improvements');
		$content .= '</div>';
		$content .= '<div class="table-responsive">';
		$content .= '<table class="table table-striped table-hover">';
		$content .= '<thead><tr>';
		$content .= '<th scope="col">' . t('Issue Type') . '</th>';
		$content .= '<th scope="col">' . t('Found In') . '</th>';
		$content .= '<th scope="col">' . t('Severity') . '</th>';
		$content .= '<th scope="col">' . t('Detected') . '</th>';
		$content .= '<th scope="col" class="text-center">' . t('Actions') . '</th>';
		$content .= '</tr></thead><tbody>';

		foreach ($recentIssues as $issue) {
			$severityBadge = match ($issue['severity']) {
				'error' => '<span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span>',
				'warning' => '<span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span>',
				default => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Unknown') . '</span>',
			};

			// Construct source description
			$sourceDesc = $issue['tableName'];
			if ($issue['fieldName']) {
				$sourceDesc .= ' (' . $issue['fieldName'] . ')';
			}
			if ($issue['recordNum']) {
				$sourceDesc .= ' #' . $issue['recordNum'];
			}

			$content .= '<tr>';
			$content .= '<td>' . \htmlencode(accessibilityChecker_getIssueTypeLabel($issue['issueType'])) . '</td>';
			$content .= '<td class="text-truncate" style="max-width:250px" title="' . \htmlencode($sourceDesc) . '">';
			$content .= \htmlencode($sourceDesc);
			$content .= '</td>';
			$content .= '<td>' . $severityBadge . '</td>';
			$content .= '<td class="text-nowrap">' . date('Y-m-d H:i', strtotime($issue['scanDate'])) . '</td>';
			$content .= '<td class="text-center text-nowrap">';
			if ($issue['tableName'] && $issue['recordNum']) {
				$content .= '<a href="?menu=' . urlencode($issue['tableName']) . '&action=edit&num=' . intval($issue['recordNum']) . '" class="btn btn-xs btn-primary" target="_blank" rel="noopener" title="' . t('Edit Record') . '"><i class="fa-duotone fa-solid fa-edit" aria-hidden="true"></i> <span class="sr-only">' . t('Edit Record (opens in new tab)') . '</span></a> ';
			}
			$content .= '<button type="button" class="btn btn-xs btn-default" onclick="recheckSingle(' . intval($issue['num']) . ')" title="' . t('Recheck') . '"><i class="fa-duotone fa-solid fa-rotate" aria-hidden="true"></i> <span class="sr-only">' . t('Recheck') . '</span></button> ';
			$content .= '<button type="button" class="btn btn-xs btn-warning" onclick="ignoreSingle(' . intval($issue['num']) . ')" title="' . t('Ignore') . '"><i class="fa-duotone fa-solid fa-eye-slash" aria-hidden="true"></i> <span class="sr-only">' . t('Ignore') . '</span></button>';
			$content .= '</td>';
			$content .= '</tr>';
		}

		$content .= '</tbody></table></div>';

		$content .= '<p style="margin-top:10px"><a href="?_pluginAction=accessibilityChecker_adminResults&filterSeverity=error">' . t('View all errors') . ' &raquo;</a></p>';
	}

	// JavaScript for single actions
	$csrfToken = $_SESSION['_CSRFToken'] ?? '';
	$content .= '<script>
var csrfToken = ' . json_encode($csrfToken) . ';

function recheckSingle(issueId) {
	if (confirm("' . t('Recheck this issue now?') . '")) {
		submitSingleAction("recheck", issueId);
	}
}

function ignoreSingle(issueId) {
	if (confirm("' . t('Mark this issue as ignored?') . '")) {
		submitSingleAction("markIgnored", issueId);
	}
}

function submitSingleAction(action, issueId) {
	var form = document.createElement("form");
	form.method = "POST";
	form.action = "?_pluginAction=accessibilityChecker_adminResults";

	var inputs = [
		{name: "_pluginAction", value: "accessibilityChecker_adminResults"},
		{name: "bulkAction", value: action},
		{name: "selectedIssues[]", value: issueId},
		{name: "_CSRFToken", value: csrfToken}
	];

	inputs.forEach(function(input) {
		var hiddenField = document.createElement("input");
		hiddenField.type = "hidden";
		hiddenField.name = input.name;
		hiddenField.value = input.value;
		form.appendChild(hiddenField);
	});

	document.body.appendChild(form);
	form.submit();
}
</script>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * Scan Results page - View all accessibility issues
 */
function accessibilityChecker_adminResults(): void
{
	// Handle bulk actions
	if (($_REQUEST['bulkAction'] ?? '') && !empty($_REQUEST['selectedIssues'])) {
		\security_dieOnInvalidCsrfToken(); // CSRF protection

		if (!is_array($_REQUEST['selectedIssues'])) {
			\alert(t('Invalid input'));
			\redirectBrowserToURL('?_pluginAction=accessibilityChecker_adminResults');
		}

		$action = $_REQUEST['bulkAction'];
		$selectedIds = array_map('intval', $_REQUEST['selectedIssues']);

		if ($action === 'markFixed') {
			accessibilityChecker_markIssuesAsFixed($selectedIds);
			\alert(sprintf(t('%d issues marked as fixed'), count($selectedIds)));
		} elseif ($action === 'markIgnored') {
			accessibilityChecker_markIssuesAsIgnored($selectedIds);
			\alert(sprintf(t('%d issues marked as ignored'), count($selectedIds)));
		} elseif ($action === 'recheck') {
			$result = accessibilityChecker_recheckIssues($selectedIds);
			\alert(sprintf(t('Rechecked %d issues, %d fixed'), $result['rechecked'], $result['fixed']));
		} elseif ($action === 'reopen') {
			accessibilityChecker_reopenIssues($selectedIds);
			\alert(sprintf(t('%d issues reopened'), count($selectedIds)));
		}

		\redirectBrowserToURL('?_pluginAction=accessibilityChecker_adminResults');
	}

	// Pagination
	$page = max(1, intval($_REQUEST['page'] ?? 1));
	$perPage = intval($_REQUEST['perPage'] ?? 50);
	$perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;
	$offset = ($page - 1) * $perPage;

	// Filters
	$filterSeverity = $_REQUEST['filterSeverity'] ?? '';
	$filterStatus = $_REQUEST['filterStatus'] ?? '';
	$filterType = $_REQUEST['filterType'] ?? '';
	$filterTable = $_REQUEST['filterTable'] ?? '';

	// Sorting
	$sortBy = $_REQUEST['sortBy'] ?? 'scanDate';
	$sortDir = $_REQUEST['sortDir'] ?? 'DESC';
	$sortDir = in_array(strtoupper($sortDir), ['ASC', 'DESC']) ? strtoupper($sortDir) : 'DESC';

	// Build where clause
	$where = "1=1";
	if ($filterSeverity) {
		$where .= " AND `severity` = '" . \mysql_escape($filterSeverity) . "'";
	}
	if ($filterStatus) {
		$where .= " AND `status` = '" . \mysql_escape($filterStatus) . "'";
	}
	if ($filterType) {
		$where .= " AND `issueType` = '" . \mysql_escape($filterType) . "'";
	}
	if ($filterTable) {
		$where .= " AND `tableName` = '" . \mysql_escape($filterTable) . "'";
	}

	// Get total count
	$totalCount = \mysql_count('_accessibilitychecker_results', $where);
	$totalPages = ceil($totalCount / $perPage);

	// Valid sort columns with mapping
	$sortColumnMap = [
		'issueType' => '`issueType`',
		'severity' => '`severity`',
		'scanDate' => '`scanDate`',
		'tableName' => '`tableName`',
		'status' => '`status`',
	];
	$sortColumn = $sortColumnMap[$sortBy] ?? '`scanDate`';

	// Get results
	$results = \mysql_select('_accessibilitychecker_results', "{$where} ORDER BY {$sortColumn} {$sortDir} LIMIT {$offset}, {$perPage}");

	// Get available tables for filter
	$availableTables = accessibilityChecker_getEnabledTables();

	// Issue types for filter
	$issueTypes = [
		'missing_alt' => t('Missing Alt Text'),
		'poor_alt' => t('Poor Alt Text'),
		'empty_link' => t('Empty Link'),
		'generic_link' => t('Generic Link Text'),
		'skipped_heading' => t('Skipped Heading'),
		'table_no_headers' => t('Table Without Headers'),
		'empty_button' => t('Empty Button'),
		'missing_label' => t('Missing Form Label'),
	];

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Accessibility Checker") => '?_pluginAction=accessibilityChecker_adminDashboard',
		t("Scan Results"),
	];

	$adminUI['ADVANCED_ACTIONS'] = accessibilityChecker_getAdvancedActions();

	$content = '';

	// Plugin navigation
	$content .= accessibilityChecker_getPluginNav('results');

	// Filters Section
	$content .= '<div class="separator"><div>' . t('Filter Results') . '</div></div>';

	$content .= '<p class="help-block" style="margin-top:0;margin-bottom:15px">' . t('Use filters to narrow down results. Multiple filters can be combined to find specific issues.') . '</p>';

	$content .= '<form method="get" id="filterForm">';
	$content .= '<input type="hidden" name="_pluginAction" value="accessibilityChecker_adminResults">';
	$content .= '<div class="form-horizontal">';

	// Severity filter
	$content .= '<div class="form-group">';
	$content .= '<label for="filterSeverity" class="col-sm-2 control-label">' . t('Severity') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="filterSeverity" id="filterSeverity" class="form-control" style="width:200px;display:inline-block">';
	$content .= '<option value="">' . t('All') . '</option>';
	$content .= '<option value="error"' . ($filterSeverity === 'error' ? ' selected' : '') . '>' . t('Errors') . '</option>';
	$content .= '<option value="warning"' . ($filterSeverity === 'warning' ? ' selected' : '') . '>' . t('Warnings') . '</option>';
	$content .= '</select>';
	$content .= '</div></div>';

	// Status filter
	$content .= '<div class="form-group">';
	$content .= '<label for="filterStatus" class="col-sm-2 control-label">' . t('Status') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="filterStatus" id="filterStatus" class="form-control" style="width:200px;display:inline-block">';
	$content .= '<option value="">' . t('All') . '</option>';
	$content .= '<option value="open"' . ($filterStatus === 'open' ? ' selected' : '') . '>' . t('Open') . '</option>';
	$content .= '<option value="fixed"' . ($filterStatus === 'fixed' ? ' selected' : '') . '>' . t('Fixed') . '</option>';
	$content .= '<option value="ignored"' . ($filterStatus === 'ignored' ? ' selected' : '') . '>' . t('Ignored') . '</option>';
	$content .= '</select>';
	$content .= '</div></div>';

	// Issue Type filter
	$content .= '<div class="form-group">';
	$content .= '<label for="filterType" class="col-sm-2 control-label">' . t('Issue Type') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="filterType" id="filterType" class="form-control" style="width:250px;display:inline-block">';
	$content .= '<option value="">' . t('All') . '</option>';
	foreach ($issueTypes as $typeKey => $typeLabel) {
		$content .= '<option value="' . \htmlencode($typeKey) . '"' . ($filterType === $typeKey ? ' selected' : '') . '>' . \htmlencode($typeLabel) . '</option>';
	}
	$content .= '</select>';
	$content .= '</div></div>';

	// Table filter
	$content .= '<div class="form-group">';
	$content .= '<label for="filterTable" class="col-sm-2 control-label">' . t('Table') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="filterTable" id="filterTable" class="form-control" style="width:250px;display:inline-block">';
	$content .= '<option value="">' . t('All Tables') . '</option>';
	$content .= '<option value="_template_file"' . ($filterTable === '_template_file' ? ' selected' : '') . '>' . t('Template Files') . '</option>';
	foreach ($availableTables as $table) {
		$schema = \loadSchema($table);
		$menuName = $schema['menuName'] ?? $table;
		$selected = ($filterTable === $table) ? ' selected' : '';
		$content .= '<option value="' . \htmlencode($table) . '"' . $selected . '>' . \htmlencode($menuName) . ' (' . \htmlencode($table) . ')</option>';
	}
	$content .= '</select>';
	$content .= '</div></div>';

	// Per page
	$content .= '<div class="form-group">';
	$content .= '<label for="perPage" class="col-sm-2 control-label">' . t('Per Page') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="perPage" id="perPage" class="form-control" style="width:100px;display:inline-block">';
	foreach ([10, 25, 50, 100, 250] as $pp) {
		$content .= '<option value="' . $pp . '"' . ($perPage === $pp ? ' selected' : '') . '>' . $pp . '</option>';
	}
	$content .= '</select>';
	$content .= '</div></div>';

	// Buttons
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label"></div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<button type="submit" class="btn btn-primary">' . t('Filter') . '</button>';
	$content .= ' <a href="?_pluginAction=accessibilityChecker_adminResults" class="btn btn-default">' . t('Reset') . '</a>';
	$content .= '</div></div>';

	$content .= '</div>'; // end form-horizontal
	$content .= '</form>';

	// Results count and bulk actions
	$content .= '<div style="margin-top:20px;margin-bottom:15px">';
	$content .= '<div class="row">';
	$content .= '<div class="col-sm-6">';
	$content .= '<p>' . sprintf(t('Showing %d - %d of %d entries'), min($offset + 1, $totalCount), min($offset + $perPage, $totalCount), $totalCount) . '</p>';
	$content .= '</div>';
	$content .= '<div class="col-sm-6 text-right">';
	$content .= '<form method="post" id="bulkActionForm" style="display:inline-block">';
	$content .= '<input type="hidden" name="_pluginAction" value="accessibilityChecker_adminResults">';
	$content .= '<input type="hidden" name="_CSRFToken" value="' . \htmlencode($_SESSION['_CSRFToken'] ?? '') . '">';
	$content .= '<select name="bulkAction" id="bulkAction" class="form-control" style="width:150px;display:inline-block;margin-right:5px">';
	$content .= '<option value="">' . t('Bulk Actions') . '</option>';
	$content .= '<option value="markFixed">' . t('Mark as Fixed') . '</option>';
	$content .= '<option value="markIgnored">' . t('Mark as Ignored') . '</option>';
	$content .= '<option value="recheck">' . t('Recheck Now') . '</option>';
	$content .= '<option value="reopen">' . t('Reopen') . '</option>';
	$content .= '</select>';
	$content .= '<button type="submit" class="btn btn-default" onclick="return confirmBulkAction()">' . t('Apply') . '</button>';
	$content .= '</form>';
	$content .= '</div>';
	$content .= '</div>';
	$content .= '</div>';

	if (empty($results)) {
		if ($filterSeverity || $filterStatus || $filterType || $filterTable) {
			// Filters are active but no results
			$content .= '<div class="alert alert-info">';
			$content .= '<strong>' . t('No issues match your current filters.') . '</strong><br>';
			$content .= t('Try adjusting your filter criteria or') . ' <a href="?_pluginAction=accessibilityChecker_adminResults">' . t('reset filters') . '</a> ' . t('to see all results.');
			$content .= '</div>';
		} else {
			// No results at all - good news!
			$content .= '<div class="alert alert-success">';
			$content .= '<i class="fa-duotone fa-solid fa-check-circle" aria-hidden="true"></i> ';
			$content .= '<strong>' . t('Excellent! No accessibility issues found.') . '</strong><br>';
			$content .= t('Your site\'s content is accessible. Run a scan to check for new issues.');
			$content .= '</div>';
		}
	} else {
		// Helper function for sort link
		$sortLink = function ($column, $label) use ($filterSeverity, $filterStatus, $filterType, $filterTable, $perPage, $sortBy, $sortDir) {
			$url = '?_pluginAction=accessibilityChecker_adminResults';
			if ($filterSeverity) $url .= '&filterSeverity=' . urlencode($filterSeverity);
			if ($filterStatus) $url .= '&filterStatus=' . urlencode($filterStatus);
			if ($filterType) $url .= '&filterType=' . urlencode($filterType);
			if ($filterTable) $url .= '&filterTable=' . urlencode($filterTable);
			$url .= '&perPage=' . $perPage;
			$url .= '&sortBy=' . $column;

			$newDir = 'ASC';
			$icon = '';
			if ($sortBy === $column) {
				$newDir = ($sortDir === 'ASC') ? 'DESC' : 'ASC';
				$icon = ($sortDir === 'ASC') ? ' <i class="fa-duotone fa-solid fa-arrow-up" aria-hidden="true"></i>' : ' <i class="fa-duotone fa-solid fa-arrow-down" aria-hidden="true"></i>';
			}
			$url .= '&sortDir=' . $newDir;

			return '<a href="' . $url . '">' . $label . $icon . '</a>';
		};

		$content .= '<div class="table-responsive">';
		$content .= '<table class="table table-striped table-hover">';
		$content .= '<thead><tr>';
		$content .= '<th scope="col" style="width:40px" class="text-center"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" aria-label="' . t('Select all issues') . '"></th>';
		$content .= '<th scope="col">' . $sortLink('issueType', t('Issue Type')) . '</th>';
		$content .= '<th scope="col">' . $sortLink('tableName', t('Found In')) . '</th>';
		$content .= '<th scope="col">' . $sortLink('severity', t('Severity')) . '</th>';
		$content .= '<th scope="col">' . $sortLink('status', t('Status')) . '</th>';
		$content .= '<th scope="col">' . $sortLink('scanDate', t('Detected')) . '</th>';
		$content .= '<th scope="col" class="text-center">' . t('Actions') . '</th>';
		$content .= '</tr></thead><tbody>';

		foreach ($results as $result) {
			$severityBadge = match ($result['severity']) {
				'error' => '<span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span>',
				'warning' => '<span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span>',
				default => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Unknown') . '</span>',
			};

			$statusBadge = match ($result['status']) {
				'open' => '<span class="badge" style="background-color:#17a2b8;color:#fff">' . t('Open') . '</span>',
				'fixed' => '<span class="badge" style="background-color:#28a745;color:#fff">' . t('Fixed') . '</span>',
				'ignored' => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Ignored') . '</span>',
				default => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Unknown') . '</span>',
			};

			// Construct source description
			if ($result['tableName'] === '_template_file') {
				// Template file - show the file path nicely
				$sourceDesc = t('Template') . ': ' . $result['fieldName'];
			} else {
				$sourceDesc = $result['tableName'];
				if ($result['fieldName']) {
					$sourceDesc .= ' (' . $result['fieldName'] . ')';
				}
				if ($result['recordNum']) {
					$sourceDesc .= ' #' . $result['recordNum'];
				}
			}

			$content .= '<tr>';
			$content .= '<td class="text-center"><input type="checkbox" name="selectedIssues[]" value="' . intval($result['num']) . '" class="issue-checkbox" form="bulkActionForm" aria-label="' . t('Select issue') . '"></td>';
			$content .= '<td>';
			$content .= \htmlencode(accessibilityChecker_getIssueTypeLabel($result['issueType']));
			$content .= '<br><small class="text-muted" title="' . \htmlencode($result['message']) . '">' . \htmlencode(mb_substr($result['message'], 0, 50)) . (strlen($result['message']) > 50 ? '...' : '') . '</small>';
			$content .= '</td>';
			$content .= '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' . \htmlencode($sourceDesc) . '">';
			$content .= \htmlencode($sourceDesc);
			$content .= '</td>';
			$content .= '<td>' . $severityBadge . '</td>';
			$content .= '<td>' . $statusBadge . '</td>';
			$content .= '<td class="text-nowrap">' . date('Y-m-d H:i', strtotime($result['scanDate'])) . '</td>';
			$content .= '<td class="text-center text-nowrap">';
			if ($result['tableName'] && $result['tableName'] !== '_template_file' && $result['recordNum']) {
				// Show edit button only for database records, not template files
				$content .= '<a href="?menu=' . urlencode($result['tableName']) . '&action=edit&num=' . intval($result['recordNum']) . '" class="btn btn-xs btn-primary" target="_blank" rel="noopener" title="' . t('Edit Record') . '"><i class="fa-duotone fa-solid fa-edit" aria-hidden="true"></i> <span class="sr-only">' . t('Edit Record (opens in new tab)') . '</span></a> ';
			} elseif ($result['tableName'] === '_template_file') {
				// For template files, show a view file button with path tooltip
				$content .= '<span class="btn btn-xs btn-default disabled" title="' . t('Edit template file:') . ' ' . \htmlencode($result['fieldName']) . '"><i class="fa-duotone fa-solid fa-file-code" aria-hidden="true"></i> <span class="sr-only">' . t('Template file') . '</span></span> ';
			}
			$content .= '<button type="button" class="btn btn-xs btn-default" onclick="recheckSingle(' . intval($result['num']) . ')" title="' . t('Recheck') . '"><i class="fa-duotone fa-solid fa-rotate" aria-hidden="true"></i> <span class="sr-only">' . t('Recheck') . '</span></button> ';
			if ($result['status'] === 'open') {
				$content .= '<button type="button" class="btn btn-xs btn-warning" onclick="ignoreSingle(' . intval($result['num']) . ')" title="' . t('Ignore') . '"><i class="fa-duotone fa-solid fa-eye-slash" aria-hidden="true"></i> <span class="sr-only">' . t('Ignore') . '</span></button>';
			} else {
				$content .= '<button type="button" class="btn btn-xs btn-info" onclick="reopenSingle(' . intval($result['num']) . ')" title="' . t('Reopen') . '"><i class="fa-duotone fa-solid fa-rotate-left" aria-hidden="true"></i> <span class="sr-only">' . t('Reopen') . '</span></button>';
			}
			$content .= '</td>';
			$content .= '</tr>';
		}

		$content .= '</tbody></table></div>';

		// Pagination
		if ($totalPages > 1) {
			$baseUrl = '?_pluginAction=accessibilityChecker_adminResults';
			if ($filterSeverity) $baseUrl .= '&filterSeverity=' . urlencode($filterSeverity);
			if ($filterStatus) $baseUrl .= '&filterStatus=' . urlencode($filterStatus);
			if ($filterType) $baseUrl .= '&filterType=' . urlencode($filterType);
			if ($filterTable) $baseUrl .= '&filterTable=' . urlencode($filterTable);
			$baseUrl .= '&perPage=' . $perPage;
			$baseUrl .= '&sortBy=' . urlencode($sortBy) . '&sortDir=' . urlencode($sortDir);

			$content .= '<div class="text-center" style="margin-top:15px">';

			// Previous
			if ($page > 1) {
				$content .= '<a href="' . $baseUrl . '&page=' . ($page - 1) . '" class="btn btn-default btn-sm">&laquo; ' . t('Previous') . '</a> ';
			}

			// Page numbers
			$startPage = max(1, $page - 2);
			$endPage = min($totalPages, $page + 2);

			for ($i = $startPage; $i <= $endPage; $i++) {
				if ($i === $page) {
					$content .= '<span class="btn btn-primary btn-sm">' . $i . '</span> ';
				} else {
					$content .= '<a href="' . $baseUrl . '&page=' . $i . '" class="btn btn-default btn-sm">' . $i . '</a> ';
				}
			}

			// Next
			if ($page < $totalPages) {
				$content .= '<a href="' . $baseUrl . '&page=' . ($page + 1) . '" class="btn btn-default btn-sm">' . t('Next') . ' &raquo;</a>';
			}

			$content .= '</div>';
		}
	}

	// JavaScript for bulk actions and select all
	$csrfToken = $_SESSION['_CSRFToken'] ?? '';
	$content .= '<script>
var csrfToken = ' . json_encode($csrfToken) . ';

function toggleSelectAll(checkbox) {
	var checkboxes = document.querySelectorAll(".issue-checkbox");
	checkboxes.forEach(function(cb) {
		cb.checked = checkbox.checked;
	});
}

function confirmBulkAction() {
	var selected = document.querySelectorAll(".issue-checkbox:checked");
	if (selected.length === 0) {
		alert("' . t('Please select at least one issue') . '");
		return false;
	}
	var action = document.getElementById("bulkAction").value;
	if (!action) {
		alert("' . t('Please select an action') . '");
		return false;
	}
	return confirm("' . t('Apply this action to') . ' " + selected.length + " ' . t('selected issues?') . '");
}

function recheckSingle(issueId) {
	if (confirm("' . t('Recheck this issue now?') . '")) {
		submitSingleAction("recheck", issueId);
	}
}

function ignoreSingle(issueId) {
	if (confirm("' . t('Mark this issue as ignored?') . '")) {
		submitSingleAction("markIgnored", issueId);
	}
}

function reopenSingle(issueId) {
	if (confirm("' . t('Reopen this issue?') . '")) {
		submitSingleAction("reopen", issueId);
	}
}

function submitSingleAction(action, issueId) {
	var form = document.createElement("form");
	form.method = "POST";
	form.action = "?_pluginAction=accessibilityChecker_adminResults";

	var inputs = [
		{name: "_pluginAction", value: "accessibilityChecker_adminResults"},
		{name: "bulkAction", value: action},
		{name: "selectedIssues[]", value: issueId},
		{name: "_CSRFToken", value: csrfToken}
	];

	inputs.forEach(function(input) {
		var hiddenField = document.createElement("input");
		hiddenField.type = "hidden";
		hiddenField.name = input.name;
		hiddenField.value = input.value;
		form.appendChild(hiddenField);
	});

	document.body.appendChild(form);
	form.submit();
}
</script>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * Run Scan page - Manual scan execution
 */
function accessibilityChecker_adminRunScan(): void
{
	$message = '';
	$messageType = 'info';
	$scanResults = null;

	// Handle form submission
	if (($_REQUEST['runScan'] ?? '')) {
		\security_dieOnInvalidCsrfToken(); // CSRF protection

		$scanType = $_REQUEST['scanType'] ?? 'full';
		$selectedTables = $_REQUEST['selectedTables'] ?? [];
		$includeTemplates = !empty($_REQUEST['includeTemplates']);

		// Validate selectedTables is array
		if (!is_array($selectedTables)) {
			$selectedTables = [];
		}

		if ($scanType === 'selected' && empty($selectedTables)) {
			$message = t('Please select at least one table to scan');
			$messageType = 'warning';
		} elseif ($scanType === 'templates') {
			// Template-only scan
			$scanResults = accessibilityChecker_runTemplateScan();

			$issuesFound = ($scanResults['errors'] ?? 0) + ($scanResults['warnings'] ?? 0);
			$message = sprintf(
				t('Template scan completed. Scanned %d files, found %d issues.'),
				$scanResults['filesScanned'] ?? 0,
				$issuesFound
			);
			$messageType = ($issuesFound > 0) ? 'warning' : 'success';
		} elseif ($scanType === 'combined' || $includeTemplates) {
			// Combined scan (database + templates)
			$scanResults = accessibilityChecker_runCombinedScan($selectedTables);

			$issuesFound = ($scanResults['errors'] ?? 0) + ($scanResults['warnings'] ?? 0);
			$recordsAndFiles = ($scanResults['recordsScanned'] ?? 0) + ($scanResults['filesScanned'] ?? 0);
			$message = sprintf(
				t('Combined scan completed. Scanned %d records + %d template files, found %d issues.'),
				$scanResults['recordsScanned'] ?? 0,
				$scanResults['filesScanned'] ?? 0,
				$issuesFound
			);
			$messageType = ($issuesFound > 0) ? 'warning' : 'success';
		} else {
			// Database-only scan
			if ($scanType === 'quick') {
				$scanResults = accessibilityChecker_runQuickScan();
			} else {
				$scanResults = accessibilityChecker_runManualScan($scanType === 'selected' ? $selectedTables : []);
			}

			// Calculate total issues
			$issuesFound = ($scanResults['errors'] ?? 0) + ($scanResults['warnings'] ?? 0);

			$message = sprintf(
				t('Scan completed. Scanned %d records, found %d issues.'),
				$scanResults['recordsScanned'] ?? 0,
				$issuesFound
			);
			$messageType = ($issuesFound > 0) ? 'warning' : 'success';
		}
	}

	// Get enabled tables
	$settings = accessibilityChecker_loadPluginSettings();
	$enabledTables = $settings['enabledTables'] ?? [];
	if (empty($enabledTables)) {
		$enabledTables = accessibilityChecker_getContentTablesWithTextFields();
	}

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Accessibility Checker") => '?_pluginAction=accessibilityChecker_adminDashboard',
		t("Run Scan"),
	];

	$adminUI['ADVANCED_ACTIONS'] = accessibilityChecker_getAdvancedActions();

	$adminUI['FORM'] = ['name' => 'runScanForm', 'autocomplete' => 'off'];
	$adminUI['HIDDEN_FIELDS'] = [
		['name' => 'runScan', 'value' => '1'],
		['name' => '_pluginAction', 'value' => 'accessibilityChecker_adminRunScan'],
	];
	$adminUI['BUTTONS'] = [
		['name' => '_action=runScan', 'label' => t('Run Scan')],
	];

	$content = '';

	// Plugin navigation
	$content .= accessibilityChecker_getPluginNav('run_scan');

	// Show message if any
	if ($message) {
		$content .= '<div class="alert alert-' . $messageType . '">';
		$content .= $message;
		$content .= '</div>';
	}

	// Scan Results Summary
	if ($scanResults) {
		$content .= '<div class="separator"><div>' . t('Scan Results Summary') . '</div></div>';

		$content .= '<div class="row" style="margin-left:-10px;margin-right:-10px;margin-bottom:20px">';

		$renderStatCard = function ($label, $count, $color, $colClass = 'col-6 col-md-3') {
			$html = '<div class="' . $colClass . '" style="padding-left:10px;padding-right:10px;margin-bottom:15px">';
			$html .= '<div class="border rounded-3 p-3 h-100 text-center" style="border:1px solid #dee2e6!important">';
			$html .= '<div class="text-uppercase small fw-semibold mb-3" style="font-weight:600;font-size:11px;letter-spacing:0.5px">' . $label . '</div>';
			$html .= '<div class="fs-2 fw-bold" style="color:' . $color . ';font-size:2.5rem;font-weight:700">' . $count . '</div>';
			$html .= '</div></div>';
			return $html;
		};

		$content .= $renderStatCard(t('Records Scanned'), $scanResults['recordsScanned'] ?? 0, '#6c757d', 'col-12');
		$content .= $renderStatCard(t('Errors'), $scanResults['errors'] ?? 0, '#dc3545');
		$content .= $renderStatCard(t('Warnings'), $scanResults['warnings'] ?? 0, '#ffc107');
		$content .= $renderStatCard(t('Tables'), $scanResults['tablesScanned'] ?? 0, '#6c757d');
		$content .= $renderStatCard(t('Duration'), ($scanResults['duration'] ?? 0) . 's', '#6c757d');

		$content .= '</div>';

		$content .= '<p style="margin-bottom:20px"><a href="?_pluginAction=accessibilityChecker_adminResults" class="btn btn-primary">' . t('View Detailed Results') . ' &raquo;</a></p>';
	}

	// Scan Options Section
	$content .= '<div class="separator"><div>' . t('Scan Options') . '</div></div>';

	$content .= '<div class="form-horizontal">';

	// Scan Type
	$content .= '<div class="form-group">';
	$content .= '<label class="col-sm-2 control-label">' . t('Scan Type') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="radio"><label>';
	$content .= '<input type="radio" name="scanType" value="full" checked> ';
	$content .= '<strong>' . t('Full Scan') . '</strong> - ' . t('Check all CMS database content') . '<br>';
	$content .= '<small class="help-block" style="margin-left:20px;margin-top:5px">' . t('Scans all content in all enabled tables. Use this for comprehensive content checking.') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="radio"><label>';
	$content .= '<input type="radio" name="scanType" value="quick"> ';
	$content .= '<strong>' . t('Quick Scan') . '</strong> - ' . t('Check only records with previous issues') . '<br>';
	$content .= '<small class="help-block" style="margin-left:20px;margin-top:5px">' . t('Only rechecks records that previously had issues. Much faster than a full scan.') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="radio"><label>';
	$content .= '<input type="radio" name="scanType" value="selected"> ';
	$content .= '<strong>' . t('Selected Tables') . '</strong> - ' . t('Check only selected tables below') . '<br>';
	$content .= '<small class="help-block" style="margin-left:20px;margin-top:5px">' . t('Scan only specific content sections you select below.') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="radio"><label>';
	$content .= '<input type="radio" name="scanType" value="templates"> ';
	$content .= '<strong>' . t('Template Files Only') . '</strong> - ' . t('Check website template files (PHP/HTML)') . '<br>';
	$content .= '<small class="help-block" style="margin-left:20px;margin-top:5px">' . t('Scans PHP and HTML template files in your website includes, templates, and root directory. Catches accessibility issues in your site structure.') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="radio"><label>';
	$content .= '<input type="radio" name="scanType" value="combined"> ';
	$content .= '<strong>' . t('Combined Scan') . '</strong> - ' . t('Check both database content AND template files') . '<br>';
	$content .= '<small class="help-block" style="margin-left:20px;margin-top:5px">' . t('Most comprehensive scan: checks both CMS content and website templates. Recommended for thorough accessibility audits.') . '</small>';
	$content .= '</label></div>';
	$content .= '</div></div>';

	$content .= '</div>'; // end form-horizontal

	// Table Selection Section
	$content .= '<div class="separator"><div>' . t('Select Tables to Scan') . '</div></div>';

	$content .= '<p style="margin-bottom:15px">' . t('Select specific tables to scan. This option is used when "Selected Tables" scan type is chosen.') . '</p>';

	if (empty($enabledTables)) {
		$content .= '<div class="alert alert-warning">' . t('No tables are configured for accessibility checking. Please configure tables in Settings first.') . '</div>';
	} else {
		$content .= '<div style="margin-bottom:15px">';
		$content .= '<button type="button" class="btn btn-sm btn-primary" style="margin-right:5px" onclick="selectAllTables()">' . t('Select All') . '</button>';
		$content .= '<button type="button" class="btn btn-sm btn-default" onclick="selectNoTables()">' . t('Select None') . '</button>';
		$content .= '</div>';

		$content .= '<div class="table-responsive">';
		$content .= '<table class="table table-hover">';
		$content .= '<thead><tr>';
		$content .= '<th scope="col" style="width:50px" class="text-center">' . t('Scan') . '</th>';
		$content .= '<th scope="col">' . t('Section Name') . '</th>';
		$content .= '<th scope="col">' . t('Table') . '</th>';
		$content .= '<th scope="col">' . t('Record Count') . '</th>';
		$content .= '</tr></thead><tbody>';

		foreach ($enabledTables as $tableName) {
			$schema = \loadSchema($tableName);
			$menuName = $schema['menuName'] ?? $tableName;
			$recordCount = \mysql_count($tableName);

			$content .= '<tr>';
			$content .= '<td class="text-center">';
			$content .= '<input class="form-check-input table-checkbox" type="checkbox" name="selectedTables[]" value="' . \htmlencode($tableName) . '">';
			$content .= '</td>';
			$content .= '<td><strong>' . \htmlencode($menuName) . '</strong></td>';
			$content .= '<td><code>' . \htmlencode($tableName) . '</code></td>';
			$content .= '<td>' . $recordCount . ' ' . t('records') . '</td>';
			$content .= '</tr>';
		}

		$content .= '</tbody></table>';
		$content .= '</div>';
	}

	// Performance Note
	$content .= '<div class="alert alert-info" style="margin-top:20px">';
	$content .= '<strong><i class="fa-duotone fa-solid fa-info-circle" aria-hidden="true"></i> ' . t('Performance Note:') . '</strong> ';
	$content .= t('Large scans may take several minutes to complete. The scan runs in the background and will not timeout. You can navigate away from this page and check results later.');
	$content .= '</div>';

	// JavaScript for table selection
	$content .= '<script>
function selectAllTables() {
	document.querySelectorAll(".table-checkbox").forEach(function(cb) {
		cb.checked = true;
	});
}
function selectNoTables() {
	document.querySelectorAll(".table-checkbox").forEach(function(cb) {
		cb.checked = false;
	});
}
</script>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * Settings page - Configure plugin options
 */
function accessibilityChecker_adminSettings(): void
{
	$message = '';
	$messageType = 'info';

	// Load current settings
	$settings = accessibilityChecker_loadPluginSettings();

	// Handle form submission
	if (($_REQUEST['saveSettings'] ?? '')) {
		\security_dieOnInvalidCsrfToken(); // CSRF protection

		// Get enabled tables from form
		$enabledTables = [];
		if (!empty($_REQUEST['enabledTables']) && is_array($_REQUEST['enabledTables'])) {
			$enabledTables = array_values(array_filter($_REQUEST['enabledTables']));
		}

		// Get ignore patterns from form
		$ignoreAltText = [];
		$ignoreAltTextRaw = trim($_REQUEST['ignoreAltText'] ?? '');
		if ($ignoreAltTextRaw) {
			$ignoreAltText = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $ignoreAltTextRaw)));
		}

		$ignoreLinkText = [];
		$ignoreLinkTextRaw = trim($_REQUEST['ignoreLinkText'] ?? '');
		if ($ignoreLinkTextRaw) {
			$ignoreLinkText = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $ignoreLinkTextRaw)));
		}

		// Update settings
		$settings['enabled'] = !empty($_REQUEST['enabled']);
		$settings['scheduledScans'] = !empty($_REQUEST['scheduledScans']);
		$settings['scanFrequency'] = $_REQUEST['scanFrequency'] ?? 'daily';

		// Checks
		$settings['checks']['missingAlt'] = !empty($_REQUEST['checkMissingAlt']);
		$settings['checks']['poorAlt'] = !empty($_REQUEST['checkPoorAlt']);
		$settings['checks']['minAltLength'] = max(1, min(50, intval($_REQUEST['minAltLength'] ?? 5)));
		$settings['checks']['emptyLinks'] = !empty($_REQUEST['checkEmptyLinks']);
		$settings['checks']['genericLinkText'] = !empty($_REQUEST['checkGenericLinkText']);
		$settings['checks']['skippedHeadings'] = !empty($_REQUEST['checkSkippedHeadings']);
		$settings['checks']['tablesWithoutHeaders'] = !empty($_REQUEST['checkTablesWithoutHeaders']);
		$settings['checks']['emptyButtons'] = !empty($_REQUEST['checkEmptyButtons']);
		$settings['checks']['missingFormLabels'] = !empty($_REQUEST['checkMissingFormLabels']);

		// Email
		$settings['emailNotifications'] = !empty($_REQUEST['emailNotifications']);
		$settings['notificationEmail'] = trim($_REQUEST['notificationEmail'] ?? '');
		$settings['emailOnlyOnErrors'] = !empty($_REQUEST['emailOnlyOnErrors']);
		$settings['includeWarningsInTrigger'] = !empty($_REQUEST['includeWarningsInTrigger']);

		// Ignore patterns
		$settings['ignorePatterns']['altText'] = $ignoreAltText;
		$settings['ignorePatterns']['linkText'] = $ignoreLinkText;

		// Tables and retention
		$settings['enabledTables'] = $enabledTables;
		$settings['logRetentionDays'] = max(1, min(365, intval($_REQUEST['logRetentionDays'] ?? 90)));

		if (accessibilityChecker_savePluginSettings($settings)) {
			$message = t('Settings saved successfully');
			$messageType = 'success';
		} else {
			$message = t('Failed to save settings. Check file permissions.');
			$messageType = 'danger';
		}
	}

	// Get all content tables
	$allTables = accessibilityChecker_getContentTablesWithTextFields();

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Accessibility Checker") => '?_pluginAction=accessibilityChecker_adminDashboard',
		t("Settings"),
	];

	$adminUI['ADVANCED_ACTIONS'] = accessibilityChecker_getAdvancedActions();

	$adminUI['FORM'] = ['name' => 'settingsForm', 'autocomplete' => 'off'];
	$adminUI['HIDDEN_FIELDS'] = [
		['name' => 'saveSettings', 'value' => '1'],
		['name' => '_pluginAction', 'value' => 'accessibilityChecker_adminSettings'],
	];
	$adminUI['BUTTONS'] = [
		['name' => '_action=save', 'label' => t('Save Settings')],
	];

	$content = '';

	// Plugin navigation
	$content .= accessibilityChecker_getPluginNav('settings');

	// Show message if any
	if ($message) {
		$content .= '<div class="alert alert-' . $messageType . '">';
		$content .= $message;
		$content .= '</div>';
	}

	// Card style for settings
	$cardStyle = 'border:1px solid #dee2e6;border-radius:8px;padding:20px;background:#fff;flex:1;';
	$cardTitleStyle = 'font-size:14px;font-weight:600;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #eee;';
	$colStyle = 'padding-left:7px;padding-right:7px;margin-bottom:15px;display:flex;';
	$rowStyle = 'display:flex;flex-wrap:wrap;margin-left:-7px;margin-right:-7px;';

	// General Settings & Scan Schedule Row
	$content .= '<div class="separator"><div>' . t('General Settings') . '</div></div>';

	$content .= '<div style="' . $rowStyle . '">';

	// Plugin Enabled Card
	$content .= '<div style="' . $colStyle . 'width:50%;min-width:300px;">';
	$content .= '<div style="' . $cardStyle . '">';
	$content .= '<div style="' . $cardTitleStyle . '"><i class="fa-duotone fa-solid fa-power-off" aria-hidden="true"></i> ' . t('Plugin Status') . '</div>';
	$content .= '<div class="checkbox" style="margin:0"><label>';
	$content .= '<input type="checkbox" name="enabled" value="1"' . (($settings['enabled'] ?? true) ? ' checked' : '') . '> ';
	$content .= t('Enable accessibility checking');
	$content .= '</label></div>';
	$content .= '<p class="help-block" style="margin-top:10px;margin-bottom:0">' . t('When disabled, no scans will run') . '</p>';
	$content .= '</div></div>';

	// Scan Schedule Card
	$content .= '<div style="' . $colStyle . 'width:50%;min-width:300px;">';
	$content .= '<div style="' . $cardStyle . '">';
	$content .= '<div style="' . $cardTitleStyle . '"><i class="fa-duotone fa-solid fa-calendar-clock" aria-hidden="true"></i> ' . t('Scan Schedule') . '</div>';
	$content .= '<div class="checkbox" style="margin:0 0 10px 0"><label>';
	$content .= '<input type="checkbox" name="scheduledScans" value="1"' . (($settings['scheduledScans'] ?? true) ? ' checked' : '') . '> ';
	$content .= t('Enable automatic scheduled scans');
	$content .= '</label></div>';
	$content .= '<label for="scanFrequency" style="font-weight:normal;margin-bottom:5px">' . t('Frequency:') . '</label>';
	$content .= '<select name="scanFrequency" id="scanFrequency" class="form-control" style="width:100%">';
	$frequencies = ['daily' => t('Daily'), 'weekly' => t('Weekly'), 'monthly' => t('Monthly')];
	foreach ($frequencies as $key => $label) {
		$selected = (($settings['scanFrequency'] ?? 'daily') === $key) ? ' selected' : '';
		$content .= '<option value="' . $key . '"' . $selected . '>' . $label . '</option>';
	}
	$content .= '</select>';
	$content .= '</div></div>';

	$content .= '</div>'; // end row

	// Checks to Perform Section
	$content .= '<div class="separator"><div>' . t('Checks to Perform') . '</div></div>';

	$content .= '<div style="' . $rowStyle . '">';

	// Image Checks Card
	$content .= '<div style="' . $colStyle . 'width:50%;min-width:300px;">';
	$content .= '<div style="' . $cardStyle . '">';
	$content .= '<div style="' . $cardTitleStyle . '"><i class="fa-duotone fa-solid fa-image" aria-hidden="true"></i> ' . t('Image Checks') . '</div>';
	$content .= '<div class="checkbox" style="margin:0 0 8px 0"><label>';
	$content .= '<input type="checkbox" name="checkMissingAlt" value="1"' . (($settings['checks']['missingAlt'] ?? true) ? ' checked' : '') . '> ';
	$content .= '<strong>' . t('Missing Alt Text') . '</strong> <span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span>';
	$content .= '<br><small class="text-muted">' . t('Images without alt attribute') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="checkbox" style="margin:0 0 12px 0"><label>';
	$content .= '<input type="checkbox" name="checkPoorAlt" value="1"' . (($settings['checks']['poorAlt'] ?? true) ? ' checked' : '') . '> ';
	$content .= '<strong>' . t('Poor Alt Text') . '</strong> <span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span>';
	$content .= '<br><small class="text-muted">' . t('Filenames, too short, or generic') . '</small>';
	$content .= '</label></div>';
	$content .= '<label for="minAltLength" style="font-weight:normal;margin-bottom:5px">' . t('Min Alt Length:') . '</label>';
	$content .= '<input type="number" name="minAltLength" id="minAltLength" class="form-control" style="width:80px" min="1" max="50" value="' . intval($settings['checks']['minAltLength'] ?? 5) . '">';
	$content .= '</div></div>';

	// Link Checks Card
	$content .= '<div style="' . $colStyle . 'width:50%;min-width:300px;">';
	$content .= '<div style="' . $cardStyle . '">';
	$content .= '<div style="' . $cardTitleStyle . '"><i class="fa-duotone fa-solid fa-link" aria-hidden="true"></i> ' . t('Link Checks') . '</div>';
	$content .= '<div class="checkbox" style="margin:0 0 8px 0"><label>';
	$content .= '<input type="checkbox" name="checkEmptyLinks" value="1"' . (($settings['checks']['emptyLinks'] ?? true) ? ' checked' : '') . '> ';
	$content .= '<strong>' . t('Empty Links') . '</strong> <span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span>';
	$content .= '<br><small class="text-muted">' . t('Links with no accessible text') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="checkbox" style="margin:0"><label>';
	$content .= '<input type="checkbox" name="checkGenericLinkText" value="1"' . (($settings['checks']['genericLinkText'] ?? true) ? ' checked' : '') . '> ';
	$content .= '<strong>' . t('Generic Link Text') . '</strong> <span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span>';
	$content .= '<br><small class="text-muted">' . t('"click here", "read more", etc.') . '</small>';
	$content .= '</label></div>';
	$content .= '</div></div>';

	// Structure Checks Card
	$content .= '<div style="' . $colStyle . 'width:50%;min-width:300px;">';
	$content .= '<div style="' . $cardStyle . '">';
	$content .= '<div style="' . $cardTitleStyle . '"><i class="fa-duotone fa-solid fa-heading" aria-hidden="true"></i> ' . t('Structure Checks') . '</div>';
	$content .= '<div class="checkbox" style="margin:0 0 8px 0"><label>';
	$content .= '<input type="checkbox" name="checkSkippedHeadings" value="1"' . (($settings['checks']['skippedHeadings'] ?? true) ? ' checked' : '') . '> ';
	$content .= '<strong>' . t('Skipped Heading Levels') . '</strong> <span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span>';
	$content .= '<br><small class="text-muted">' . t('Hierarchy jumps (h2 to h4)') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="checkbox" style="margin:0"><label>';
	$content .= '<input type="checkbox" name="checkTablesWithoutHeaders" value="1"' . (($settings['checks']['tablesWithoutHeaders'] ?? true) ? ' checked' : '') . '> ';
	$content .= '<strong>' . t('Tables Without Headers') . '</strong> <span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span>';
	$content .= '<br><small class="text-muted">' . t('Data tables missing th elements') . '</small>';
	$content .= '</label></div>';
	$content .= '</div></div>';

	// Form Checks Card
	$content .= '<div style="' . $colStyle . 'width:50%;min-width:300px;">';
	$content .= '<div style="' . $cardStyle . '">';
	$content .= '<div style="' . $cardTitleStyle . '"><i class="fa-duotone fa-solid fa-input-text" aria-hidden="true"></i> ' . t('Form Checks') . '</div>';
	$content .= '<div class="checkbox" style="margin:0 0 8px 0"><label>';
	$content .= '<input type="checkbox" name="checkEmptyButtons" value="1"' . (($settings['checks']['emptyButtons'] ?? true) ? ' checked' : '') . '> ';
	$content .= '<strong>' . t('Empty Buttons') . '</strong> <span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span>';
	$content .= '<br><small class="text-muted">' . t('Buttons without accessible name') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="checkbox" style="margin:0"><label>';
	$content .= '<input type="checkbox" name="checkMissingFormLabels" value="1"' . (($settings['checks']['missingFormLabels'] ?? true) ? ' checked' : '') . '> ';
	$content .= '<strong>' . t('Missing Form Labels') . '</strong> <span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span>';
	$content .= '<br><small class="text-muted">' . t('Inputs without associated labels') . '</small>';
	$content .= '</label></div>';
	$content .= '</div></div>';

	$content .= '</div>'; // end row

	// Email & Ignore Patterns Section
	$content .= '<div class="separator"><div>' . t('Notifications & Ignore Patterns') . '</div></div>';

	$content .= '<div style="' . $rowStyle . '">';

	// Email Notifications Card
	$content .= '<div style="' . $colStyle . 'width:50%;min-width:300px;">';
	$content .= '<div style="' . $cardStyle . '">';
	$content .= '<div style="' . $cardTitleStyle . '"><i class="fa-duotone fa-solid fa-envelope" aria-hidden="true"></i> ' . t('Email Notifications') . '</div>';
	$content .= '<div class="checkbox" style="margin:0 0 10px 0"><label>';
	$content .= '<input type="checkbox" name="emailNotifications" value="1"' . (($settings['emailNotifications'] ?? true) ? ' checked' : '') . '> ';
	$content .= t('Send email after scheduled scans');
	$content .= '</label></div>';
	$content .= '<label for="notificationEmail" style="font-weight:normal;margin-bottom:5px">' . t('Email Address:') . '</label>';
	$content .= '<input type="email" name="notificationEmail" id="notificationEmail" class="form-control" style="width:100%;margin-bottom:10px" value="' . \htmlencode($settings['notificationEmail'] ?? '') . '" placeholder="' . t('Leave blank for admin email') . '">';
	$content .= '<div class="checkbox" style="margin:0 0 5px 0"><label>';
	$content .= '<input type="checkbox" name="emailOnlyOnErrors" value="1"' . (($settings['emailOnlyOnErrors'] ?? true) ? ' checked' : '') . '> ';
	$content .= t('Only send when errors found');
	$content .= '</label></div>';
	$content .= '<div class="checkbox" style="margin:0"><label>';
	$content .= '<input type="checkbox" name="includeWarningsInTrigger" value="1"' . (($settings['includeWarningsInTrigger'] ?? false) ? ' checked' : '') . '> ';
	$content .= t('Include warnings in trigger');
	$content .= '</label></div>';
	$content .= '</div></div>';

	// Ignore Patterns Card
	$content .= '<div style="' . $colStyle . 'width:50%;min-width:300px;">';
	$content .= '<div style="' . $cardStyle . '">';
	$content .= '<div style="' . $cardTitleStyle . '"><i class="fa-duotone fa-solid fa-filter-slash" aria-hidden="true"></i> ' . t('Ignore Patterns') . '</div>';
	$content .= '<label for="ignoreAltText" style="font-weight:normal;margin-bottom:5px">' . t('Alt Text Patterns:') . '</label>';
	$content .= '<textarea name="ignoreAltText" id="ignoreAltText" class="form-control" rows="2" style="width:100%;margin-bottom:10px" placeholder="decorative&#10;spacer">' . \htmlencode(implode("\n", $settings['ignorePatterns']['altText'] ?? [])) . '</textarea>';
	$content .= '<label for="ignoreLinkText" style="font-weight:normal;margin-bottom:5px">' . t('Link Text Patterns:') . '</label>';
	$content .= '<textarea name="ignoreLinkText" id="ignoreLinkText" class="form-control" rows="2" style="width:100%">' . \htmlencode(implode("\n", $settings['ignorePatterns']['linkText'] ?? [])) . '</textarea>';
	$content .= '<p class="help-block" style="margin-top:8px;margin-bottom:0">' . t('One pattern per line. Issues matching these are ignored.') . '</p>';
	$content .= '</div></div>';

	$content .= '</div>'; // end row

	// Tables to Scan Section
	$content .= '<div class="separator"><div>' . t('Tables to Scan') . '</div></div>';

	$content .= '<p class="help-block" style="margin-bottom:15px">' . t('Select which tables should be scanned for accessibility issues. If none are selected, all tables with text fields will be scanned.') . '</p>';

	if (empty($allTables)) {
		$content .= '<div class="alert alert-warning">' . t('No content tables with text fields found in your database.') . '</div>';
	} else {
		$content .= '<div style="margin-bottom:15px">';
		$content .= '<button type="button" class="btn btn-sm btn-primary" style="margin-right:5px" onclick="selectAllTables()">' . t('Select All') . '</button>';
		$content .= '<button type="button" class="btn btn-sm btn-default" onclick="selectNoTables()">' . t('Select None') . '</button>';
		$content .= '</div>';

		$content .= '<div class="table-responsive" style="max-height:400px;overflow-y:auto">';
		$content .= '<table class="table table-hover">';
		$content .= '<thead><tr>';
		$content .= '<th scope="col" style="width:50px" class="text-center">' . t('Scan') . '</th>';
		$content .= '<th scope="col">' . t('Section Name') . '</th>';
		$content .= '<th scope="col">' . t('Table') . '</th>';
		$content .= '</tr></thead><tbody>';

		$enabledTables = $settings['enabledTables'] ?? [];
		foreach ($allTables as $tableName) {
			$schema = \loadSchema($tableName);
			$menuName = $schema['menuName'] ?? $tableName;
			$isChecked = empty($enabledTables) || in_array($tableName, $enabledTables);

			$content .= '<tr>';
			$content .= '<td class="text-center">';
			$content .= '<input class="form-check-input table-checkbox" type="checkbox" name="enabledTables[]" value="' . \htmlencode($tableName) . '"' . ($isChecked ? ' checked' : '') . '>';
			$content .= '</td>';
			$content .= '<td><strong>' . \htmlencode($menuName) . '</strong></td>';
			$content .= '<td><code>' . \htmlencode($tableName) . '</code></td>';
			$content .= '</tr>';
		}

		$content .= '</tbody></table>';
		$content .= '</div>';
	}

	// Log Retention Section
	$content .= '<div class="separator" style="margin-top:30px;"><div>' . t('Log Retention') . '</div></div>';

	$content .= '<div style="' . $rowStyle . '">';
	$content .= '<div style="' . $colStyle . 'width:50%;min-width:300px;">';
	$content .= '<div style="' . $cardStyle . '">';
	$content .= '<div style="' . $cardTitleStyle . '"><i class="fa-duotone fa-solid fa-clock-rotate-left" aria-hidden="true"></i> ' . t('Retention Period') . '</div>';
	$content .= '<label for="logRetentionDays" style="font-weight:normal;margin-bottom:5px">' . t('Keep results for:') . '</label>';
	$content .= '<div style="display:flex;align-items:center;gap:8px">';
	$content .= '<input type="number" name="logRetentionDays" id="logRetentionDays" class="form-control" style="width:80px" min="1" max="365" value="' . intval($settings['logRetentionDays'] ?? 90) . '">';
	$content .= '<span>' . t('days') . '</span>';
	$content .= '</div>';
	$content .= '<p class="help-block" style="margin-top:10px;margin-bottom:0">' . t('Fixed and ignored issues older than this will be automatically deleted') . '</p>';
	$content .= '</div></div>';
	$content .= '</div>';

	// JavaScript for table selection
	$content .= '<script>
function selectAllTables() {
	document.querySelectorAll(".table-checkbox").forEach(function(cb) {
		cb.checked = true;
	});
}
function selectNoTables() {
	document.querySelectorAll(".table-checkbox").forEach(function(cb) {
		cb.checked = false;
	});
}
</script>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * Scan History page - View all past scans
 */
function accessibilityChecker_adminHistory(): void
{
	// Pagination
	$page = max(1, intval($_REQUEST['page'] ?? 1));
	$perPage = 25;
	$offset = ($page - 1) * $perPage;

	// Get total count
	$totalCount = \mysql_count('_accessibilitychecker_scans');
	$totalPages = ceil($totalCount / $perPage);

	// Get scans
	$scans = \mysql_select('_accessibilitychecker_scans', "1=1 ORDER BY `createdDate` DESC LIMIT {$offset}, {$perPage}");

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Accessibility Checker") => '?_pluginAction=accessibilityChecker_adminDashboard',
		t("Scan History"),
	];

	$adminUI['ADVANCED_ACTIONS'] = accessibilityChecker_getAdvancedActions();

	$content = '';

	// Plugin navigation
	$content .= accessibilityChecker_getPluginNav('history');

	// History Section
	$content .= '<div class="separator"><div>' . t('Scan History') . '</div></div>';

	$content .= '<p class="help-block" style="margin-top:0;margin-bottom:15px">' . t('Complete history of all accessibility scans performed on this site.') . '</p>';

	if (empty($scans)) {
		$content .= '<div class="alert alert-info">';
		$content .= '<i class="fa-duotone fa-solid fa-info-circle" aria-hidden="true"></i> ';
		$content .= t('No scans have been performed yet. Go to') . ' <a href="?_pluginAction=accessibilityChecker_adminRunScan">' . t('Run Scan') . '</a> ' . t('to start your first accessibility check.');
		$content .= '</div>';
	} else {
		$content .= '<div class="table-responsive">';
		$content .= '<table class="table table-striped table-hover">';
		$content .= '<thead><tr>';
		$content .= '<th scope="col">' . t('Date') . '</th>';
		$content .= '<th scope="col">' . t('Type') . '</th>';
		$content .= '<th scope="col">' . t('Status') . '</th>';
		$content .= '<th scope="col" class="text-right">' . t('Tables') . '</th>';
		$content .= '<th scope="col" class="text-right">' . t('Records') . '</th>';
		$content .= '<th scope="col" class="text-right">' . t('Errors') . '</th>';
		$content .= '<th scope="col" class="text-right">' . t('Warnings') . '</th>';
		$content .= '<th scope="col" class="text-right">' . t('Duration') . '</th>';
		$content .= '</tr></thead><tbody>';

		foreach ($scans as $scan) {
			$typeBadge = match ($scan['scanType']) {
				'manual' => '<span class="badge" style="background-color:#17a2b8;color:#fff">' . t('Manual') . '</span>',
				'scheduled' => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Scheduled') . '</span>',
				default => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Unknown') . '</span>',
			};

			$statusBadge = match ($scan['status']) {
				'completed' => '<span class="badge" style="background-color:#28a745;color:#fff">' . t('Completed') . '</span>',
				'running' => '<span class="badge" style="background-color:#ffc107;color:#212529">' . t('Running') . '</span>',
				'failed' => '<span class="badge" style="background-color:#dc3545;color:#fff">' . t('Failed') . '</span>',
				default => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Unknown') . '</span>',
			};

			$content .= '<tr>';
			$content .= '<td class="text-nowrap">' . date('Y-m-d H:i:s', strtotime($scan['startTime'])) . '</td>';
			$content .= '<td>' . $typeBadge . '</td>';
			$content .= '<td>' . $statusBadge . '</td>';
			$content .= '<td class="text-right">' . intval($scan['tablesScanned']) . '</td>';
			$content .= '<td class="text-right">' . intval($scan['recordsScanned']) . '</td>';
			$content .= '<td class="text-right">';
			if (intval($scan['errorsFound']) > 0) {
				$content .= '<strong style="color:#dc3545">' . intval($scan['errorsFound']) . '</strong>';
			} else {
				$content .= intval($scan['errorsFound']);
			}
			$content .= '</td>';
			$content .= '<td class="text-right">';
			if (intval($scan['warningsFound']) > 0) {
				$content .= '<strong style="color:#ffc107">' . intval($scan['warningsFound']) . '</strong>';
			} else {
				$content .= intval($scan['warningsFound']);
			}
			$content .= '</td>';
			$content .= '<td class="text-right">' . number_format(floatval($scan['duration']), 2) . 's</td>';
			$content .= '</tr>';
		}

		$content .= '</tbody></table></div>';

		// Pagination
		if ($totalPages > 1) {
			$baseUrl = '?_pluginAction=accessibilityChecker_adminHistory';

			$content .= '<div class="text-center" style="margin-top:15px">';

			if ($page > 1) {
				$content .= '<a href="' . $baseUrl . '&page=' . ($page - 1) . '" class="btn btn-default btn-sm">&laquo; ' . t('Previous') . '</a> ';
			}

			$startPage = max(1, $page - 2);
			$endPage = min($totalPages, $page + 2);

			for ($i = $startPage; $i <= $endPage; $i++) {
				if ($i === $page) {
					$content .= '<span class="btn btn-primary btn-sm">' . $i . '</span> ';
				} else {
					$content .= '<a href="' . $baseUrl . '&page=' . $i . '" class="btn btn-default btn-sm">' . $i . '</a> ';
				}
			}

			if ($page < $totalPages) {
				$content .= '<a href="' . $baseUrl . '&page=' . ($page + 1) . '" class="btn btn-default btn-sm">' . t('Next') . ' &raquo;</a>';
			}

			$content .= '</div>';
		}
	}

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * Help page - Display documentation
 */
function accessibilityChecker_adminHelp(): void
{
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Accessibility Checker") => '?_pluginAction=accessibilityChecker_adminDashboard',
		t("Help"),
	];

	$adminUI['ADVANCED_ACTIONS'] = accessibilityChecker_getAdvancedActions();

	$content = '';

	// Plugin navigation
	$content .= accessibilityChecker_getPluginNav('help');

	// Overview Section
	$content .= '<div class="separator"><div>' . t('Overview') . '</div></div>';

	$content .= '<p>' . t('Scan your CMS content for WCAG 2.1 AA accessibility violations. The plugin helps you identify and fix accessibility issues before they impact your users or compliance.') . '</p>';

	$content .= '<p><strong>' . t('Features:') . '</strong></p>';
	$content .= '<ul>';
	$content .= '<li><strong>' . t('Automatic Scanning') . '</strong> - ' . t('Schedule daily, weekly, or monthly scans') . '</li>';
	$content .= '<li><strong>' . t('8 Accessibility Checks') . '</strong> - ' . t('Images, links, headings, tables, buttons, forms') . '</li>';
	$content .= '<li><strong>' . t('Direct Edit Links') . '</strong> - ' . t('Go straight to the source record to fix issues') . '</li>';
	$content .= '<li><strong>' . t('Issue Tracking') . '</strong> - ' . t('Mark issues as fixed or ignored') . '</li>';
	$content .= '<li><strong>' . t('Email Notifications') . '</strong> - ' . t('Get alerts when scans find new issues') . '</li>';
	$content .= '</ul>';

	// Accessibility Checks Section
	$content .= '<div class="separator"><div>' . t('Accessibility Checks') . '</div></div>';

	$content .= '<div class="table-responsive">';
	$content .= '<table class="table table-striped">';
	$content .= '<thead><tr><th>' . t('Check') . '</th><th>' . t('Severity') . '</th><th>' . t('WCAG Criterion') . '</th></tr></thead>';
	$content .= '<tbody>';
	$content .= '<tr><td><strong>' . t('Missing Alt Text') . '</strong><br><small class="text-muted">' . t('Images without alt attribute') . '</small></td><td><span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span></td><td>1.1.1 Non-text Content</td></tr>';
	$content .= '<tr><td><strong>' . t('Poor Alt Text') . '</strong><br><small class="text-muted">' . t('Filenames, too short, or generic') . '</small></td><td><span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span></td><td>1.1.1 Non-text Content</td></tr>';
	$content .= '<tr><td><strong>' . t('Empty Links') . '</strong><br><small class="text-muted">' . t('Links with no accessible text') . '</small></td><td><span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span></td><td>2.4.4 Link Purpose</td></tr>';
	$content .= '<tr><td><strong>' . t('Generic Link Text') . '</strong><br><small class="text-muted">' . t('"Click here", "Read more", etc.') . '</small></td><td><span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span></td><td>2.4.4 Link Purpose</td></tr>';
	$content .= '<tr><td><strong>' . t('Skipped Heading Levels') . '</strong><br><small class="text-muted">' . t('h2 jumping to h4') . '</small></td><td><span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span></td><td>1.3.1 Info and Relationships</td></tr>';
	$content .= '<tr><td><strong>' . t('Tables Without Headers') . '</strong><br><small class="text-muted">' . t('Data tables missing th elements') . '</small></td><td><span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span></td><td>1.3.1 Info and Relationships</td></tr>';
	$content .= '<tr><td><strong>' . t('Empty Buttons') . '</strong><br><small class="text-muted">' . t('Buttons without accessible name') . '</small></td><td><span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span></td><td>4.1.2 Name, Role, Value</td></tr>';
	$content .= '<tr><td><strong>' . t('Missing Form Labels') . '</strong><br><small class="text-muted">' . t('Inputs without associated labels') . '</small></td><td><span class="badge" style="background-color:#dc3545;color:#fff">' . t('Error') . '</span></td><td>1.3.1, 4.1.2</td></tr>';
	$content .= '</tbody></table>';
	$content .= '</div>';

	// How to Use Section
	$content .= '<div class="separator" style="margin-top:25px"><div>' . t('How to Use') . '</div></div>';

	$content .= '<ol>';
	$content .= '<li><strong>' . t('Configure Settings') . '</strong> - ' . t('Go to Settings to select which tables to scan and enable/disable specific checks.') . '</li>';
	$content .= '<li><strong>' . t('Run a Scan') . '</strong> - ' . t('Use Run Scan for a full scan of all content, or Quick Scan to recheck previous issues.') . '</li>';
	$content .= '<li><strong>' . t('Review Results') . '</strong> - ' . t('Check the Scan Results page to see all found issues with filtering and sorting.') . '</li>';
	$content .= '<li><strong>' . t('Fix Issues') . '</strong> - ' . t('Click Edit to go directly to the source record and fix the issue.') . '</li>';
	$content .= '<li><strong>' . t('Update Status') . '</strong> - ' . t('Mark issues as Fixed after correcting them, or Ignored if intentionally accepted.') . '</li>';
	$content .= '</ol>';

	// Scan Types Section
	$content .= '<div class="separator"><div>' . t('Scan Types') . '</div></div>';

	$content .= '<ul>';
	$content .= '<li><strong>' . t('Full Scan') . '</strong> - ' . t('Scans all content in all enabled tables. Use for comprehensive site-wide checking.') . '</li>';
	$content .= '<li><strong>' . t('Quick Scan') . '</strong> - ' . t('Only rechecks records with existing issues. Much faster - use to verify fixes.') . '</li>';
	$content .= '<li><strong>' . t('Selected Tables') . '</strong> - ' . t('Scan only specific tables you choose. Useful for testing specific sections.') . '</li>';
	$content .= '</ul>';

	// Issue Status Section
	$content .= '<div class="separator"><div>' . t('Issue Status') . '</div></div>';

	$content .= '<ul style="line-height:2.2">';
	$content .= '<li><span class="badge" style="background-color:#17a2b8;color:#fff">' . t('Open') . '</span> - ' . t('Issue detected and needs attention') . '</li>';
	$content .= '<li><span class="badge" style="background-color:#28a745;color:#fff">' . t('Fixed') . '</span> - ' . t('Issue has been resolved (manually marked or auto-detected)') . '</li>';
	$content .= '<li><span class="badge" style="background-color:#6c757d;color:#fff">' . t('Ignored') . '</span> - ' . t('Issue intentionally accepted and will not be flagged again') . '</li>';
	$content .= '</ul>';

	// Best Practices Section
	$content .= '<div class="separator"><div>' . t('Best Practices') . '</div></div>';

	$content .= '<p><strong>' . t('For Content Editors:') . '</strong></p>';
	$content .= '<ul>';
	$content .= '<li>' . t('Always add alt text to images that convey information') . '</li>';
	$content .= '<li>' . t('Use empty alt="" only for truly decorative images') . '</li>';
	$content .= '<li>' . t('Write link text that makes sense out of context (avoid "click here")') . '</li>';
	$content .= '<li>' . t('Use proper heading hierarchy (h1 -> h2 -> h3)') . '</li>';
	$content .= '<li>' . t('Label all form fields with visible labels') . '</li>';
	$content .= '</ul>';

	$content .= '<p><strong>' . t('For Site Administrators:') . '</strong></p>';
	$content .= '<ul>';
	$content .= '<li>' . t('Run scans after major content imports or updates') . '</li>';
	$content .= '<li>' . t('Enable email notifications to catch new issues early') . '</li>';
	$content .= '<li>' . t('Review "Poor Alt Text" warnings - they often indicate fixable issues') . '</li>';
	$content .= '<li>' . t('Use Quick Scan after fixing issues to verify corrections') . '</li>';
	$content .= '</ul>';

	// Requirements Section
	$content .= '<div class="separator"><div>' . t('Requirements') . '</div></div>';

	$content .= '<ul>';
	$content .= '<li>CMS Builder 3.50 or higher</li>';
	$content .= '<li>PHP 8.0 or higher</li>';
	$content .= '<li>MySQL 5.7 or higher</li>';
	$content .= '</ul>';

	// Version Information Section
	$content .= '<div class="separator"><div>' . t('Version Information') . '</div></div>';

	$content .= '<p><strong>' . t('Version') . ':</strong> ' . ($GLOBALS['ACCESSIBILITYCHECKER_VERSION'] ?? '1.00') . '</p>';
	$content .= '<p><strong>' . t('Author') . ':</strong> <a href="https://www.sagentic.com" target="_blank" rel="noopener">Sagentic Web Design</a></p>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}
