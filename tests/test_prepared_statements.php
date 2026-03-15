<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Regression checks for prepared DB helper migration in gexport plugin    |
 |                                                                         |
 | Run: php tests/test_prepared_statements.php                             |
 +-------------------------------------------------------------------------+
 */

$pass = 0;
$fail = 0;

function assert_true($label, $value) {
	global $pass, $fail;

	if ($value) {
		echo "PASS  $label\n";
		$pass++;
	} else {
		echo "FAIL  $label\n";
		$fail++;
	}
}

$setup_contents = file_get_contents(__DIR__ . '/../setup.php');
$gexport_contents = file_get_contents(__DIR__ . '/../gexport.php');

assert_true(
	'setup.php uses prepared enabled exports query',
	preg_match('/db_fetch_assoc_prepared\s*\(\s*\'SELECT \*\s+FROM graph_exports\s+WHERE enabled = \?/s', $setup_contents) === 1
);
assert_true(
	'setup.php uses prepared plugin version lookup',
	preg_match('/db_fetch_cell_prepared\s*\(\s*\'SELECT version\s+FROM plugin_config\s+WHERE directory = \?/s', $setup_contents) === 1
);
assert_true(
	'gexport.php uses prepared bulk delete',
	preg_match('/db_execute_prepared\s*\(\s*"DELETE FROM graph_exports\s+WHERE id IN \(/s', $gexport_contents) === 1
);
assert_true(
	'gexport.php no longer uses array_to_sql_or for export delete',
	strpos($gexport_contents, "array_to_sql_or(\$export_ids, 'id')") === false
);
assert_true(
	'gexport.php uses prepared count and record fetch for list query',
	preg_match_all('/db_fetch_(?:cell|assoc)_prepared\s*\(/', $gexport_contents) >= 4
);
assert_true(
	'gexport.php parameterizes site/tree group concat lookups',
	preg_match('/GROUP_CONCAT\(name ORDER BY name SEPARATOR \', \'\)\s+FROM sites\s+WHERE id IN \(\$placeholders\)/s', $gexport_contents) === 1 &&
	preg_match('/GROUP_CONCAT\(name ORDER BY name SEPARATOR \', \'\)\s+FROM graph_tree\s+WHERE id IN \(\$placeholders\)/s', $gexport_contents) === 1
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
