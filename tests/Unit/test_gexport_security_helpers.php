<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../gexport_security.php';

if (gexport_normalize_bulk_action('1') !== '1') {
	fwrite(STDERR, "Expected action 1 to normalize\n");
	exit(1);
}

if (gexport_normalize_bulk_action('4') !== '4') {
	fwrite(STDERR, "Expected action 4 to normalize\n");
	exit(1);
}

if (gexport_normalize_bulk_action('4\" autofocus onfocus=\"alert(1)') !== '') {
	fwrite(STDERR, "Expected mixed action payload to be rejected\n");
	exit(1);
}

if (gexport_normalize_bulk_action('99') !== '') {
	fwrite(STDERR, "Expected out of range action to be rejected\n");
	exit(1);
}

if (gexport_build_nav_filter_url('name=test&refresh=1') !== 'gexport.php?filter=name%3Dtest%26refresh%3D1') {
	fwrite(STDERR, "Expected filter to be URL encoded\n");
	exit(1);
}

print "OK\n";
