<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$path = __DIR__ . '/../../gexport.php';
$contents = file_get_contents($path);

if ($contents === false) {
	fwrite(STDERR, "Unable to read gexport.php\n");
	exit(1);
}

$checks = array(
	"include_once('./plugins/gexport/gexport_security.php');",
	'$bulk_action = gexport_normalize_bulk_action(get_nfilter_request_var(\'drp_action\'));',
	'html_start_box(isset($export_actions[$bulk_action]) ? $export_actions[$bulk_action] : \'\',',
	'<input type=\'hidden\' name=\'drp_action\' value=\'" . html_escape($bulk_action) . "\'>',
);

foreach ($checks as $check) {
	if (strpos($contents, $check) === false) {
		fwrite(STDERR, "Missing expected security wiring: {$check}\n");
		exit(1);
	}
}

print "OK\n";
