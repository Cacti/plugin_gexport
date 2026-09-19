<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the former ad hoc tests/e2e/test_filter_nav_encoding.php
 * script into Pest-style assertions. Verifies gexport.php builds its nav
 * bar filter URL through the encoding helper rather than raw concatenation.
 */

$path = __DIR__ . '/../../gexport.php';
$contents = file_get_contents($path);

if ($contents === false) {
	throw new RuntimeException('Unable to read gexport.php');
}

it('builds the nav bar filter URL through the encoding helper', function () use ($contents) {
	expect($contents)->toContain(
		"html_nav_bar(gexport_build_nav_filter_url(get_request_var('filter'))"
	);
});

it('does not reuse the raw, unencoded filter value in the nav bar URL', function () use ($contents) {
	expect($contents)->not->toContain(
		"html_nav_bar('gexport.php?filter=' . get_request_var('filter')"
	);
});
