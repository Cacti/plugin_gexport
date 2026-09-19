<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for gexport_calc_next_start() in functions.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../functions.php';
});

beforeEach(function () {
	$GLOBALS['__test_config_options'] = array();
});

it('schedules the next hourly run later in the same hour', function () {
	$start = strtotime('2024-01-15 10:15:00');

	$export = array(
		'export_timing' => 'hourly',
		'export_hourly' => '30',
	);

	expect(gexport_calc_next_start($export, $start))->toBe('2024-01-15 10:30:00');
});

it('rolls the hourly run over to the next hour once the target minute has passed', function () {
	$start = strtotime('2024-01-15 10:45:00');

	$export = array(
		'export_timing' => 'hourly',
		'export_hourly' => '30',
	);

	expect(gexport_calc_next_start($export, $start))->toBe('2024-01-15 11:30:00');
});

it('schedules the next daily run later in the same day', function () {
	$start = strtotime('2024-01-15 08:00:00');

	$export = array(
		'export_timing' => 'daily',
		'export_daily'  => '20:00',
	);

	expect(gexport_calc_next_start($export, $start))->toBe('2024-01-15 20:00:00');
});

it('rolls the daily run over to the next day once the target time has passed', function () {
	$start = strtotime('2024-01-15 21:00:00');

	$export = array(
		'export_timing' => 'daily',
		'export_daily'  => '20:00',
	);

	expect(gexport_calc_next_start($export, $start))->toBe('2024-01-16 20:00:00');
});

it('schedules a periodic run using the poller interval and export skip count', function () {
	test_set_config_option('poller_interval', 300);

	$export = array(
		'export_timing' => 'periodic',
		'export_skip'   => 2,
	);

	$before = strtotime(date('Y-m-d H:i:00'));
	$result = gexport_calc_next_start($export, $before);
	$after  = strtotime(date('Y-m-d H:i:00'));

	$resultTime = strtotime($result);

	expect($resultTime)->toBeGreaterThanOrEqual($before + 2 * 300);
	expect($resultTime)->toBeLessThanOrEqual($after + 2 * 300);
});
