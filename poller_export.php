#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* Start Initialization Section */
$dir = dirname(__FILE__);
chdir($dir);

if (substr_count(strtolower($dir), 'gexport')) {
    chdir('../../');
}

include('./include/cli_check.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/plugins/gexport/functions.php');
include_once($config['base_path'] . '/lib/rrd.php');

/* Let PHP Run Just as Long as It Has To */
ini_set('max_execution_time', '0');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug = false;
$force = false;
$id    = 0;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
		case '--id':
			$id = $value;
			break;
		case '--thread':
			$thread = $value;
			break;
		case '-d':
		case '--debug':
			$debug = true;
			break;
		case '-f':
		case '--force':
			$force = true;
			break;
		case '--version':
		case '-V':
		case '-v':
			display_version();
			exit;
		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit;
		default:
			print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
			display_help();
			exit;
		}
	}
}

/* graph export */
if (isset($thread)) {
	export_graph_start_task($thread);
} else {
	graph_export($id, $force);
}

/*  display_version - displays version information */
function display_version() {
	global $config;

	if (!function_exists('plugin_gexport_version')) {
		include_once($config['base_path'] . '/plugins/gexport/setup.php');
	}

    $info = plugin_gexport_version();
	print "Cacti Graph Export Poller, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: poller_export.php [--id=N] [--force] [--debug]\n\n";
	print "Cacti's Graph Export poller.  This poller will export parts of the Cacti\n";
	print "website into a static representation.\n\n";
	print "Optional:\n";
	print "    --force     - Force export to run now running now\n";
	print "    --debug     - Display verbose output during execution\n\n";
}
