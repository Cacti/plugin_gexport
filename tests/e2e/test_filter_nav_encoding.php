<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$path = __DIR__ . '/../../gexport.php';
$contents = file_get_contents($path);

if ($contents === false) {
	fwrite(STDERR, "Unable to read gexport.php\n");
	exit(1);
}

if (strpos($contents, "html_nav_bar(gexport_build_nav_filter_url(get_request_var('filter'))") === false) {
	fwrite(STDERR, "Expected nav filter URL builder to be used\n");
	exit(1);
}

if (strpos($contents, "html_nav_bar('gexport.php?filter=' . get_request_var('filter')") !== false) {
	fwrite(STDERR, "Raw filter reuse still present in nav URL\n");
	exit(1);
}

print "OK\n";
