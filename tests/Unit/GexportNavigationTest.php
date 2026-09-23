<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for gexport_draw_navigation_text() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

it('adds the gexport breadcrumb entries without disturbing existing ones', function () {
	$nav = gexport_draw_navigation_text(array('other.php:' => array('title' => 'Other')));

	expect($nav)->toHaveKey('other.php:');
	expect($nav)->toHaveKey('gexport.php:');
	expect($nav)->toHaveKey('gexport.php:edit');
	expect($nav)->toHaveKey('gexport.php:actions');
	expect($nav['gexport.php:edit']['mapping'])->toBe('index.php:,gexport.php:');
});
