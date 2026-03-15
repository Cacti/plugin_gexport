<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Regression checks for shared layout wrapper routing in gexport.php      |
 |                                                                         |
 | Run: php tests/test_layout_wrapper.php                                  |
 +-------------------------------------------------------------------------+
 */

$pass = 0;
$fail = 0;
$events = array();

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

function top_header() {
	global $events;
	$events[] = 'top_header';
}

function bottom_footer() {
	global $events;
	$events[] = 'bottom_footer';
}

require_once __DIR__ . '/../ui_helpers.php';

$events = array();
$runs = 0;

gexport_render_with_layout(function () use (&$events, &$runs) {
	$runs++;
	$events[] = 'content';
});

assert_true('layout callback executes once', $runs === 1);
assert_true('layout call order is top->content->bottom', $events === array('top_header', 'content', 'bottom_footer'));

$source = file_get_contents(__DIR__ . '/../gexport.php');

assert_true(
	'edit action uses shared layout helper',
	strpos($source, "gexport_render_with_layout('export_edit');") !== false
);
assert_true(
	'default action uses shared layout helper',
	strpos($source, "gexport_render_with_layout('gexport');") !== false
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
