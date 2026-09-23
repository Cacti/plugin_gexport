<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for gexport_setup_table(), gexport_create_table(), and
 * gexport_create_table_tasks() in setup.php.
 *
 * gexport_setup_table() include_once()s Cacti core's database.php via
 * $config['library_path'], so that is pointed at a throwaway empty stub
 * file for the duration of these tests.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gexport-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	gexport_test_reset_db_mocks();
	$GLOBALS['__test_db_calls'] = array();
});

it('creates both tables when neither exists', function () {
	gexport_test_mock_db('db_table_exists', 'graph_exports_tasks', false);
	gexport_test_mock_db('db_table_exists', 'graph_exports', false);

	expect(gexport_setup_table())->toBeTrue();

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	expect($sql)->toContain('CREATE TABLE `graph_exports`');
	expect($sql)->toContain('CREATE TABLE `graph_exports_tasks`');
});

it('does not recreate a table that already exists', function () {
	gexport_test_mock_db('db_table_exists', 'graph_exports_tasks', true);
	gexport_test_mock_db('db_table_exists', 'graph_exports', true);

	gexport_create_table();
	gexport_create_table_tasks();

	$creates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'CREATE TABLE') !== false;
	});

	expect($creates)->toBeEmpty();
});
