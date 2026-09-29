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
 * gexport_setup_table() require_once()s Cacti core's database.php via
 * $config['library_path'], so that is pointed at a throwaway empty stub
 * file for the duration of these tests.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/database.php';

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

it('creates both tables via the tracked plugin table API', function () {
	expect(gexport_setup_table())->toBeTrue();

	$tables = array_column(
		array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] === 'api_plugin_db_table_create';
		}),
		'table'
	);

	expect($tables)->toContain('graph_exports')
		->and($tables)->toContain('graph_exports_tasks');
});

it('never emits a raw CREATE TABLE statement', function () {
	gexport_create_table();
	gexport_create_table_tasks();

	$raw = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'CREATE TABLE') !== false;
	});

	expect($raw)->toBeEmpty();
});
