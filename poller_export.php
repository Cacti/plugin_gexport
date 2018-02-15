#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

/* we are not talking to the browser */
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* Start Initialization Section */
$dir = dirname(__FILE__);
chdir($dir);

if (substr_count(strtolower($dir), 'gexport')) {
    chdir('../../');
}

include('./include/global.php');
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

$debug = FALSE;
$force = FALSE;
$id    = 0;

if (sizeof($parms)) {
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
			$debug = TRUE;
			break;
		case '-f':
		case '--force':
			$force = TRUE;
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
			echo 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
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
	echo "Cacti Graph Export Poller, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	echo "\nusage: poller_export.php [--id=N] [--force] [--debug]\n\n";
	echo "Cacti's Graph Export poller.  This poller will export parts of the Cacti\n";
	echo "website into a static representation.\n\n";
	echo "Optional:\n";
	echo "    --force     - Force export to run now running now\n";
	echo "    --debug     - Display verbose output during execution\n\n";
}
