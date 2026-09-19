<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the rsync/scp exit-code-to-message translators in
 * functions.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../functions.php';
});

it('maps rsync success', function () {
	expect(export_rsync_get_message(0))->toBe('Success');
});

it('maps known rsync error codes', function () {
	expect(export_rsync_get_message(23))->toBe('Partial transfer due to error');
	expect(export_rsync_get_message(35))->toBe('Timeout waiting for daemon connection');
});

it('maps unknown rsync error codes with the code appended', function () {
	expect(export_rsync_get_message(999))->toBe('Unknown error 999');
});

it('maps scp success', function () {
	expect(export_scp_get_message(0))->toBe('Operation was successful');
});

it('maps known scp error codes', function () {
	expect(export_scp_get_message(6))->toBe('File does not exist');
	expect(export_scp_get_message(74))->toBe('Connection failed');
});
