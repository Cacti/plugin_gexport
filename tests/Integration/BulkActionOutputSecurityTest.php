<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the former ad hoc tests/Integration/test_bulk_action_output_security.php
 * script into Pest-style assertions. Verifies gexport.php still wires the
 * bulk-action helpers into its output rather than reusing raw request data.
 */

$path = __DIR__ . '/../../gexport.php';
$contents = file_get_contents($path);

if ($contents === false) {
	throw new RuntimeException('Unable to read gexport.php');
}

it('includes the gexport_security helper file', function () use ($contents) {
	expect($contents)->toContain("include_once('./plugins/gexport/gexport_security.php');");
});

it('normalizes the bulk action from the request before use', function () use ($contents) {
	expect($contents)->toContain(
		'$bulk_action = gexport_normalize_bulk_action(get_nfilter_request_var(\'drp_action\'));'
	);
});

it('uses the normalized bulk action to select the box title', function () use ($contents) {
	expect($contents)->toContain(
		'html_start_box(isset($export_actions[$bulk_action]) ? $export_actions[$bulk_action] : \'\','
	);
});

it('escapes the bulk action before writing it back into a hidden field', function () use ($contents) {
	expect($contents)->toContain(
		'<input type=\'hidden\' name=\'drp_action\' value=\'" . html_escape($bulk_action) . "\'>'
	);
});
