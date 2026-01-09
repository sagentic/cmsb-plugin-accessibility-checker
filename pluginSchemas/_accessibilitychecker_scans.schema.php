<?php return [
	'menuName'            => 'Accessibility Scans',
	'_tableName'          => '_accessibilitychecker_scans',
	'_primaryKey'         => 'num',
	'menuType'            => 'multi',
	'listPageFields'      => 'scanType, status, tablesScanned, recordsScanned, errorsFound, warningsFound, createdDate',
	'listPageOrder'       => 'createdDate DESC',
	'listPageSearchFields' => '_all_',
	'_filenameFields'     => 'num',
	'_disableView'        => 1,
	'_disableAdd'         => 1,
	'_disableModify'      => 1,
	'menuHidden'          => 1,
	'menuOrder'           => 9999999999,

	'num' => [
		'type'          => 'none',
		'label'         => 'Record Number',
		'isSystemField' => 1,
	],

	'createdDate' => [
		'type'          => 'none',
		'label'         => 'Created Date',
		'isSystemField' => 1,
	],

	'scanId' => [
		'label'            => 'Scan ID',
		'type'             => 'textfield',
		'customColumnType' => 'VARCHAR(50)',
		'indexed'          => 1,
	],

	'scanType' => [
		'label'            => 'Scan Type',
		'type'             => 'list',
		'listType'         => 'pulldown',
		'optionsType'      => 'text',
		'optionsText'      => "manual|Manual\nscheduled|Scheduled",
		'customColumnType' => "ENUM('manual','scheduled')",
	],

	'startTime' => [
		'label'            => 'Start Time',
		'type'             => 'none',
		'customColumnType' => 'DATETIME',
	],

	'endTime' => [
		'label'            => 'End Time',
		'type'             => 'none',
		'customColumnType' => 'DATETIME',
	],

	'tablesScanned' => [
		'label'            => 'Tables Scanned',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'recordsScanned' => [
		'label'            => 'Records Scanned',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'errorsFound' => [
		'label'            => 'Errors Found',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'warningsFound' => [
		'label'            => 'Warnings Found',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'duration' => [
		'label'            => 'Duration (seconds)',
		'type'             => 'textfield',
		'customColumnType' => 'DECIMAL(10,2) DEFAULT 0',
	],

	'status' => [
		'label'            => 'Status',
		'type'             => 'list',
		'listType'         => 'pulldown',
		'optionsType'      => 'text',
		'optionsText'      => "running|Running\ncompleted|Completed\nfailed|Failed",
		'customColumnType' => "ENUM('running','completed','failed') DEFAULT 'running'",
		'indexed'          => 1,
	],

	'errorMessage' => [
		'label'            => 'Error Message',
		'type'             => 'textbox',
		'customColumnType' => 'TEXT',
	],
];
