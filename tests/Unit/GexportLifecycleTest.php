<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the plugin lifecycle contract functions in setup.php:
 * plugin_gexport_check_config(), plugin_gexport_uninstall(),
 * gexport_check_dependencies(), and gexport_check_upgrade()'s page-guard,
 * already-current, and version-drift branches.
 *
 * gexport_check_upgrade() include_once()s Cacti core's database.php/
 * functions.php via $config['library_path'], so that is pointed at
 * throwaway empty stub files for the duration of these tests.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gexport-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");
	file_put_contents($stubLibraryPath . '/functions.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	gexport_test_reset_db_mocks();
	$GLOBALS['__test_db_calls']            = array();
	$GLOBALS['__test_enabled_hooks_calls'] = array();
	test_set_current_page('gexport.php');
});

it('reports the config as always valid', function () {
	expect(plugin_gexport_check_config())->toBeTrue();
});

it('reports that its dependencies are always satisfied', function () {
	expect(gexport_check_dependencies())->toBeTrue();
});

it('drops both plugin tables on uninstall', function () {
	plugin_gexport_uninstall();

	$drops = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'DROP TABLE') !== false;
	});

	expect($drops)->toHaveCount(2);
});

it('skips the version check on pages that do not need it', function () {
	test_set_current_page('graphs.php');

	gexport_check_upgrade();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});

it('does nothing when the stored version already matches the plugin version', function () {
	$info = plugin_gexport_version();

	gexport_test_mock_db('db_fetch_cell', 'plugin_config', $info['version']);

	gexport_check_upgrade();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
	expect($GLOBALS['__test_enabled_hooks_calls'])->toBeEmpty();
});

it('re-enables hooks and updates plugin_config when an old version upgrades', function () {
	gexport_test_mock_db('db_fetch_cell', 'plugin_config', '1.0');

	gexport_check_upgrade();

	expect($GLOBALS['__test_enabled_hooks_calls'])->toBe(array('gexport'));

	$updates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], "UPDATE plugin_config") !== false;
	}));

	expect($updates)->not->toBeEmpty();
});
