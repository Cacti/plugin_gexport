<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for gexport_poller_bottom() in setup.php - launches the
 * graph-export poller script in the background when exports are enabled
 * and this is the primary poller.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	gexport_test_reset_db_mocks();
	$GLOBALS['__test_exec_calls'] = array();
	$GLOBALS['config']['poller_id'] = 1;
	test_set_config_option('path_php_binary', '/usr/bin/php');
});

it('does nothing when there are no enabled exports', function () {
	gexport_test_mock_db('db_fetch_assoc', 'graph_exports', array());

	gexport_poller_bottom();

	expect($GLOBALS['__test_exec_calls'])->toBeEmpty();
});

it('launches poller_export.php in the background when an export is enabled', function () {
	gexport_test_mock_db('db_fetch_assoc', 'graph_exports', array(array('id' => 1)));

	gexport_poller_bottom();

	expect($GLOBALS['__test_exec_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_exec_calls'][0]['command'])->toBe('/usr/bin/php');
	expect($GLOBALS['__test_exec_calls'][0]['args'])->toContain('poller_export.php');
});

it('does nothing on a non-primary poller', function () {
	$GLOBALS['config']['poller_id'] = 2;

	gexport_test_mock_db('db_fetch_assoc', 'graph_exports', array(array('id' => 1)));

	gexport_poller_bottom();

	expect($GLOBALS['__test_exec_calls'])->toBeEmpty();
});
