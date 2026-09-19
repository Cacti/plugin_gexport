<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the former ad hoc tests/Unit/test_gexport_security_helpers.php
 * script into Pest-style assertions.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../gexport_security.php';
});

it('normalizes a valid low bound bulk action', function () {
	expect(gexport_normalize_bulk_action('1'))->toBe('1');
});

it('normalizes a valid high bound bulk action', function () {
	expect(gexport_normalize_bulk_action('4'))->toBe('4');
});

it('normalizes an integer bulk action', function () {
	expect(gexport_normalize_bulk_action(2))->toBe('2');
});

it('rejects a bulk action payload mixed with an XSS attempt', function () {
	expect(gexport_normalize_bulk_action('4" autofocus onfocus="alert(1)'))->toBe('');
});

it('rejects an out of range bulk action', function () {
	expect(gexport_normalize_bulk_action('99'))->toBe('');
});

it('rejects a non-numeric bulk action', function () {
	expect(gexport_normalize_bulk_action('abc'))->toBe('');
});

it('rejects an empty bulk action', function () {
	expect(gexport_normalize_bulk_action(''))->toBe('');
});

it('URL encodes the nav filter value', function () {
	expect(gexport_build_nav_filter_url('name=test&refresh=1'))
		->toBe('gexport.php?filter=name%3Dtest%26refresh%3D1');
});

it('URL encodes a filter value containing quotes and script tags', function () {
	expect(gexport_build_nav_filter_url('"><script>alert(1)</script>'))
		->toBe('gexport.php?filter=%22%3E%3Cscript%3Ealert%281%29%3C%2Fscript%3E');
});

it('builds a nav filter URL for an empty filter', function () {
	expect(gexport_build_nav_filter_url(''))->toBe('gexport.php?filter=');
});
