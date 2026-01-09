<?php

/**
 * Accessibility Checker Plugin - Helper Functions
 *
 * @package AccessibilityChecker
 */


/**
 * Get the path to the settings JSON file
 *
 * @return string Settings file path
 */
function accessibilityChecker_getSettingsFilePath(): string
{
	return __DIR__ . '/accessibilityChecker_settings.json';
}

/**
 * Load plugin settings from JSON file
 *
 * @return array Settings array
 */
function accessibilityChecker_loadPluginSettings(): array
{
	$settingsFile = accessibilityChecker_getSettingsFilePath();
	$defaults = [
		'enabled' => true,
		'scheduledScans' => true,
		'scanFrequency' => 'daily',
		'lastScanDate' => null,
		'lastScanResults' => null,

		'checks' => [
			'missingAlt' => true,
			'poorAlt' => true,
			'minAltLength' => 5,
			'emptyLinks' => true,
			'genericLinkText' => true,
			'skippedHeadings' => true,
			'tablesWithoutHeaders' => true,
			'emptyButtons' => true,
			'missingFormLabels' => true,
		],

		'emailNotifications' => true,
		'notificationEmail' => '',
		'emailOnlyOnErrors' => true,
		'includeWarningsInTrigger' => false,

		'ignorePatterns' => [
			'altText' => ['decorative', 'spacer'],
			'linkText' => [],
			'excludeTables' => ['_cron_log', 'accounts'],
		],

		'enabledTables' => [],
		'logRetentionDays' => 90,
	];

	if (!file_exists($settingsFile) || !is_readable($settingsFile)) {
		return $defaults;
	}

	$content = @file_get_contents($settingsFile);
	if ($content === false) {
		return $defaults;
	}

	$settings = @json_decode($content, true);

	if (!is_array($settings)) {
		return $defaults;
	}

	// Deep merge for nested arrays
	$merged = array_merge($defaults, $settings);
	if (isset($settings['checks']) && is_array($settings['checks'])) {
		$merged['checks'] = array_merge($defaults['checks'], $settings['checks']);
	}
	if (isset($settings['ignorePatterns']) && is_array($settings['ignorePatterns'])) {
		$merged['ignorePatterns'] = array_merge($defaults['ignorePatterns'], $settings['ignorePatterns']);
	}

	return $merged;
}

/**
 * Save plugin settings to JSON file
 *
 * @param array $settings Settings to save
 * @return bool True on success
 */
function accessibilityChecker_savePluginSettings(array $settings): bool
{
	$settingsFile = accessibilityChecker_getSettingsFilePath();
	$json = json_encode($settings, JSON_PRETTY_PRINT);
	return @file_put_contents($settingsFile, $json) !== false;
}

/**
 * Check if table should be permanently ignored
 *
 * @param string $tableName Table name to check
 * @return bool True if should be ignored
 */
function accessibilityChecker_shouldIgnoreTable(string $tableName): bool
{
	// Always ignore these common system/utility tables
	$ignoredTables = [
		'accounts',
		'uploads',
		'convert_to_webp',
		'indexnow',
		'indexnow_log',
		'linkchecker_results',
		'linkchecker_scans',
		'accessibilitychecker_results',
		'accessibilitychecker_scans',
	];

	// Check exact match
	if (in_array(strtolower($tableName), $ignoredTables)) {
		return true;
	}

	// Check if table name contains _menugroup or _menulink
	if (stripos($tableName, '_menugroup') !== false || stripos($tableName, 'menugroup') !== false) {
		return true;
	}
	if (stripos($tableName, '_menulink') !== false || stripos($tableName, 'menulink') !== false) {
		return true;
	}

	// Check schema for menugroup or link type
	$schema = \loadSchema($tableName);
	$menuType = $schema['menuType'] ?? '';
	if ($menuType === 'menugroup' || $menuType === 'link') {
		return true;
	}

	return false;
}

/**
 * Get list of content tables with text fields
 *
 * @return array List of table names
 */
function accessibilityChecker_getContentTablesWithTextFields(): array
{
	$tableNames = \getSchemaTables();
	$contentTables = [];

	foreach ($tableNames as $tableName) {
		// Skip system tables (starting with _)
		if (str_starts_with($tableName, '_')) {
			continue;
		}

		// Skip permanently ignored tables
		if (accessibilityChecker_shouldIgnoreTable($tableName)) {
			continue;
		}

		$schema = \loadSchema($tableName);
		$hasTextField = false;

		foreach ($schema as $field) {
			if (!is_array($field)) continue;
			$fieldType = $field['type'] ?? '';
			if (in_array($fieldType, ['wysiwyg', 'textbox', 'textfield'])) {
				$hasTextField = true;
				break;
			}
		}

		if ($hasTextField) {
			$contentTables[] = $tableName;
		}
	}

	sort($contentTables);
	return $contentTables;
}

/**
 * Get text fields from schema
 *
 * @param array $schema Table schema
 * @return array List of field info with name and type
 */
function accessibilityChecker_getTextFieldsFromSchema(array $schema): array
{
	$textFields = [];
	foreach ($schema as $fieldName => $field) {
		if (!is_array($field)) continue;
		$fieldType = $field['type'] ?? '';
		if (in_array($fieldType, ['wysiwyg', 'textbox', 'textfield'])) {
			$textFields[] = [
				'name' => $fieldName,
				'type' => $fieldType,
			];
		}
	}
	return $textFields;
}

/**
 * Create database tables if they don't exist
 */
function accessibilityChecker_createTablesIfNeeded(): void
{
	global $TABLE_PREFIX;

	// Check if results table exists
	$tableExists = false;
	$result = \mysqli()->query("SHOW TABLES LIKE '{$TABLE_PREFIX}_accessibilitychecker_results'");
	if ($result && $result->num_rows > 0) {
		$tableExists = true;
	}

	if (!$tableExists) {
		$sql = "CREATE TABLE IF NOT EXISTS `{$TABLE_PREFIX}_accessibilitychecker_results` (
			`num` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`createdDate` datetime DEFAULT NULL,
			`scanId` varchar(50) DEFAULT NULL,
			`scanDate` datetime DEFAULT NULL,
			`tableName` varchar(255) DEFAULT NULL,
			`recordNum` int(10) unsigned DEFAULT NULL,
			`fieldName` varchar(255) DEFAULT NULL,
			`issueType` enum('missing_alt','poor_alt','empty_link','generic_link','skipped_heading','table_no_headers','empty_button','missing_label') DEFAULT NULL,
			`severity` enum('error','warning') DEFAULT NULL,
			`elementHtml` text,
			`context` text,
			`message` varchar(500) DEFAULT NULL,
			`status` enum('open','fixed','ignored') DEFAULT 'open',
			`ignoreReason` varchar(255) DEFAULT NULL,
			`fixedDate` datetime DEFAULT NULL,
			PRIMARY KEY (`num`),
			KEY `scanId` (`scanId`),
			KEY `scanDate` (`scanDate`),
			KEY `tableName` (`tableName`),
			KEY `status` (`status`),
			KEY `issueType` (`issueType`),
			KEY `severity` (`severity`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		\mysqli()->query($sql);
	}

	// Check if scans table exists
	$tableExists = false;
	$result = \mysqli()->query("SHOW TABLES LIKE '{$TABLE_PREFIX}_accessibilitychecker_scans'");
	if ($result && $result->num_rows > 0) {
		$tableExists = true;
	}

	if (!$tableExists) {
		$sql = "CREATE TABLE IF NOT EXISTS `{$TABLE_PREFIX}_accessibilitychecker_scans` (
			`num` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`createdDate` datetime DEFAULT NULL,
			`scanId` varchar(50) DEFAULT NULL,
			`scanType` enum('manual','scheduled') DEFAULT NULL,
			`startTime` datetime DEFAULT NULL,
			`endTime` datetime DEFAULT NULL,
			`tablesScanned` int(10) DEFAULT 0,
			`recordsScanned` int(10) DEFAULT 0,
			`errorsFound` int(10) DEFAULT 0,
			`warningsFound` int(10) DEFAULT 0,
			`duration` decimal(10,2) DEFAULT 0,
			`status` enum('running','completed','failed') DEFAULT 'running',
			`errorMessage` text,
			PRIMARY KEY (`num`),
			KEY `scanId` (`scanId`),
			KEY `createdDate` (`createdDate`),
			KEY `status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		\mysqli()->query($sql);
	}
}

/**
 * Generate unique scan ID
 *
 * @return string Unique scan ID
 */
function accessibilityChecker_generateScanId(): string
{
	return date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
}

/**
 * Create scan record
 *
 * @param string $scanType Scan type (manual or scheduled)
 * @return array Scan info with num and scanId
 */
function accessibilityChecker_createScanRecord(string $scanType): array
{
	$scanId = accessibilityChecker_generateScanId();

	$data = [
		'createdDate=' => 'NOW()',
		'scanId' => $scanId,
		'scanType' => $scanType,
		'startTime=' => 'NOW()',
		'status' => 'running',
	];

	$num = \mysql_insert('_accessibilitychecker_scans', $data);

	return ['num' => $num, 'scanId' => $scanId];
}

/**
 * Complete scan record
 *
 * @param int $scanNum Scan record number
 * @param array $results Scan results
 */
function accessibilityChecker_completeScanRecord(int $scanNum, array $results): void
{
	$data = [
		'endTime=' => 'NOW()',
		'tablesScanned' => $results['tablesScanned'] ?? 0,
		'recordsScanned' => $results['recordsScanned'] ?? 0,
		'errorsFound' => $results['errors'] ?? 0,
		'warningsFound' => $results['warnings'] ?? 0,
		'duration' => $results['duration'] ?? 0,
		'status' => 'completed',
	];

	\mysql_update('_accessibilitychecker_scans', $scanNum, null, $data);
}

/**
 * Fail scan record
 *
 * @param int $scanNum Scan record number
 * @param string $errorMessage Error message
 */
function accessibilityChecker_failScanRecord(int $scanNum, string $errorMessage): void
{
	$data = [
		'endTime=' => 'NOW()',
		'status' => 'failed',
		'errorMessage' => $errorMessage,
	];

	\mysql_update('_accessibilitychecker_scans', $scanNum, null, $data);
}

/**
 * Log accessibility issue
 *
 * @param string $scanId Scan ID
 * @param string $tableName Table name
 * @param int $recordNum Record number
 * @param string $fieldName Field name
 * @param string $issueType Issue type
 * @param string $severity Severity (error or warning)
 * @param string $elementHtml The offending HTML element
 * @param string $context Surrounding context
 * @param string $message Human-readable message
 */
function accessibilityChecker_logIssue(
	string $scanId,
	string $tableName,
	int $recordNum,
	string $fieldName,
	string $issueType,
	string $severity,
	string $elementHtml,
	string $context,
	string $message
): void {
	// Check if this exact issue already exists (same table, record, field, type, and element)
	// This prevents duplicates when rescanning
	$elementHash = md5($elementHtml);
	$whereClause = mysql_escapef(
		"`tableName` = ? AND `recordNum` = ? AND `fieldName` = ? AND `issueType` = ? AND MD5(`elementHtml`) = ?",
		$tableName, $recordNum, $fieldName, $issueType, $elementHash
	);
	$existing = \mysql_select('_accessibilitychecker_results', $whereClause);

	if (!empty($existing)) {
		// Issue already exists - update the scan date and reopen if fixed
		$existingIssue = $existing[0];
		$updateData = [
			'scanId' => $scanId,
			'scanDate=' => 'NOW()',
		];
		// Reopen if it was marked fixed but is still present
		if ($existingIssue['status'] === 'fixed') {
			$updateData['status'] = 'open';
		}
		\mysql_update('_accessibilitychecker_results', $existingIssue['num'], null, $updateData);
		return;
	}

	// New issue - insert it
	$data = [
		'createdDate=' => 'NOW()',
		'scanId' => $scanId,
		'scanDate=' => 'NOW()',
		'tableName' => $tableName,
		'recordNum' => $recordNum,
		'fieldName' => $fieldName,
		'issueType' => $issueType,
		'severity' => $severity,
		'elementHtml' => mb_substr($elementHtml, 0, 65000),
		'context' => mb_substr($context, 0, 65000),
		'message' => mb_substr($message, 0, 500),
		'status' => 'open',
	];

	\mysql_insert('_accessibilitychecker_results', $data);
}

// =============================================================================
// ACCESSIBILITY CHECKS
// =============================================================================

/**
 * Check for missing alt text on images
 *
 * @param string $content HTML content to check
 * @return array Array of issues found
 */
function accessibilityChecker_checkMissingAlt(string $content): array
{
	$issues = [];

	// Find all img tags
	preg_match_all('/<img[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE);

	foreach ($matches[0] as $match) {
		$imgTag = $match[0];
		$position = $match[1];

		// Check if alt attribute exists
		if (!preg_match('/\salt\s*=/i', $imgTag)) {
			$issues[] = [
				'type' => 'missing_alt',
				'severity' => 'error',
				'element' => $imgTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Image is missing alt attribute'),
			];
		}
	}

	return $issues;
}

/**
 * Check for poor alt text on images
 *
 * @param string $content HTML content to check
 * @param array $settings Plugin settings
 * @return array Array of issues found
 */
function accessibilityChecker_checkPoorAlt(string $content, array $settings): array
{
	$issues = [];
	$minLength = $settings['checks']['minAltLength'] ?? 5;
	$ignorePatterns = $settings['ignorePatterns']['altText'] ?? ['decorative', 'spacer'];

	// Find all img tags with alt attribute
	preg_match_all('/<img[^>]+alt\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach ($matches as $match) {
		$imgTag = $match[0][0];
		$altText = $match[1][0];
		$position = $match[0][1];

		// Skip if alt text matches ignore patterns
		$shouldIgnore = false;
		foreach ($ignorePatterns as $pattern) {
			if (stripos($altText, $pattern) !== false) {
				$shouldIgnore = true;
				break;
			}
		}
		if ($shouldIgnore) continue;

		// Check for filename patterns
		if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i', $altText)) {
			$issues[] = [
				'type' => 'poor_alt',
				'severity' => 'warning',
				'element' => $imgTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Alt text appears to be a filename: %s', $altText),
			];
			continue;
		}

		// Check for too short alt text (but not empty - that might be intentional for decorative)
		if (strlen(trim($altText)) > 0 && strlen(trim($altText)) < $minLength) {
			$issues[] = [
				'type' => 'poor_alt',
				'severity' => 'warning',
				'element' => $imgTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Alt text is too short (%d characters, minimum %d): "%s"', strlen(trim($altText)), $minLength, $altText),
			];
			continue;
		}

		// Check for generic patterns
		$genericPatterns = [
			'/^image\s*(of)?$/i',
			'/^photo\s*(of)?$/i',
			'/^picture\s*(of)?$/i',
			'/^IMG_/i',
			'/^DSC_/i',
			'/^screenshot$/i',
			'/^untitled$/i',
			'/^image\d+$/i',
		];

		foreach ($genericPatterns as $pattern) {
			if (preg_match($pattern, trim($altText))) {
				$issues[] = [
					'type' => 'poor_alt',
					'severity' => 'warning',
					'element' => $imgTag,
					'context' => accessibilityChecker_getContext($content, $position),
					'message' => t('Alt text is generic: "%s"', $altText),
				];
				break;
			}
		}
	}

	return $issues;
}

/**
 * Check for empty links (no accessible text content)
 *
 * @param string $content HTML content to check
 * @return array Array of issues found
 */
function accessibilityChecker_checkEmptyLinks(string $content): array
{
	$issues = [];

	// Find all anchor tags
	preg_match_all('/<a[^>]*>.*?<\/a>/is', $content, $matches, PREG_OFFSET_CAPTURE);

	foreach ($matches[0] as $match) {
		$linkTag = $match[0];
		$position = $match[1];

		// Extract text content (strip HTML tags)
		$textContent = trim(strip_tags($linkTag));

		// Check if link has accessible content
		$hasAccessibleContent = false;

		// Check for text content
		if (!empty($textContent)) {
			$hasAccessibleContent = true;
		}

		// Check for aria-label
		if (preg_match('/aria-label\s*=\s*["\']([^"\']+)["\']/i', $linkTag, $ariaMatch)) {
			if (!empty(trim($ariaMatch[1]))) {
				$hasAccessibleContent = true;
			}
		}

		// Check for aria-labelledby
		if (preg_match('/aria-labelledby\s*=\s*["\']([^"\']+)["\']/i', $linkTag)) {
			$hasAccessibleContent = true;
		}

		// NOTE: title attribute is NOT accepted as accessible content
		// per WCAG guidelines - it's not reliably announced by screen readers
		// and doesn't work on touch devices

		// Check for image with alt text inside link
		if (preg_match('/<img[^>]+alt\s*=\s*["\']([^"\']+)["\']/i', $linkTag, $imgMatch)) {
			if (!empty(trim($imgMatch[1]))) {
				$hasAccessibleContent = true;
			}
		}

		if (!$hasAccessibleContent) {
			$issues[] = [
				'type' => 'empty_link',
				'severity' => 'error',
				'element' => $linkTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Link has no accessible text content'),
			];
		}
	}

	return $issues;
}

/**
 * Check for generic link text
 *
 * @param string $content HTML content to check
 * @param array $settings Plugin settings
 * @return array Array of issues found
 */
function accessibilityChecker_checkGenericLinkText(string $content, array $settings): array
{
	$issues = [];
	$ignorePatterns = $settings['ignorePatterns']['linkText'] ?? [];

	$genericPhrases = [
		'click here',
		'click',
		'here',
		'read more',
		'learn more',
		'more',
		'link',
		'this link',
		'this page',
		'go',
		'download',
		'view',
		'see more',
		'details',
		'info',
		'information',
	];

	// Find all anchor tags
	preg_match_all('/<a[^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach ($matches as $match) {
		$linkTag = $match[0][0];
		$linkContent = $match[1][0];
		$position = $match[0][1];

		// Get text content
		$textContent = strtolower(trim(strip_tags($linkContent)));

		// Skip if empty (handled by empty links check)
		if (empty($textContent)) continue;

		// Skip if matches ignore patterns
		$shouldIgnore = false;
		foreach ($ignorePatterns as $pattern) {
			if (stripos($textContent, $pattern) !== false) {
				$shouldIgnore = true;
				break;
			}
		}
		if ($shouldIgnore) continue;

		// Check against generic phrases
		foreach ($genericPhrases as $phrase) {
			if ($textContent === $phrase) {
				$issues[] = [
					'type' => 'generic_link',
					'severity' => 'warning',
					'element' => $linkTag,
					'context' => accessibilityChecker_getContext($content, $position),
					'message' => t('Link text is generic: "%s"', $textContent),
				];
				break;
			}
		}
	}

	return $issues;
}

/**
 * Check for skipped heading levels
 *
 * @param string $content HTML content to check
 * @return array Array of issues found
 */
function accessibilityChecker_checkSkippedHeadings(string $content): array
{
	$issues = [];

	// Find all heading tags
	preg_match_all('/<h([1-6])[^>]*>.*?<\/h\1>/is', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	$lastLevel = 0;
	foreach ($matches as $match) {
		$fullTag = $match[0][0];
		$level = intval($match[1][0]);
		$position = $match[0][1];

		// Check if level is skipped (e.g., h2 -> h4)
		if ($lastLevel > 0 && $level > $lastLevel + 1) {
			$issues[] = [
				'type' => 'skipped_heading',
				'severity' => 'warning',
				'element' => $fullTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Heading level skipped from h%d to h%d', $lastLevel, $level),
			];
		}

		$lastLevel = $level;
	}

	return $issues;
}

/**
 * Check for tables without headers
 *
 * @param string $content HTML content to check
 * @return array Array of issues found
 */
function accessibilityChecker_checkTablesWithoutHeaders(string $content): array
{
	$issues = [];

	// Find all table tags
	preg_match_all('/<table[^>]*>.*?<\/table>/is', $content, $matches, PREG_OFFSET_CAPTURE);

	foreach ($matches[0] as $match) {
		$tableTag = $match[0];
		$position = $match[1];

		// Skip layout tables (role="presentation")
		if (preg_match('/role\s*=\s*["\']presentation["\']/i', $tableTag)) {
			continue;
		}

		// Check for th elements
		if (!preg_match('/<th[^>]*>/i', $tableTag)) {
			$issues[] = [
				'type' => 'table_no_headers',
				'severity' => 'warning',
				'element' => mb_substr($tableTag, 0, 200) . (strlen($tableTag) > 200 ? '...' : ''),
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Table has no header cells (th elements)'),
			];
		}
	}

	return $issues;
}

/**
 * Check for empty buttons
 *
 * @param string $content HTML content to check
 * @return array Array of issues found
 */
function accessibilityChecker_checkEmptyButtons(string $content): array
{
	$issues = [];

	// Find button elements
	preg_match_all('/<button[^>]*>.*?<\/button>/is', $content, $matches, PREG_OFFSET_CAPTURE);

	foreach ($matches[0] as $match) {
		$buttonTag = $match[0];
		$position = $match[1];

		$hasAccessibleContent = false;

		// Check for text content
		$textContent = trim(strip_tags($buttonTag));
		if (!empty($textContent)) {
			$hasAccessibleContent = true;
		}

		// Check for aria-label
		if (preg_match('/aria-label\s*=\s*["\']([^"\']+)["\']/i', $buttonTag, $ariaMatch)) {
			if (!empty(trim($ariaMatch[1]))) {
				$hasAccessibleContent = true;
			}
		}

		// Check for aria-labelledby
		if (preg_match('/aria-labelledby\s*=\s*["\']([^"\']+)["\']/i', $buttonTag)) {
			$hasAccessibleContent = true;
		}

		// NOTE: title attribute is NOT accepted as accessible content
		// per WCAG guidelines - it's not reliably announced by screen readers

		if (!$hasAccessibleContent) {
			$issues[] = [
				'type' => 'empty_button',
				'severity' => 'error',
				'element' => $buttonTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Button has no accessible name'),
			];
		}
	}

	// Find input type="button" and type="submit" without value
	preg_match_all('/<input[^>]+type\s*=\s*["\'](?:button|submit)["\'][^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE);

	foreach ($matches[0] as $match) {
		$inputTag = $match[0];
		$position = $match[1];

		$hasAccessibleContent = false;

		// Check for value attribute
		if (preg_match('/\svalue\s*=\s*["\']([^"\']+)["\']/i', $inputTag, $valueMatch)) {
			if (!empty(trim($valueMatch[1]))) {
				$hasAccessibleContent = true;
			}
		}

		// Check for aria-label
		if (preg_match('/aria-label\s*=\s*["\']([^"\']+)["\']/i', $inputTag, $ariaMatch)) {
			if (!empty(trim($ariaMatch[1]))) {
				$hasAccessibleContent = true;
			}
		}

		// Check for aria-labelledby
		if (preg_match('/aria-labelledby\s*=\s*["\']([^"\']+)["\']/i', $inputTag)) {
			$hasAccessibleContent = true;
		}

		// NOTE: title attribute is NOT accepted as accessible content

		if (!$hasAccessibleContent) {
			$issues[] = [
				'type' => 'empty_button',
				'severity' => 'error',
				'element' => $inputTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Button input has no accessible name'),
			];
		}
	}

	return $issues;
}

/**
 * Check for missing form labels
 *
 * @param string $content HTML content to check
 * @return array Array of issues found
 */
function accessibilityChecker_checkMissingFormLabels(string $content): array
{
	$issues = [];

	// Find form inputs that need labels
	$inputTypes = ['text', 'email', 'password', 'tel', 'url', 'number', 'search', 'date', 'time', 'datetime-local'];
	$inputPattern = '/<input[^>]+type\s*=\s*["\'](' . implode('|', $inputTypes) . ')["\'][^>]*>/i';

	preg_match_all($inputPattern, $content, $matches, PREG_OFFSET_CAPTURE);

	foreach ($matches[0] as $match) {
		$inputTag = $match[0];
		$position = $match[1];

		$hasLabel = false;

		// Check for aria-label
		if (preg_match('/aria-label\s*=\s*["\']([^"\']+)["\']/i', $inputTag, $ariaMatch)) {
			if (!empty(trim($ariaMatch[1]))) {
				$hasLabel = true;
			}
		}

		// Check for aria-labelledby
		if (preg_match('/aria-labelledby\s*=\s*["\']([^"\']+)["\']/i', $inputTag)) {
			$hasLabel = true;
		}

		// Check for title attribute (fallback)
		if (preg_match('/\stitle\s*=\s*["\']([^"\']+)["\']/i', $inputTag, $titleMatch)) {
			if (!empty(trim($titleMatch[1]))) {
				$hasLabel = true;
			}
		}

		// Check for associated label via id
		if (preg_match('/\sid\s*=\s*["\']([^"\']+)["\']/i', $inputTag, $idMatch)) {
			$inputId = $idMatch[1];
			// Look for label with for attribute matching this id
			if (preg_match('/<label[^>]+for\s*=\s*["\']' . preg_quote($inputId, '/') . '["\']/i', $content)) {
				$hasLabel = true;
			}
		}

		// Check if input is inside a label element
		// This is a simplified check - find if there's a label containing this input
		$beforeContent = substr($content, 0, $position);
		$afterContent = substr($content, $position);

		// Count open and close label tags before this position
		$openLabels = preg_match_all('/<label[^>]*>/i', $beforeContent);
		$closeLabels = preg_match_all('/<\/label>/i', $beforeContent);

		if ($openLabels > $closeLabels) {
			$hasLabel = true;
		}

		if (!$hasLabel) {
			$issues[] = [
				'type' => 'missing_label',
				'severity' => 'error',
				'element' => $inputTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Form input is missing an associated label'),
			];
		}
	}

	// Check textarea elements
	preg_match_all('/<textarea[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE);

	foreach ($matches[0] as $match) {
		$textareaTag = $match[0];
		$position = $match[1];

		$hasLabel = false;

		// Same checks as input
		if (preg_match('/aria-label\s*=\s*["\']([^"\']+)["\']/i', $textareaTag)) {
			$hasLabel = true;
		}

		if (preg_match('/aria-labelledby\s*=\s*["\']([^"\']+)["\']/i', $textareaTag)) {
			$hasLabel = true;
		}

		if (preg_match('/\stitle\s*=\s*["\']([^"\']+)["\']/i', $textareaTag)) {
			$hasLabel = true;
		}

		if (preg_match('/\sid\s*=\s*["\']([^"\']+)["\']/i', $textareaTag, $idMatch)) {
			$inputId = $idMatch[1];
			if (preg_match('/<label[^>]+for\s*=\s*["\']' . preg_quote($inputId, '/') . '["\']/i', $content)) {
				$hasLabel = true;
			}
		}

		$beforeContent = substr($content, 0, $position);
		$openLabels = preg_match_all('/<label[^>]*>/i', $beforeContent);
		$closeLabels = preg_match_all('/<\/label>/i', $beforeContent);
		if ($openLabels > $closeLabels) {
			$hasLabel = true;
		}

		if (!$hasLabel) {
			$issues[] = [
				'type' => 'missing_label',
				'severity' => 'error',
				'element' => $textareaTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Textarea is missing an associated label'),
			];
		}
	}

	// Check select elements
	preg_match_all('/<select[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE);

	foreach ($matches[0] as $match) {
		$selectTag = $match[0];
		$position = $match[1];

		$hasLabel = false;

		if (preg_match('/aria-label\s*=\s*["\']([^"\']+)["\']/i', $selectTag)) {
			$hasLabel = true;
		}

		if (preg_match('/aria-labelledby\s*=\s*["\']([^"\']+)["\']/i', $selectTag)) {
			$hasLabel = true;
		}

		if (preg_match('/\stitle\s*=\s*["\']([^"\']+)["\']/i', $selectTag)) {
			$hasLabel = true;
		}

		if (preg_match('/\sid\s*=\s*["\']([^"\']+)["\']/i', $selectTag, $idMatch)) {
			$inputId = $idMatch[1];
			if (preg_match('/<label[^>]+for\s*=\s*["\']' . preg_quote($inputId, '/') . '["\']/i', $content)) {
				$hasLabel = true;
			}
		}

		$beforeContent = substr($content, 0, $position);
		$openLabels = preg_match_all('/<label[^>]*>/i', $beforeContent);
		$closeLabels = preg_match_all('/<\/label>/i', $beforeContent);
		if ($openLabels > $closeLabels) {
			$hasLabel = true;
		}

		if (!$hasLabel) {
			$issues[] = [
				'type' => 'missing_label',
				'severity' => 'error',
				'element' => $selectTag,
				'context' => accessibilityChecker_getContext($content, $position),
				'message' => t('Select element is missing an associated label'),
			];
		}
	}

	return $issues;
}

/**
 * Get context around a position in content
 *
 * @param string $content Full content
 * @param int $position Position to get context around
 * @param int $length Context length on each side
 * @return string Context string
 */
function accessibilityChecker_getContext(string $content, int $position, int $length = 50): string
{
	$start = max(0, $position - $length);
	$end = min(strlen($content), $position + $length);

	$context = substr($content, $start, $end - $start);

	if ($start > 0) {
		$context = '...' . $context;
	}
	if ($end < strlen($content)) {
		$context = $context . '...';
	}

	return $context;
}

// =============================================================================
// SCANNING FUNCTIONS
// =============================================================================

/**
 * Scan a single record for accessibility issues
 *
 * @param string $scanId Scan ID
 * @param string $tableName Table name
 * @param array $record Record data
 * @param array $textFields Text fields to check
 * @param array $settings Plugin settings
 * @return array Count of errors and warnings found
 */
function accessibilityChecker_scanRecord(
	string $scanId,
	string $tableName,
	array $record,
	array $textFields,
	array $settings
): array {
	$errors = 0;
	$warnings = 0;
	$checks = $settings['checks'];

	foreach ($textFields as $fieldInfo) {
		$fieldName = $fieldInfo['name'];
		$content = $record[$fieldName] ?? '';

		if (empty($content)) continue;

		$issues = [];

		// Run enabled checks
		if ($checks['missingAlt'] ?? true) {
			$issues = array_merge($issues, accessibilityChecker_checkMissingAlt($content));
		}

		if ($checks['poorAlt'] ?? true) {
			$issues = array_merge($issues, accessibilityChecker_checkPoorAlt($content, $settings));
		}

		if ($checks['emptyLinks'] ?? true) {
			$issues = array_merge($issues, accessibilityChecker_checkEmptyLinks($content));
		}

		if ($checks['genericLinkText'] ?? true) {
			$issues = array_merge($issues, accessibilityChecker_checkGenericLinkText($content, $settings));
		}

		if ($checks['skippedHeadings'] ?? true) {
			$issues = array_merge($issues, accessibilityChecker_checkSkippedHeadings($content));
		}

		if ($checks['tablesWithoutHeaders'] ?? true) {
			$issues = array_merge($issues, accessibilityChecker_checkTablesWithoutHeaders($content));
		}

		if ($checks['emptyButtons'] ?? true) {
			$issues = array_merge($issues, accessibilityChecker_checkEmptyButtons($content));
		}

		if ($checks['missingFormLabels'] ?? true) {
			$issues = array_merge($issues, accessibilityChecker_checkMissingFormLabels($content));
		}

		// Log issues
		foreach ($issues as $issue) {
			accessibilityChecker_logIssue(
				$scanId,
				$tableName,
				intval($record['num']),
				$fieldName,
				$issue['type'],
				$issue['severity'],
				$issue['element'],
				$issue['context'],
				$issue['message']
			);

			if ($issue['severity'] === 'error') {
				$errors++;
			} else {
				$warnings++;
			}
		}
	}

	return ['errors' => $errors, 'warnings' => $warnings];
}

/**
 * Perform scan
 *
 * @param array $tables Tables to scan (empty = all enabled)
 * @param string $scanId Scan ID
 * @return array Scan results
 */
function accessibilityChecker_performScan(array $tables, string $scanId): array
{
	// Buffer any stray output to prevent it from appearing on the page
	ob_start();

	$startTime = microtime(true);
	$settings = accessibilityChecker_loadPluginSettings();

	$results = [
		'tablesScanned' => 0,
		'recordsScanned' => 0,
		'errors' => 0,
		'warnings' => 0,
		'duration' => 0,
	];

	// Get tables to scan
	if (empty($tables)) {
		$tables = empty($settings['enabledTables'])
			? accessibilityChecker_getContentTablesWithTextFields()
			: $settings['enabledTables'];
	}

	foreach ($tables as $tableName) {
		$schema = \loadSchema($tableName);
		$textFields = accessibilityChecker_getTextFieldsFromSchema($schema);

		if (empty($textFields)) continue;

		$results['tablesScanned']++;
		$records = \mysql_select($tableName, "1=1");

		foreach ($records as $record) {
			$results['recordsScanned']++;

			$recordResults = accessibilityChecker_scanRecord(
				$scanId,
				$tableName,
				$record,
				$textFields,
				$settings
			);

			$results['errors'] += $recordResults['errors'];
			$results['warnings'] += $recordResults['warnings'];
		}
	}

	$results['duration'] = round(microtime(true) - $startTime, 2);

	// Discard any stray output
	ob_end_clean();

	// Update settings with last scan info
	$settings['lastScanDate'] = date('Y-m-d H:i:s');
	$settings['lastScanResults'] = $results;
	accessibilityChecker_savePluginSettings($settings);

	return $results;
}

/**
 * Run manual scan
 *
 * @param array $selectedTables Tables to scan
 * @return array Scan results
 */
function accessibilityChecker_runManualScan(array $selectedTables = []): array
{
	$scanInfo = accessibilityChecker_createScanRecord('manual');

	try {
		$results = accessibilityChecker_performScan($selectedTables, $scanInfo['scanId']);
		accessibilityChecker_completeScanRecord($scanInfo['num'], $results);
		return $results;
	} catch (\Exception $e) {
		accessibilityChecker_failScanRecord($scanInfo['num'], $e->getMessage());
		throw $e;
	}
}

/**
 * Run scheduled scan
 *
 * @return array Scan results
 */
function accessibilityChecker_runScheduledScan(): array
{
	$scanInfo = accessibilityChecker_createScanRecord('scheduled');

	try {
		$results = accessibilityChecker_performScan([], $scanInfo['scanId']);
		accessibilityChecker_completeScanRecord($scanInfo['num'], $results);
		return $results;
	} catch (\Exception $e) {
		accessibilityChecker_failScanRecord($scanInfo['num'], $e->getMessage());
		throw $e;
	}
}

/**
 * Run quick scan (only records with previous issues)
 *
 * @return array Scan results
 */
function accessibilityChecker_runQuickScan(): array
{
	$startTime = microtime(true);
	$settings = accessibilityChecker_loadPluginSettings();
	$scanInfo = accessibilityChecker_createScanRecord('manual');

	$results = [
		'tablesScanned' => 0,
		'recordsScanned' => 0,
		'errors' => 0,
		'warnings' => 0,
		'duration' => 0,
	];

	try {
		// Get unique table/record combinations with open issues
		$openIssues = \mysql_select(
			'_accessibilitychecker_results',
			"`status` = 'open' GROUP BY `tableName`, `recordNum`"
		);

		$scannedRecords = [];
		$tablesScanned = [];

		foreach ($openIssues as $issue) {
			$tableName = $issue['tableName'];
			$recordNum = intval($issue['recordNum']);

			// Skip template file issues - they can't be quick-scanned via database
			if ($tableName === '_template_file') {
				continue;
			}

			// Skip if already scanned this record
			$recordKey = "{$tableName}_{$recordNum}";
			if (isset($scannedRecords[$recordKey])) continue;
			$scannedRecords[$recordKey] = true;

			// Track tables
			if (!isset($tablesScanned[$tableName])) {
				$tablesScanned[$tableName] = true;
			}

			// Get record
			$record = \mysql_get($tableName, $recordNum);
			if (!$record) {
				// Record deleted - mark issues as fixed
				accessibilityChecker_markRecordIssuesFixed($tableName, $recordNum);
				continue;
			}

			// Get schema and text fields
			$schema = \loadSchema($tableName);
			$textFields = accessibilityChecker_getTextFieldsFromSchema($schema);

			if (empty($textFields)) continue;

			$results['recordsScanned']++;

			$recordResults = accessibilityChecker_scanRecord(
				$scanInfo['scanId'],
				$tableName,
				$record,
				$textFields,
				$settings
			);

			$results['errors'] += $recordResults['errors'];
			$results['warnings'] += $recordResults['warnings'];
		}

		$results['tablesScanned'] = count($tablesScanned);
		$results['duration'] = round(microtime(true) - $startTime, 2);

		accessibilityChecker_completeScanRecord($scanInfo['num'], $results);

		// Update settings
		$settings['lastScanDate'] = date('Y-m-d H:i:s');
		$settings['lastScanResults'] = $results;
		accessibilityChecker_savePluginSettings($settings);

		return $results;
	} catch (\Exception $e) {
		accessibilityChecker_failScanRecord($scanInfo['num'], $e->getMessage());
		throw $e;
	}
}

/**
 * Mark all issues for a record as fixed
 *
 * @param string $tableName Table name
 * @param int $recordNum Record number
 */
function accessibilityChecker_markRecordIssuesFixed(string $tableName, int $recordNum): void
{
	global $TABLE_PREFIX;

	$query = "UPDATE `{$TABLE_PREFIX}_accessibilitychecker_results`
			  SET `status` = 'fixed', `fixedDate` = NOW()
			  WHERE `tableName` = ? AND `recordNum` = ? AND `status` = 'open'";

	\mysqli()->query(\mysql_escapef($query, $tableName, $recordNum));
}

// =============================================================================
// DATA ACCESS FUNCTIONS
// =============================================================================

/**
 * Get last scan info
 *
 * @return array|null Last scan info or null
 */
function accessibilityChecker_getLastScanInfo(): ?array
{
	$scans = \mysql_select('_accessibilitychecker_scans', "1=1 ORDER BY `createdDate` DESC LIMIT 1");
	return $scans[0] ?? null;
}

/**
 * Get scan statistics
 *
 * @return array Statistics array
 */
function accessibilityChecker_getScanStats(): array
{
	$stats = [
		'totalOpen' => 0,
		'errors' => 0,
		'warnings' => 0,
		'fixed' => 0,
		'ignored' => 0,
	];

	// Get current open issues
	$stats['errors'] = (int)\mysql_count('_accessibilitychecker_results', "`severity` = 'error' AND `status` = 'open'");
	$stats['warnings'] = (int)\mysql_count('_accessibilitychecker_results', "`severity` = 'warning' AND `status` = 'open'");
	$stats['fixed'] = (int)\mysql_count('_accessibilitychecker_results', "`status` = 'fixed'");
	$stats['ignored'] = (int)\mysql_count('_accessibilitychecker_results', "`status` = 'ignored'");
	$stats['totalOpen'] = $stats['errors'] + $stats['warnings'];

	return $stats;
}

/**
 * Get recent issues
 *
 * @param int $limit Number of entries to retrieve
 * @return array Recent issues
 */
function accessibilityChecker_getRecentIssues(int $limit = 10): array
{
	return \mysql_select('_accessibilitychecker_results', "`status` = 'open' ORDER BY `createdDate` DESC LIMIT {$limit}");
}

/**
 * Get issue type label
 *
 * @param string $issueType Issue type
 * @return string Human-readable label
 */
function accessibilityChecker_getIssueTypeLabel(string $issueType): string
{
	$labels = [
		'missing_alt' => t('Missing Alt Text'),
		'poor_alt' => t('Poor Alt Text'),
		'empty_link' => t('Empty Link'),
		'generic_link' => t('Generic Link Text'),
		'skipped_heading' => t('Skipped Heading'),
		'table_no_headers' => t('Table Without Headers'),
		'empty_button' => t('Empty Button'),
		'missing_label' => t('Missing Form Label'),
	];

	return $labels[$issueType] ?? $issueType;
}

/**
 * Mark issues as fixed
 *
 * @param array $issueIds Array of issue IDs
 * @return int Number of issues marked
 */
function accessibilityChecker_markIssuesAsFixed(array $issueIds): int
{
	$count = 0;
	foreach ($issueIds as $issueId) {
		$data = [
			'status' => 'fixed',
			'fixedDate=' => 'NOW()',
		];
		\mysql_update('_accessibilitychecker_results', intval($issueId), null, $data);
		$count++;
	}
	return $count;
}

/**
 * Mark issues as ignored
 *
 * @param array $issueIds Array of issue IDs
 * @param string $reason Reason for ignoring
 * @return int Number of issues marked
 */
function accessibilityChecker_markIssuesAsIgnored(array $issueIds, string $reason = ''): int
{
	$count = 0;
	foreach ($issueIds as $issueId) {
		$data = [
			'status' => 'ignored',
			'ignoreReason' => $reason,
		];
		\mysql_update('_accessibilitychecker_results', intval($issueId), null, $data);
		$count++;
	}
	return $count;
}

/**
 * Reopen issues
 *
 * @param array $issueIds Array of issue IDs
 * @return int Number of issues reopened
 */
function accessibilityChecker_reopenIssues(array $issueIds): int
{
	$count = 0;
	foreach ($issueIds as $issueId) {
		$data = [
			'status' => 'open',
			'fixedDate' => null,
			'ignoreReason' => '',
		];
		\mysql_update('_accessibilitychecker_results', intval($issueId), null, $data);
		$count++;
	}
	return $count;
}

/**
 * Recheck issues
 *
 * @param array $issueIds Array of issue IDs
 * @return array Results with rechecked and fixed counts
 */
function accessibilityChecker_recheckIssues(array $issueIds): array
{
	$settings = accessibilityChecker_loadPluginSettings();
	$rechecked = 0;
	$fixed = 0;

	foreach ($issueIds as $issueId) {
		$issue = \mysql_get('_accessibilitychecker_results', intval($issueId));
		if (!$issue) continue;

		// Skip ignored issues
		if ($issue['status'] === 'ignored') continue;

		$rechecked++;

		// Check if record still exists
		$record = \mysql_get($issue['tableName'], intval($issue['recordNum']));
		if (!$record) {
			// Record deleted - mark as fixed
			accessibilityChecker_markIssuesAsFixed([intval($issueId)]);
			$fixed++;
			continue;
		}

		// Get field content
		$content = $record[$issue['fieldName']] ?? '';

		// Check if the specific issue still exists
		$stillExists = accessibilityChecker_checkIfIssueExists(
			$content,
			$issue['issueType'],
			$issue['elementHtml'],
			$settings
		);

		if (!$stillExists) {
			accessibilityChecker_markIssuesAsFixed([intval($issueId)]);
			$fixed++;
		}
	}

	return ['rechecked' => $rechecked, 'fixed' => $fixed];
}

/**
 * Check if a specific issue still exists in content
 *
 * @param string $content Content to check
 * @param string $issueType Issue type
 * @param string $elementHtml Original element HTML
 * @param array $settings Plugin settings
 * @return bool True if issue still exists
 */
function accessibilityChecker_checkIfIssueExists(
	string $content,
	string $issueType,
	string $elementHtml,
	array $settings
): bool {
	// If the original element is no longer in the content, issue is fixed
	if (strpos($content, $elementHtml) === false) {
		return false;
	}

	// Run the specific check to see if the issue still exists
	$issues = [];

	switch ($issueType) {
		case 'missing_alt':
			$issues = accessibilityChecker_checkMissingAlt($content);
			break;
		case 'poor_alt':
			$issues = accessibilityChecker_checkPoorAlt($content, $settings);
			break;
		case 'empty_link':
			$issues = accessibilityChecker_checkEmptyLinks($content);
			break;
		case 'generic_link':
			$issues = accessibilityChecker_checkGenericLinkText($content, $settings);
			break;
		case 'skipped_heading':
			$issues = accessibilityChecker_checkSkippedHeadings($content);
			break;
		case 'table_no_headers':
			$issues = accessibilityChecker_checkTablesWithoutHeaders($content);
			break;
		case 'empty_button':
			$issues = accessibilityChecker_checkEmptyButtons($content);
			break;
		case 'missing_label':
			$issues = accessibilityChecker_checkMissingFormLabels($content);
			break;
	}

	// Check if any of the found issues match our original element
	foreach ($issues as $issue) {
		if ($issue['element'] === $elementHtml) {
			return true;
		}
	}

	return false;
}

/**
 * Get all content tables (for settings page)
 *
 * @return array List of table names with info
 */
function accessibilityChecker_getAllTables(): array
{
	$tableNames = \getSchemaTables();
	$tables = [];

	foreach ($tableNames as $tableName) {
		// Skip system tables (starting with _)
		if (str_starts_with($tableName, '_')) {
			continue;
		}

		// Skip permanently ignored tables
		if (accessibilityChecker_shouldIgnoreTable($tableName)) {
			continue;
		}

		$schema = \loadSchema($tableName);
		$tables[] = [
			'name' => $tableName,
			'menuName' => $schema['menuName'] ?? $tableName,
			'menuType' => $schema['menuType'] ?? 'multi',
		];
	}

	return $tables;
}

/**
 * Get enabled tables from settings
 *
 * @return array List of enabled table names
 */
function accessibilityChecker_getEnabledTables(): array
{
	$settings = accessibilityChecker_loadPluginSettings();
	$enabledTables = $settings['enabledTables'] ?? [];

	// If no tables enabled, return all content tables with text fields
	if (empty($enabledTables)) {
		return accessibilityChecker_getContentTablesWithTextFields();
	}

	return $enabledTables;
}

/**
 * Clear all accessibility check results and scan history
 *
 * @return int Number of results cleared
 */
function accessibilityChecker_clearAllResults(): int
{
	global $TABLE_PREFIX;

	// Get count before deleting
	$result = \mysqli()->query("SELECT COUNT(*) as count FROM `{$TABLE_PREFIX}_accessibilitychecker_results`");
	$count = 0;
	if ($result) {
		$row = $result->fetch_assoc();
		$count = intval($row['count']);
	}

	// Delete all results
	\mysqli()->query("TRUNCATE TABLE `{$TABLE_PREFIX}_accessibilitychecker_results`");

	// Also clear scan history
	\mysqli()->query("TRUNCATE TABLE `{$TABLE_PREFIX}_accessibilitychecker_scans`");

	// Reset last scan status in settings
	$settings = accessibilityChecker_loadPluginSettings();
	$settings['lastScanDate'] = null;
	$settings['lastScanResults'] = null;
	accessibilityChecker_savePluginSettings($settings);

	return $count;
}

/**
 * Cleanup old scans based on retention settings
 */
function accessibilityChecker_cleanupOldScans(): void
{
	global $TABLE_PREFIX;

	$settings = accessibilityChecker_loadPluginSettings();
	$retentionDays = $settings['logRetentionDays'];
	if ($retentionDays <= 0) {
		return;
	}

	$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

	// Clean up scans table
	$query = "DELETE FROM `{$TABLE_PREFIX}_accessibilitychecker_scans` WHERE `createdDate` < ?";
	\mysqli()->query(\mysql_escapef($query, $cutoffDate));
}

/**
 * Send notification email
 *
 * @param array $results Scan results
 */
function accessibilityChecker_sendNotificationEmail(array $results): void
{
	$settings = accessibilityChecker_loadPluginSettings();

	if (!$settings['emailNotifications']) return;

	// Check trigger conditions
	$hasErrors = ($results['errors'] ?? 0) > 0;
	$hasWarnings = ($results['warnings'] ?? 0) > 0;

	if ($settings['emailOnlyOnErrors']) {
		if (!$hasErrors && !($settings['includeWarningsInTrigger'] && $hasWarnings)) {
			return;
		}
	}

	$to = $settings['notificationEmail'] ?: accessibilityChecker_getAdminEmail();
	$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$subject = "Accessibility Checker Report: {$domain}";

	// Build email body
	$body = "Accessibility Checker Scan Results for {$domain}\n";
	$body .= "Scan Date: " . date('Y-m-d H:i:s') . "\n\n";
	$body .= "Summary:\n";
	$body .= "- Tables Scanned: {$results['tablesScanned']}\n";
	$body .= "- Records Scanned: {$results['recordsScanned']}\n";
	$body .= "- Errors Found: {$results['errors']}\n";
	$body .= "- Warnings Found: {$results['warnings']}\n";
	$body .= "- Duration: {$results['duration']} seconds\n\n";

	if ($hasErrors || $hasWarnings) {
		$body .= "Action Required: Log into the CMS to view and fix accessibility issues.\n";
		$adminUrl = \settings('adminUrl') ?? '/cmsb/';
		$body .= "Dashboard: https://{$domain}{$adminUrl}?_pluginAction=accessibilityChecker_adminDashboard\n";
	}

	mail($to, $subject, $body);
}

/**
 * Get admin email
 *
 * @return string Admin email address
 */
function accessibilityChecker_getAdminEmail(): string
{
	global $SETTINGS;
	return $SETTINGS['adminEmail'] ?? 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/**
 * Get edit link for record
 *
 * @param string $tableName Table name
 * @param int $recordNum Record number
 * @return string Edit URL
 */
function accessibilityChecker_getEditLinkForRecord(string $tableName, int $recordNum): string
{
	$adminUrl = \settings('adminUrl') ?? '/cmsb/';
	return "{$adminUrl}?menu={$tableName}&action=edit&num={$recordNum}";
}

// =============================================================================
// TEMPLATE FILE SCANNING
// =============================================================================

/**
 * Get the website root directory
 *
 * @return string Website root path
 */
function accessibilityChecker_getWebsiteRoot(): string
{
	// Go up from cmsb/plugins/accessibilityChecker to website root
	$cmsbDir = dirname(dirname(__DIR__));
	$websiteRoot = dirname($cmsbDir);
	return $websiteRoot;
}

/**
 * Get default template directories to scan
 *
 * @return array List of directories relative to website root
 */
function accessibilityChecker_getDefaultTemplateDirs(): array
{
	return [
		'includes',
		'templates',
		'views',
		'partials',
		'components',
	];
}

/**
 * Find all template files in the website
 *
 * @param array $directories Directories to scan (relative to website root)
 * @param array $extensions File extensions to include
 * @return array List of file paths
 */
function accessibilityChecker_findTemplateFiles(array $directories = [], array $extensions = ['php', 'html', 'htm']): array
{
	$websiteRoot = accessibilityChecker_getWebsiteRoot();
	$files = [];

	if (empty($directories)) {
		$directories = accessibilityChecker_getDefaultTemplateDirs();
	}

	foreach ($directories as $dir) {
		$fullPath = $websiteRoot . '/' . ltrim($dir, '/');

		if (!is_dir($fullPath)) {
			continue;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($iterator as $file) {
			$ext = strtolower($file->getExtension());
			if (in_array($ext, $extensions)) {
				$files[] = $file->getPathname();
			}
		}
	}

	// Also scan root-level PHP files (index.php, etc.)
	$rootFiles = glob($websiteRoot . '/*.php');
	foreach ($rootFiles as $file) {
		// Skip files that are likely not templates
		$basename = basename($file);
		if (!in_array($basename, ['robots.txt', 'sitemap.xml', '.htaccess'])) {
			$files[] = $file;
		}
	}

	sort($files);
	return $files;
}

/**
 * Extract HTML content from a PHP file
 * This extracts literal HTML and echo statements, ignoring PHP logic
 *
 * @param string $filePath Path to PHP file
 * @return string Extracted HTML content
 */
function accessibilityChecker_extractHtmlFromPhp(string $filePath): string
{
	if (!file_exists($filePath) || !is_readable($filePath)) {
		return '';
	}

	$content = file_get_contents($filePath);
	$html = '';

	// Remove PHP code blocks but keep HTML outside
	// This is a simplified extraction - gets HTML between ?> and <?php
	$parts = preg_split('/(<\?php|\?>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

	$inPhp = false;
	foreach ($parts as $part) {
		if (preg_match('/^<\?php$/i', $part)) {
			$inPhp = true;
		} elseif ($part === '?>') {
			$inPhp = false;
		} elseif (!$inPhp) {
			$html .= $part;
		} else {
			// Inside PHP - extract echo/print statements with HTML
			// Match echo "..." or echo '...' containing HTML tags
			preg_match_all('/(?:echo|print)\s+["\']([^"\']*<[^"\']*)["\'];/i', $part, $matches);
			if (!empty($matches[1])) {
				$html .= implode("\n", $matches[1]);
			}

			// Match heredoc/nowdoc with HTML
			preg_match_all('/<<<[\'"]?(\w+)[\'"]?\s*\n(.*?)\n\1;/s', $part, $heredocMatches);
			if (!empty($heredocMatches[2])) {
				foreach ($heredocMatches[2] as $heredocContent) {
					if (preg_match('/<[a-z]/i', $heredocContent)) {
						$html .= $heredocContent . "\n";
					}
				}
			}

			// Match echo with concatenation containing HTML elements
			preg_match_all('/echo\s+["\'](<[^;]+)["\'];/s', $part, $echoMatches);
			if (!empty($echoMatches[1])) {
				$html .= implode("\n", $echoMatches[1]);
			}
		}
	}

	return $html;
}

/**
 * Scan a template file for accessibility issues
 *
 * @param string $scanId Scan ID
 * @param string $filePath Path to template file
 * @param array $settings Plugin settings
 * @return array Count of errors and warnings found
 */
function accessibilityChecker_scanTemplateFile(string $scanId, string $filePath, array $settings): array
{
	$errors = 0;
	$warnings = 0;
	$checks = $settings['checks'];

	// Get content based on file type
	$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
	if ($ext === 'php') {
		$content = accessibilityChecker_extractHtmlFromPhp($filePath);
	} else {
		$content = file_get_contents($filePath);
	}

	if (empty($content)) {
		return ['errors' => 0, 'warnings' => 0];
	}

	$issues = [];

	// Run enabled checks
	if ($checks['missingAlt'] ?? true) {
		$issues = array_merge($issues, accessibilityChecker_checkMissingAlt($content));
	}

	if ($checks['poorAlt'] ?? true) {
		$issues = array_merge($issues, accessibilityChecker_checkPoorAlt($content, $settings));
	}

	if ($checks['emptyLinks'] ?? true) {
		$issues = array_merge($issues, accessibilityChecker_checkEmptyLinks($content));
	}

	if ($checks['genericLinkText'] ?? true) {
		$issues = array_merge($issues, accessibilityChecker_checkGenericLinkText($content, $settings));
	}

	if ($checks['skippedHeadings'] ?? true) {
		$issues = array_merge($issues, accessibilityChecker_checkSkippedHeadings($content));
	}

	if ($checks['tablesWithoutHeaders'] ?? true) {
		$issues = array_merge($issues, accessibilityChecker_checkTablesWithoutHeaders($content));
	}

	if ($checks['emptyButtons'] ?? true) {
		$issues = array_merge($issues, accessibilityChecker_checkEmptyButtons($content));
	}

	if ($checks['missingFormLabels'] ?? true) {
		$issues = array_merge($issues, accessibilityChecker_checkMissingFormLabels($content));
	}

	// Get relative path for display
	$websiteRoot = accessibilityChecker_getWebsiteRoot();
	$relativePath = str_replace($websiteRoot . '/', '', $filePath);

	// Log issues with file info instead of table/record
	foreach ($issues as $issue) {
		accessibilityChecker_logTemplateIssue(
			$scanId,
			$relativePath,
			$issue['type'],
			$issue['severity'],
			$issue['element'],
			$issue['context'],
			$issue['message']
		);

		if ($issue['severity'] === 'error') {
			$errors++;
		} else {
			$warnings++;
		}
	}

	return ['errors' => $errors, 'warnings' => $warnings];
}

/**
 * Log accessibility issue for a template file
 *
 * @param string $scanId Scan ID
 * @param string $filePath Relative file path
 * @param string $issueType Issue type
 * @param string $severity Severity (error or warning)
 * @param string $elementHtml The offending HTML element
 * @param string $context Surrounding context
 * @param string $message Human-readable message
 */
function accessibilityChecker_logTemplateIssue(
	string $scanId,
	string $filePath,
	string $issueType,
	string $severity,
	string $elementHtml,
	string $context,
	string $message
): void {
	// Use the standard logIssue function which handles duplicates
	// Template files use tableName='_template_file', recordNum=0, fieldName=filePath
	accessibilityChecker_logIssue(
		$scanId,
		'_template_file',
		0,
		$filePath,
		$issueType,
		$severity,
		$elementHtml,
		$context,
		$message
	);
}

/**
 * Perform template file scan
 *
 * @param string $scanId Scan ID
 * @param array $directories Directories to scan (empty = defaults)
 * @return array Scan results
 */
function accessibilityChecker_performTemplateScan(string $scanId, array $directories = []): array
{
	// Buffer any stray output to prevent it from appearing on the page
	ob_start();

	$startTime = microtime(true);
	$settings = accessibilityChecker_loadPluginSettings();

	$results = [
		'filesScanned' => 0,
		'errors' => 0,
		'warnings' => 0,
		'duration' => 0,
	];

	$files = accessibilityChecker_findTemplateFiles($directories);

	foreach ($files as $filePath) {
		$results['filesScanned']++;

		$fileResults = accessibilityChecker_scanTemplateFile($scanId, $filePath, $settings);

		$results['errors'] += $fileResults['errors'];
		$results['warnings'] += $fileResults['warnings'];
	}

	$results['duration'] = round(microtime(true) - $startTime, 2);

	// Discard any stray output
	ob_end_clean();

	return $results;
}

/**
 * Run manual template scan
 *
 * @param array $directories Directories to scan
 * @return array Scan results
 */
function accessibilityChecker_runTemplateScan(array $directories = []): array
{
	$scanInfo = accessibilityChecker_createScanRecord('manual');

	try {
		$results = accessibilityChecker_performTemplateScan($scanInfo['scanId'], $directories);

		// Update scan record with template-specific info
		$data = [
			'endTime=' => 'NOW()',
			'tablesScanned' => $results['filesScanned'],
			'recordsScanned' => $results['filesScanned'],
			'errorsFound' => $results['errors'],
			'warningsFound' => $results['warnings'],
			'duration' => $results['duration'],
			'status' => 'completed',
		];

		\mysql_update('_accessibilitychecker_scans', $scanInfo['num'], null, $data);

		// Update settings with last scan info
		$settings = accessibilityChecker_loadPluginSettings();
		$settings['lastScanDate'] = date('Y-m-d H:i:s');
		$settings['lastScanResults'] = $results;
		accessibilityChecker_savePluginSettings($settings);

		return $results;
	} catch (\Exception $e) {
		accessibilityChecker_failScanRecord($scanInfo['num'], $e->getMessage());
		throw $e;
	}
}

/**
 * Run combined scan (database content + template files)
 *
 * @param array $tables Tables to scan (empty = all)
 * @param array $directories Template directories to scan (empty = defaults)
 * @return array Combined scan results
 */
function accessibilityChecker_runCombinedScan(array $tables = [], array $directories = []): array
{
	$scanInfo = accessibilityChecker_createScanRecord('manual');

	try {
		// Scan database content
		$dbResults = accessibilityChecker_performScan($tables, $scanInfo['scanId']);

		// Scan template files
		$templateResults = accessibilityChecker_performTemplateScan($scanInfo['scanId'], $directories);

		// Combine results
		$results = [
			'tablesScanned' => $dbResults['tablesScanned'],
			'recordsScanned' => $dbResults['recordsScanned'],
			'filesScanned' => $templateResults['filesScanned'],
			'errors' => $dbResults['errors'] + $templateResults['errors'],
			'warnings' => $dbResults['warnings'] + $templateResults['warnings'],
			'duration' => $dbResults['duration'] + $templateResults['duration'],
		];

		// Update scan record
		$data = [
			'endTime=' => 'NOW()',
			'tablesScanned' => $results['tablesScanned'] + $results['filesScanned'],
			'recordsScanned' => $results['recordsScanned'] + $results['filesScanned'],
			'errorsFound' => $results['errors'],
			'warningsFound' => $results['warnings'],
			'duration' => $results['duration'],
			'status' => 'completed',
		];

		\mysql_update('_accessibilitychecker_scans', $scanInfo['num'], null, $data);

		// Update settings with last scan info
		$settings = accessibilityChecker_loadPluginSettings();
		$settings['lastScanDate'] = date('Y-m-d H:i:s');
		$settings['lastScanResults'] = $results;
		accessibilityChecker_savePluginSettings($settings);

		return $results;
	} catch (\Exception $e) {
		accessibilityChecker_failScanRecord($scanInfo['num'], $e->getMessage());
		throw $e;
	}
}

/**
 * Check if an issue is from a template file
 *
 * @param array $issue Issue record
 * @return bool True if from template file
 */
function accessibilityChecker_isTemplateIssue(array $issue): bool
{
	return ($issue['tableName'] ?? '') === '_template_file';
}

/**
 * Get file path for a template issue
 *
 * @param array $issue Issue record
 * @return string File path or empty string
 */
function accessibilityChecker_getTemplateFilePath(array $issue): string
{
	if (accessibilityChecker_isTemplateIssue($issue)) {
		return $issue['fieldName'] ?? '';
	}
	return '';
}
