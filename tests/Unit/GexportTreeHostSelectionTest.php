<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	gexport_test_reset_db_mocks();
	$GLOBALS['__test_allowed_trees']       = [];
	$GLOBALS['__test_allowed_graphs']      = [];
	$GLOBALS['__test_allowed_graph_calls'] = [];
});

it('binds every selected tree id when looking up their hosts', function () {
	$query  = null;
	$params = null;

	gexport_test_mock_db('db_fetch_assoc_prepared', function ($sql, $bound) use (&$query, &$params) {
		$query  = $sql;
		$params = $bound;

		return true;
	}, [['host_id' => '10'], ['host_id' => '20']]);

	expect(gexport_get_tree_hosts([3, 8]))->toBe('10,20')
		->and(preg_replace('/\s+/', ' ', trim($query)))
		->toBe(
			'SELECT DISTINCT host_id ' .
			'FROM graph_tree_items WHERE graph_tree_id IN(?, ?) AND host_id > 0'
		)
		->and($params)->toBe([3, 8]);
});

it('binds one tree id with a single placeholder', function () {
	$query  = null;
	$params = null;

	gexport_test_mock_db('db_fetch_assoc_prepared', function ($sql, $bound) use (&$query, &$params) {
		$query  = $sql;
		$params = $bound;

		return true;
	}, [['host_id' => '10']]);

	expect(gexport_get_tree_hosts([3]))->toBe('10')
		->and(preg_replace('/\s+/', ' ', trim($query)))
		->toBe(
			'SELECT DISTINCT host_id ' .
			'FROM graph_tree_items WHERE graph_tree_id IN(?) AND host_id > 0'
		)
		->and($params)->toBe([3]);
});

it('returns an empty host list when the selected trees hold no devices', function () {
	gexport_test_mock_db('db_fetch_assoc_prepared', 'FROM graph_tree_items', []);

	expect(gexport_get_tree_hosts([3]))->toBe('');
});

it('keeps every host id intact when the list is long', function () {
	// GROUP_CONCAT() stops at group_concat_max_len (1024 bytes by default).
	$rows = [];

	for ($id = 100000; $id < 100400; $id++) {
		$rows[] = ['host_id' => (string) $id];
	}

	gexport_test_mock_db('db_fetch_assoc_prepared', 'FROM graph_tree_items', $rows);

	$hosts = explode(',', gexport_get_tree_hosts([3]));

	expect($hosts)->toHaveCount(400)
		->and(end($hosts))->toBe('100399');
});

it('uses hosts from all selected trees when finding exportable graphs', function () {
	$GLOBALS['__test_allowed_trees'] = [
		['id' => 3, 'name' => 'Tree 3'],
		['id' => 8, 'name' => 'Tree 8'],
	];

	gexport_test_mock_db('db_fetch_assoc_prepared', function ($sql, $bound) {
		return strpos($sql, 'FROM graph_tree_items') !== false && $bound === [3, 8];
	}, [['host_id' => '10'], ['host_id' => '20']]);

	$export = [
		'export_clear'          => 'off',
		'export_effective_user' => 5,
		'graph_tree'            => '3,8',
		'graph_site'            => '0',
		'id'                    => 1,
		'export_presentation'   => 'tree',
		'export_threads'        => 0,
		'graph_max'             => 0,
	];
	$export_path = sys_get_temp_dir() . '/gexport-tree-hosts-' . uniqid();

	try {
		expect(export_graphs($export, $export_path))->toBe(0)
			->and($GLOBALS['__test_allowed_graph_calls'][0]['sql_where'])
			->toBe('gl.host_id IN(10,20)');
	} finally {
		delTree($export_path);
	}
});
