<?php return [
	'menuName'            => 'Accessibility Results',
	'_tableName'          => '_accessibilitychecker_results',
	'_primaryKey'         => 'num',
	'menuType'            => 'multi',
	'listPageFields'      => 'issueType, severity, tableName, status, scanDate',
	'listPageOrder'       => 'scanDate DESC',
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

	'scanDate' => [
		'label'            => 'Scan Date',
		'type'             => 'none',
		'customColumnType' => 'DATETIME',
		'indexed'          => 1,
	],

	'tableName' => [
		'label'            => 'Table Name',
		'type'             => 'textfield',
		'customColumnType' => 'VARCHAR(255)',
		'indexed'          => 1,
	],

	'recordNum' => [
		'label'            => 'Record Number',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) UNSIGNED',
		'indexed'          => 1,
	],

	'fieldName' => [
		'label'            => 'Field Name',
		'type'             => 'textfield',
		'customColumnType' => 'VARCHAR(255)',
	],

	'issueType' => [
		'label'            => 'Issue Type',
		'type'             => 'list',
		'listType'         => 'pulldown',
		'optionsType'      => 'text',
		'optionsText'      => "missing_alt|Missing Alt Text\npoor_alt|Poor Alt Text\nempty_link|Empty Link\ngeneric_link|Generic Link Text\nskipped_heading|Skipped Heading\ntable_no_headers|Table Without Headers\nempty_button|Empty Button\nmissing_label|Missing Form Label",
		'customColumnType' => "ENUM('missing_alt','poor_alt','empty_link','generic_link','skipped_heading','table_no_headers','empty_button','missing_label')",
		'indexed'          => 1,
	],

	'severity' => [
		'label'            => 'Severity',
		'type'             => 'list',
		'listType'         => 'pulldown',
		'optionsType'      => 'text',
		'optionsText'      => "error|Error\nwarning|Warning",
		'customColumnType' => "ENUM('error','warning')",
		'indexed'          => 1,
	],

	'elementHtml' => [
		'label'            => 'Element HTML',
		'type'             => 'textbox',
		'customColumnType' => 'TEXT',
	],

	'context' => [
		'label'            => 'Context',
		'type'             => 'textbox',
		'customColumnType' => 'TEXT',
	],

	'message' => [
		'label'            => 'Message',
		'type'             => 'textfield',
		'customColumnType' => 'VARCHAR(500)',
	],

	'status' => [
		'label'            => 'Status',
		'type'             => 'list',
		'listType'         => 'pulldown',
		'optionsType'      => 'text',
		'optionsText'      => "open|Open\nfixed|Fixed\nignored|Ignored",
		'customColumnType' => "ENUM('open','fixed','ignored') DEFAULT 'open'",
		'indexed'          => 1,
	],

	'ignoreReason' => [
		'label'            => 'Ignore Reason',
		'type'             => 'textfield',
		'customColumnType' => 'VARCHAR(255)',
	],

	'fixedDate' => [
		'label'            => 'Fixed Date',
		'type'             => 'none',
		'customColumnType' => 'DATETIME',
	],
];
