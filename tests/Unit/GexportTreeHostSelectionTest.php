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

	gexport_test_mock_db('db_fetch_cell_prepared', function ($sql, $bound) use (&$query, &$params) {
		$query  = $sql;
		$params = $bound;

		return '10,20';
	}, '10,20');

	expect(gexport_get_tree_hosts([3, 8]))->toBe('10,20')
		->and(preg_replace('/\s+/', ' ', trim($query)))
		->toBe(
			'SELECT GROUP_CONCAT(DISTINCT host_id) ' .
			'FROM graph_tree_items WHERE graph_tree_id IN(?, ?)'
		)
		->and($params)->toBe([3, 8]);
});

it('binds one tree id with a single placeholder', function () {
	$query  = null;
	$params = null;

	gexport_test_mock_db('db_fetch_cell_prepared', function ($sql, $bound) use (&$query, &$params) {
		$query  = $sql;
		$params = $bound;

		return '10';
	}, '10');

	expect(gexport_get_tree_hosts([3]))->toBe('10')
		->and(preg_replace('/\s+/', ' ', trim($query)))
		->toBe(
			'SELECT GROUP_CONCAT(DISTINCT host_id) ' .
			'FROM graph_tree_items WHERE graph_tree_id IN(?)'
		)
		->and($params)->toBe([3]);
});

it('uses hosts from all selected trees when finding exportable graphs', function () {
	$GLOBALS['__test_allowed_trees'] = [
		['id' => 3, 'name' => 'Tree 3'],
		['id' => 8, 'name' => 'Tree 8'],
	];

	gexport_test_mock_db('db_fetch_cell_prepared', function ($sql, $bound) {
		return count($bound) === 2 && $bound === [3, 8] ? '10,20' : false;
	}, '10,20');

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
