<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

/* graph_export - a function that determines, for each export definition
   if it's time to run or not.  this function is currently single threaded
   and some thought should be given to making multi-threaded.
   @arg $id    - the id of the export to check, '0' for all export definitions.
   @arg $force - force the export to run no regardless of it's timing settings. */
function graph_export($id = 0, $force = false) {
	global $debug, $start;

	/* take time to log performance data */
	$start           = microtime(true);
	$start_time      = time();
	$poller_interval = read_config_option('poller_interval');
	$runnow          = false;
	$sql_where       = '';

	if ($debug) {
		export_debug('This is a forced run');
	}

	/* force run */
	if ($id > 0) {
		$sql_where = ' AND id=' . $id;
	}

	$exports = db_fetch_assoc('SELECT * FROM graph_exports WHERE enabled="on"' . $sql_where);

	if (sizeof($exports)) {
		foreach($exports as $export) {
			/* insert poller stats into the settings table */
			db_execute_prepared('UPDATE graph_exports 
				SET last_checked = NOW()
				WHERE id = ?', 
				array($export['id']));

			$runnow = false;

			if (!$force) {
				if ($export['export_timing'] == 'periodic') {
					if ($export['last_checked'] == '0000-00-00 00:00:00') {
						$runnow = true;
					}else{
						$last_checked = strtotime($export['export_timing']);
						if ($last_checked + $export['export_skip'] * $poller_interval < $start_time) {
							$runnow = true;
						}
					}
				}else{
					switch($export['export_timing']) {
					case 'hourly':
						$current = date('H:i:s', $start_time);
						$seconds = date('s',     $start_time);
						$hours   = date('H',     $start_time);
						$today   = date('Y-m-d', $start_time);
						$nextst  = $export['export_hourly'];
	
						$stn = strtotime($today . ' ' . $hours . ':' . $nextst . ':' . $seconds);
						$stp = $start_time - $poller_interval;
	
						if ($stn < $stp && $stn < $start_time) {
							$runnow = true;
						}
	
						break;
					case 'daily':
						$current = date('H:i:s', $start_time);
						$seconds = date('s',     $start_time);
						$today   = date('Y-m-d', $start_time);
						$nextst  = $export['export_daily'];
	
						$stn = strtotime($today . ' ' . $nextst . ':' . $seconds);
						$stp = $start_time - $poller_interval;
	
						if ($stn < $stp && $stn < $start_time) {
							$runnow = true;
						}
	
						break;
					}
				}
			}else{
				$runnow = true;
			}
	
			if ($runnow) {
				export_debug('Running Export for id ' . $export['id']);
				run_export($export);
			}
		}
	}
}

/* run_export - a function the pre-processes the export structure and 
   then executes the required functions to export graphs, html and
   config, to sanitize directories, and transfer data to the remote
   host(s).
   @arg $export   - the export item structure. */
function run_export(&$export) {
	global $export_path;

	$exported = 0;

	switch ($export['export_type']) {
	case 'local':
		export_debug("Export Type is 'local'");

		$export_path = $export['export_directory'];

		$exported = exporter($export, $export_path);

		break;
	case 'sftp':
		export_debug("Export Type is 'sftp_php'");

		if (!function_exists('ftp_ssl_connect')) {
			export_fatal($export, 'Secure FTP Function does not exist.  Export can not continue.');
		}
	case 'ftp':
		export_debug("Export Type is 'ftp'");

		/* set the temp directory */
		if (strlen($export['export_temp_directory']) == 0) {
			$stExportDir = $_ENV['TMP'] . '/cacti-ftp-temp';
		}else{
			$stExportDir = $export['export_temp_directory'] . '/cacti-ftp-temp';
		}

		$exported = exporter($export, $stExportDir);

		export_pre_ftp_upload($export, $stExportDir);

		export_log('Using PHP built-in FTP functions.');
		export_ftp_php_execute($export, $stExportDir);
		export_post_ftp_upload($export, $stExportDir);

		break;
	case 'ftp_nc':
		export_debug("Export Type is 'ftp_nc'");
		if (strstr(PHP_OS, 'WIN')) export_fatal($export, 'ncftpput only available in unix environment!  Export can not continue.');

		/* set the temp directory */
		if (strlen($export['export_temp_directory']) == 0) {
			$stExportDir = $_ENV['TMP'] . '/cacti-ftp-temp';
		}else{
			$stExportDir = $export['export_temporary_directory'] . '/cacti-ftp-temp';
		}

		$exported = exporter($export, $stExportDir);

		export_pre_ftp_upload($export, $stExportDir);

		export_log('Using ncftpput.');
		export_ftp_ncftpput_execute($export, $stExportDir);
		export_post_ftp_upload($export, $stExportDir);

		break;
	case 'rsync':
		export_debug("Export Type is 'rsync'");

		/* set the temp directory */
		if (strlen($export['export_temp_directory']) == 0) {
			$stExportDir = $_ENV['TMP'] . '/cacti-rsync-temp';
		}else{
			$stExportDir = $export['export_temporary_directory'] . '/cacti-rsync-temp';
		}

		$exported = exporter($export, $stExportDir);

		break;
	case 'scp':
		export_debug("Export Type is 'scp'");

		/* set the temp directory */
		if (strlen($export['export_temp_directory']) == 0) {
			$stExportDir = $_ENV['TMP'] . '/cacti-scp-temp';
		}else{
			$stExportDir = $export['export_temporary_directory'] . '/cacti-scp-temp';
		}

		$exported = exporter($export, $stExportDir);

		break;
	default:
		export_fatal($export, 'Export method not specified. Exporting can not continue.  Please set method properly in Cacti configuration.');
	}

	config_export_stats($export, $exported);
}

/* exporter - a wrapper function that reduces clutter in the run_export
   function.
   @arg $export      - the export item structure.
   @arg $export_path - the location to storage export output. */
function exporter(&$export, $export_path) {
	$exported = 0;

	//$exported = export_graphs($export, $export_path);
	if ($export['export_presentation'] == 'tree') {
		tree_export($export, $export_path);
	}else{
		site_export($export, $export_path);
	}

	return $exported;
}

/* config_export_stats - a function to export stats to the Cacti system for information
   and possible graphing. It uses a global variable to get the start time of the 
   export process.
   @arg $export   - the export item structure 
   @arg $exported - the number of graphs exported. */
function config_export_stats(&$export, $exported) {
	global $start;
	/* take time to log performance data */
	$end = microtime(true);

	$export_stats = sprintf(
		'ExportID:%s ExportDate:%s ExportDuration:%01.2f TotalGraphsExported:%s',
		$export['id'], date('Y-m-d_G:i:s'), $end - $start, $exported);

	cacti_log('STATS: ' . $export_stats, true, 'EXPORT');

	/* insert poller stats into the settings table */
	db_execute_prepared('UPDATE graph_exports 
		SET last_runtime = ?, total_graphs = ?, last_ended=NOW(), status=0
		WHERE id = ?', 
		array($end - $start, $exported, $export['id']));

	db_execute_prepared(sprintf("REPLACE INTO settings (name,value) values ('stats_export_%s', ?)", $export['id']), array($export_stats));
}

/* export_fatal - a simple export logging function that indicates a 
   fatal condition for developers and users.
   @arg $export    - the export item structure 
   @arg $stMessage - the debug message. */
function export_fatal(&$export, $stMessage) {
	cacti_log('FATAL ERROR: ' . $stMessage, true, 'EXPORT');
 
	/* insert poller stats into the settings table */
	db_execute_prepared('UPDATE graph_exports 
		SET last_error = ?, last_ended=NOW(), status=2
		WHERE id = ?', 
		array($stMessage, $export['id']));

	export_debug($stMessage);

	exit;
}

/* export_log - a simple export logging function that also logs to stdout
   for developers.
   @arg $message - the debug message. */
function export_log($stMessage) {
	cacti_log($stMessage, true, 'EXPORT', POLLER_VERBOSITY_HIGH);

	export_debug($stMessage);
}

/* export_debug - a common cli debug level output for developers.
   @arg $message - the debug message. */
function export_debug($message) {
	global $debug;

	if ($debug) {
		print rtrim($message) . "\n";
	}
}

/* export_pre_ftp_upload - this function creates a global variable
   of your pre-checked ftp credentials and settings that will be used
   for the actual ftp transfer.
   @arg $export       - the export item structure */
function export_pre_ftp_upload(&$export) {
	global $config, $aFtpExport;

	$aFtpExport['server'] = $export['export_host'];
	if (empty($aFtpExport['server'])) {
		export_fatal($export, 'FTP Hostname is not expected to be blank!');
	}

	$aFtpExport['remotedir'] = $export['export_directory'];
	if (empty($aFtpExport['remotedir'])) {
		export_fatal($export, 'FTP Remote export path is not expected to be blank!');
	}

	$aFtpExport['port'] = $export['export_port'];
	$aFtpExport['port'] = empty($aFtpExport['port']) ? '21' : $aFtpExport['port'];

	$aFtpExport['username'] = $export['export_ftp_user'];
	$aFtpExport['password'] = $export['export_ftp_password'];

	if (empty($aFtpExport['username'])) {
		$aFtpExport['username'] = 'Anonymous';
		$aFtpExport['password'] = '';
		export_log('Using Anonymous transfer method.');
	}

	if ($export['export_passive'] == 'on') {
		$aFtpExport['passive'] = TRUE;
		export_log('Using passive transfer method.');
	}else {
		$aFtpExport['passive'] = FALSE;
		export_log('Using active transfer method.');
	}
}

/* check_cacti_paths - this function is looking for bad export paths that 
   can potentially get the user in trouble.  We avoid paths that can 
   get erased by accident.
   @arg $export       - the export item structure
   @arg $export_path  - the directory holding the export contents. */
function check_cacti_paths(&$export, $export_path) {
	global $config;

	$cacti_root_path = $config['base_path'];

	/* check for bad directories within the cacti path */
	if (strcasecmp($cacti_root_path, $export_path) < 0) {
		$cacti_system_paths = array(
			'include',
			'lib',
			'install',
			'rra',
			'log',
			'scripts',
			'plugins',
			'images',
			'resource');

		foreach($cacti_system_paths as $cacti_system_path) {
			if (substr_count(strtolower($export_path), strtolower($cacti_system_path)) > 0) {
				export_fatal($export, "Export path '" . $export_path . "' is potentially within a Cacti system path '" . $cacti_system_path . "'.  Can not continue.");
			}
		}
	}

	/* can not be the web root */
	if ((strcasecmp($cacti_root_path, $export_path) == 0) &&
		(read_config_option('export_type') == 'local')) {
		export_fatal($export, "Export path '" . $export_path . "' is the Cacti web root.  Can not continue.");
	}

	/* can not be a parent of the Cacti web root */
	if (strncasecmp($cacti_root_path, $export_path, strlen($export_path))== 0) {
		export_fatal($export, "Export path '" . $export_path . "' is a parent folder from the Cacti web root.  Can not continue.");
	}

}

function check_system_paths(&$export, $export_path) {
	/* don't allow to export to system paths */
	$system_paths = array(
		'/boot',
		'/lib',
		'/usr',
		'/usr/bin',
		'/bin',
		'/sbin',
		'/usr/sbin',
		'/usr/lib',
		'/var/lib',
		'/var/log',
		'/root',
		'/etc',
		'windows',
		'winnt',
		'program files');

	foreach($system_paths as $system_path) {
		if (substr($system_path, 0, 1) == '/') {
			if ($system_path == substr($export_path, 0, strlen($system_path))) {
				export_fatal($export, "Export path '" . $export_path . "' is within a system path '" . $system_path . "'.  Can not continue.");
			}
		}elseif (substr_count(strtolower($export_path), strtolower($system_path)) > 0) {
			export_fatal($export, "Export path '" . $export_path . "' is within a system path '" . $system_path . "'.  Can not continue.");
		}
	}
}

/* export_graphs - this function exports all the graphs and some html for
   mgtg view data.  these are all the graphs that are in scope for the export
   be it a tree export, or a site export.
   @arg $export       - the export item structure
   @arg $export_path  - the directory holding the export contents. */
function export_graphs(&$export, $export_path) {
	global $config;

	/* insert started time into database */
	db_execute_prepared('UPDATE graph_exports 
		SET last_started = NOW(), status=1
		WHERE id = ?',
		array($export['id']));

	/* check for bad directories */
	check_cacti_paths($export, $export_path);
	check_system_paths($export, $export_path);

	/* if the path is not a directory, don't continue */
	if (!is_dir($export_path)) {
		if (!mkdir($export_path)) {
			export_fatal($export, "Unable to create path '" . $export_path . "'!  Export can not continue.");
		}
	}

	if (!is_dir($export_path . '/graphs')) {
		if (!mkdir($export_path . '/graphs')) {
			export_fatal($export, "Unable to create path '" . $export_path . "/graphs'!  Export can not continue.");
		}
	}

	if (!is_writable($export_path)) {
		export_fatal($export, "Unable to write to path '" . $export_path . "'!  Export can not continue.");
	}

	if (!is_writable($export_path . '/graphs')) {
		export_fatal($export, "Unable to write to path '" . $export_path . "/graphs'!  Export can not continue.");
	}

	/* blank paths are not good */
	if (strlen($export_path) == 0) {
		export_fatal($export, 'Export path is null!  Export can not continue.');
	}

	export_log('Running graph export');

	$user       = $export['export_effective_user'];
	$trees      = $export['graph_tree'];
	$sites      = $export['graph_site'];

	$ntree      = array();
	$graphs     = array();
	$ngraph     = array();

	$limit      = 1000;
	$sql_where  = '';
	$hosts      = '';
	$total_rows = 0;
	$exported   = 0;
	$metadata   = array();

	if ($user == 0) {
		$user = -1;
	}

	if ($export['export_presentation'] == 'tree') {
		if ($trees != '') {
			$sql_where = 'gt.id IN(' . $trees . ')';
		}

		$trees = get_allowed_trees(false, false, $sql_where, 'name', '', $total_rows, $user);

		if (sizeof($trees)) {
			foreach($trees as $tree) {
				$ntree[] = $tree['id'];
			}
		}

		if (sizeof($ntree)) {
			$graphs = array_rekey(
				db_fetch_assoc('SELECT DISTINCT local_graph_id 
					FROM graph_tree_items 
					WHERE local_graph_id > 0 
					AND graph_tree_id IN(' . implode(', ', $ntree) . ')'), 
				'local_graph_id', 'local_graph_id'
			);

			if (sizeof($graphs)) {
				foreach($graphs as $local_graph_id) {
					if (is_graph_allowed($local_graph_id, $user)) {
						$ngraph[$local_graph_id] = $local_graph_id;
					}
				}
			}

			$hosts = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT host_id) 
				FROM graph_tree_items 
				WHERE graph_tree_id IN(?)', 
				array(implode(', ', $ntree)));

			if ($hosts != '') {
				$sql_where = 'gl.host_id IN(' . $hosts . ')';
				$graphs = get_allowed_graphs($sql_where, 'gtg.title_cache', '', $total_rows, $user);

				if (sizeof($graphs)) {
					foreach($graphs as $graph) {
						if (is_graph_allowed($graph['local_graph_id'], $user)) {
							$ngraph[$graph['local_graph_id']] = $graph['local_graph_id'];
						}
					}
				}
			}
		}
	}else{
		if ($sites != '') {
			$hosts = db_fetch_cell('SELECT GROUP_CONCAT(id) FROM host WHERE site_id IN(' . $sites . ')');
		}

		if ($hosts != '') {
			$sql_where = 'gl.host_id IN(' . $hosts . ')';
		}

		$graphs = get_allowed_graphs($sql_where, 'gtg.title_cache', '', $total_rows, $user);

		if (sizeof($graphs)) {
			foreach($graphs as $graph) {
				if (is_graph_allowed($graph['local_graph_id'], $user)) {
					$ngraph[$graph['local_graph_id']] = $graph['local_graph_id'];
				}
			}
		}
	}

	if (sizeof($ngraph)) {
		/* open a pipe to rrdtool for writing */
		$rrdtool_pipe = rrd_init();

		foreach($ngraph as $local_graph_id) {
			/* settings for preview graphs */
			$graph_data_array['export_filename'] = $export_path . '/graphs/thumb_' . $local_graph_id . '.png';

			$graph_data_array['graph_height']    = $export['graph_height'];
			$graph_data_array['graph_width']     = $export['graph_width'];
			$graph_data_array['graph_nolegend']  = true;
			$graph_data_array['export']          = true;

			check_remove($graph_data_array['export_filename']);
			check_remove($export_path . '/graph_' . $local_graph_id . '.html');

			export_log("Creating Graph '" . $graph_data_array['export_filename'] . "'");

			rrdtool_function_graph($local_graph_id, 0, $graph_data_array, $rrdtool_pipe, $metadata, $user);

			$exported++;

			/* generate html files for each graph */
			export_log("Creating File  '" . $export_path . '/graph_' . $local_graph_id . '.html');

			$fp_graph_index = fopen($export_path . '/graph_' . $local_graph_id . '.html', 'w');

			fwrite($fp_graph_index, '<table><tr><td><strong>Graph - ' . get_graph_title($local_graph_id) . '</strong></td></tr>');

			$rras = get_associated_rras($local_graph_id, ' AND dspr.id IS NOT NULL');

			/* reset vars for actual graph image creation */
			unset($graph_data_array);

			/* generate graphs for each rra */
			if (sizeof($rras)) {
				foreach ($rras as $rra) {
					$graph_data_array['export_filename'] = $export_path . '/graphs/graph_' . $local_graph_id . '_' . $rra['id'] . '.png';
					$graph_data_array['export']          = true;

					check_remove($graph_data_array['export_filename']);

					export_log("Creating Graph '" . $graph_data_array['export_filename'] . "'");

					rrdtool_function_graph($local_graph_id, $rra['id'], $graph_data_array, $rrdtool_pipe, $metadata, $user);

					$exported++;

					fwrite($fp_graph_index, "<tr><td><div align=center><img src='graphs/graph_" . $local_graph_id . '_' . $rra['id'] . ".png'></div></td></tr>\n");
					fwrite($fp_graph_index, "<tr><td><div align=center><strong>" . $rra['name'] . '</strong></div></td></tr>');
				}

				fwrite($fp_graph_index, '</table>');
				fclose($fp_graph_index);
  			}
		}

		/* close the rrdtool pipe */
		rrd_close($rrdtool_pipe);
	}

	return $exported;
}

/* export_ftp_php_execute - this function creates the ftp connection object,
   optionally sanitizes the destination and then calls the function to copy
   data to the remote host.   
   @arg $export       - the export item structure
   @arg $stExportDir  - the temporary data holding the staged export contents.
   @arg $stFtpType    - the type of ftp transfer, secure or unsecure. */
function export_ftp_php_execute(&$export, $stExportDir, $stFtpType = 'ftp') {
	global $aFtpExport;

	/* connect to foreign system */
	switch($stFtpType) {
	case 'ftp':
		$oFtpConnection = ftp_connect($aFtpExport['server'], $aFtpExport['port']);

		if (!$oFtpConnection) {
			export_fatal($export, 'FTP Connection failed! Check hostname and port.  Export can not continue.');
		}else {
			export_log('Conection to remote server was successful.');
		}
		break;
	case 'sftp':
		$oFtpConnection = ftp_ssl_connect($aFtpExport['server'], $aFtpExport['port']);

		if (!$oFtpConnection) {
			export_fatal($export, 'SFTP Connection failed! Check hostname and port.  Export can not continue.');
		}else {
			export_log('Conection to remote server was successful.');
		}
		break;
	}

	/* login to foreign system */
	if (!ftp_login($oFtpConnection, $aFtpExport['username'], $aFtpExport['password'])) {
		ftp_close($oFtpConnection);
		export_fatal($export, 'FTP Login failed! Check username and password.  Export can not continue.');
	}else {
		export_log('Remote login was successful.');
	}

	/* set connection type */
	if ($aFtpExport['passive']) {
		ftp_pasv($oFtpConnection, TRUE);
	}else {
		ftp_pasv($oFtpConnection, FALSE);
	}

	/* change directories into the remote upload directory */
	if (!@ftp_chdir($oFtpConnection, $aFtpExport['remotedir'])) {
		ftp_close($oFtpConnection);
		export_fatal($export, "FTP Remote directory '" . $aFtpExport['remotedir'] . "' does not exist!.  Export can not continue.");
	}

	/* sanitize the remote location if the user has asked so */
	if ($export['export_sanitize_remote'] == 'on') {
		export_log('Deleting remote files.');

		/* get rid of the files first */
		$aFtpRemoteFiles = ftp_nlist($oFtpConnection, $aFtpExport['remotedir']);

		if (is_array($aFtpRemoteFiles)) {
			foreach ($aFtpRemoteFiles as $stFile) {
				export_log("Deleting remote file '" . $stFile . "'");
				@ftp_delete($oFtpConnection, $stFile);
			}
		}

		/* if the presentation is tree, you will have some directories too */
		if ($export['export_presentation'] == 'tree') {
			$aFtpRemoteDirs = ftp_nlist($oFtpConnection, $aFtpExport['remotedir']);

			foreach ($aFtpRemoteDirs as $remote_dir) {
				if (ftp_chdir($oFtpConnection, addslashes($remote_dir))) {
					$aFtpRemoteFiles = ftp_nlist($oFtpConnection, '.');
					if (is_array($aFtpRemoteFiles)) {
						foreach ($aFtpRemoteFiles as $stFile) {
							export_log("Deleting Remote File '" . $stFile . "'");
							ftp_delete($oFtpConnection, $stFile);
						}
					}
					ftp_chdir($oFtpConnection, '..');

					export_log("Removing Remote Directory '" . $remote_dir . "'");
					ftp_rmdir($oFtpConnection, $remote_dir);
				}else{
					ftp_close($oFtpConnection);
					export_fatal($export, 'Unable to cd on remote system');
				}
			}
		}

		$aFtpRemoteFiles = ftp_nlist($oFtpConnection, $aFtpExport['remotedir']);
		if (sizeof($aFtpRemoteFiles) > 0) {
			ftp_close($oFtpConnection);
			export_fatal($export, 'Problem sanitizing remote ftp location, must exit.');
		}
	}

	/* upload files to remote system */
	export_log('Uploading files to remote location.');
	ftp_chdir($oFtpConnection, $aFtpExport['remotedir']);
	export_ftp_php_uploaddir($stExportDir,$oFtpConnection);

	/* end connection */
	export_log('Closing ftp connection.');
	ftp_close($oFtpConnection);
}

/* export_ftp_php_uploaddir - this function performs the transfer of the exported
   data to the remote host.
   @arg $dir - the directory to transfer to the remote host. 
   @arge $oFtpConnection - the ftp connection object created previously. */
function export_ftp_php_uploaddir($dir, $oFtpConnection) {
	global $aFtpExport;

	export_log("Uploading directory: '$dir' to remote location.");
	if($dh = opendir($dir)) {
		export_log('Uploading files to remote location.');
		while(($file = readdir($dh)) !== false) {
			$filePath = $dir . '/' . $file;
			if($file != '.' && $file != '..' && !is_dir($filePath)) {
				if(!ftp_put($oFtpConnection, $file, $filePath, FTP_BINARY)) {
					export_log("Failed to upload '$file'.");
				}
			}

			if (($file != '.') &&
				($file != '..') &&
				(is_dir($filePath))) {

				export_log("Create remote directory: '$file'.");
				ftp_mkdir($oFtpConnection,$file);

				export_log("Change remote directory to: '$file'.");
				ftp_chdir($oFtpConnection,$file);
				export_ftp_php_uploaddir($filePath,$oFtpConnection);

				export_log('Change remote directory: one up.');
				ftp_cdup($oFtpConnection);
			}
		}
		closedir($dh);
	}
}

/* export_ftp_ncftpput_execute - this function performs the transfer of the exported
   data to the remote host.
   @arg $stExportDir - the directory to transfer to the remote host. */
function export_ftp_ncftpput_execute($stExportDir) {
	global $aFtpExport;

	chdir($stExportDir);

	/* set the initial command structure */
	$stExecute = 'ncftpput -R -V -r 1 -u ' . cacti_escapeshellarg($aFtpExport['username']) . ' -p ' . cacti_escapeshellarg($aFtpExport['password']);

	/* if the user requested passive mode, use it */
	if ($aFtpExport['passive']) {
		$stExecute .= ' -F ';
	}

	/* setup the port, server, remote directory and all files */
	$stExecute .= ' -P ' . cacti_escapeshellarg($aFtpExport['port']) . ' ' . cacti_escapeshellarg($aFtpExport['server']) . ' ' . cacti_escapeshellarg($aFtpExport['remotedir']) . '.';

	/* run the command */
	$iExecuteReturns = 0;
	system($stExecute, $iExecuteReturns);

	$aNcftpputStatusCodes = array (
		'Success.',
		'Could not connect to remote host.',
		'Could not connect to remote host - timed out.',
		'Transfer failed.',
		'Transfer failed - timed out.',
		'Directory change failed.',
		'Directory change failed - timed out.',
		'Malformed URL.',
		'Usage error.',
		'Error in login configuration file.',
		'Library initialization failed.',
		'Session initialization failed.');

	export_log('Ncftpput returned: ' . $aNcftpputStatusCodes[$iExecuteReturns]);
}

/* export_post_ftp_upload - this function clean's up the local temporary 
   directory after the data transfer has completed.
   @arg $export - the export structure
   @arg $stExportDir  - the temporary directory where files were staged. */
function export_post_ftp_upload(&$export, $stExportDir) {
	/* clean-up after ftp-put */
	if ($dh = opendir($stExportDir)) {
		while (($file = readdir($dh)) !== false) {
			$filePath = $stExportDir . '/' . $file;
			if ($file != '.' && $file != '..' && !is_dir($filePath)) {
				export_log("Removing Local File '" . $file . "'");
				unlink($filePath);
			}

			/* if the directory turns out to be a sub-directory, delete it too */
			if ($file != '.' && $file != '..' && is_dir($filePath)) {
				export_log("Removing Local Directory '" . $filePath . "'");
				export_post_ftp_upload($filePath);
			}
		}
		closedir($dh);

		/* don't delete the root of the temporary export directory */
		if ($export['export_temp_directory'] != $stExportDir) {
			rmdir($stExportDir);
		}
	}
}

/* write_branch_conf - this function writes a json array of all graphs
   that lie on a branch within a tree.
   @arg $tree_id     - the tree id of the branch
   @arg $branch_id   - the branch id of the branch
   @arg $type        - the type of conf file including tree, branch, host, host_gt, host_dq, and host_dqi
   @arg $host_id     - the host id of any host level tree objects
   @arg $sub_id      - the sub id of the object passed.  This is either a numeric data point, or a hybrid
                       the case of the host_dqi object.
   @arg $user        - the effective user to use for export, -1 indicates no permission check
   @arg $export_path - the location to store the json array configuration file */
function write_branch_conf($tree_id, $branch_id, $type, $host_id, $sub_id, $user, $export_path) {
	static $json_files = array();
	$total_rows  = 0;
	$graph_array = array();

	if ($type == 'branch') {
		$json_file = $export_path . '/tree_' . $tree_id . '_branch_' . $branch_id . '.json';

		if (isset($json_files[$json_file])) return;

		$graphs = db_fetch_assoc_prepared('SELECT DISTINCT local_graph_id 
			FROM graph_tree_items 
			WHERE graph_tree_id = ? 
			AND parent = ? 
			AND local_graph_id > 0
			ORDER BY position', array($tree_id, $branch_id));
	}elseif ($type == 'host') {
		$json_file = $export_path . '/host_' . $host_id . '.json';

		if (isset($json_files[$json_file])) return;

		$graphs = get_allowed_graphs('gl.host_id=' . $host_id, 'gtg.title_cache', '', $total_rows, $user);
	}elseif ($type == 'host_gt') {
		$json_file = $export_path . '/host_' . $host_id . '_gt_' . $sub_id . '.json';

		if (isset($json_files[$json_file])) return;

		$graphs = get_allowed_graphs('gl.host_id=' . $host_id . ' AND gl.graph_template_id=' . $sub_id, 'gtg.title_cache', '', $total_rows, $user);
	}elseif ($type == 'host_dq') {
		$json_file = $export_path . '/host_' . $host_id . '_dq_' . $sub_id . '.json';

		if (isset($json_files[$json_file])) return;

		$graphs = get_allowed_graphs('gl.host_id=' . $host_id . ' AND gl.snmp_query_id=' . $sub_id, 'gtg.title_cache', '', $total_rows, $user);
	}elseif ($type == 'host_dqi') {
		$parts = explode(':', $sub_id);
		$dq    = $parts[0];
		$index = clean_up_name($parts[1]);
		$json_file = $export_path . '/host_' . $host_id . '_dq_' . $dq . '_dqi_' . $index . '.json';

		if (isset($json_files[$json_file])) return;

		$graphs = get_allowed_graphs('gl.host_id=' . $host_id . ' AND gl.snmp_query_id=' . $dq . ' AND gl.snmp_index=' . db_qstr($parts[1]), 'gtg.title_cache', '', $total_rows, $user);
	}

	if (sizeof($graphs)) {
		$fp = fopen($json_file, 'w');
		$graph_array = array();

		foreach($graphs as $graph) {
			if ($host_id == 0) {
				if (is_graph_allowed($graph['local_graph_id'], $user)) {
					$graph_array[] = $graph['local_graph_id'];
				}
			}else{
				$graph_array[] = $graph['local_graph_id'];
			}
		}

		fwrite($fp, json_encode($graph_array) . "\n");
		fclose($fp);
	}

	$json_files[$json_file] = true;

	return sizeof($graph_array);;
}

/* export_generate_tree_html - create jstree compatible static tree html.  This is a
   set of unsorted lists that jstree can properly parse into a tree object. Note that
   this is a reentrant/recursive function that will call iteself.
   @arg $export_path  - the location to write the resulting index.html file
   @arg $tree         - the tree array including information about the tree
   @arg $parent       - the parent of any branch to be searched
   @arg $expand_hosts - the setting of expand hosts for the export
   @arg $user         - the effective user to use for permission checks.  -1 indicated
                        no permission check.
   @arg $jstree       - the html of the jstree compatible unsorted list */
function export_generate_tree_html($export_path, $tree, $parent, $expand_hosts, $user, $jstree) {
	static $depth = 2;

	$total_rows = 0;

	$jstree    .= str_repeat("\t", $depth) . "<ul>\n";

	$depth++;

	write_branch_conf($tree['id'], $parent, 'branch', 0, 0, $user, $export_path);

	$branches = db_fetch_assoc_prepared('SELECT id, title
		FROM graph_tree_items
		WHERE local_graph_id = 0
		AND host_id = 0
		AND graph_tree_id = ?
		AND parent = ?',
		array($tree['id'], $parent));

	if (sizeof($branches)) {
		foreach($branches as $branch) {
			write_branch_conf($tree['id'], $branch['id'], 'branch', 0, 0, $user, $export_path);

			$has_children = db_fetch_cell_prepared('SELECT count(*) 
				FROM graph_tree_items 
				WHERE graph_tree_id = ?
				AND local_graph_id = 0
				AND parent = ?',
				array($tree['id'], $branch['id']));

			$jstree .= str_repeat("\t", $depth) . '<li id="tree|' . $tree['id'] . '|branch|' . $branch['id'] . '">' . $branch['title'];

			if ($has_children) {
				$depth++;
				$jstree .= "\n";

				$jstree = export_generate_tree_html($export_path, $tree, $branch['id'], $expand_hosts, $user, $jstree);

				$depth--;
				$jstree .= str_repeat("\t", $depth) . "</li>\n";
			}else{
				$jstree .= "</li>\n";
			}
		}
	}

	$hosts = db_fetch_assoc_prepared('SELECT DISTINCT host_id, host_grouping_type
		FROM graph_tree_items 
		WHERE graph_tree_id = ? 
		AND parent = ? 
		AND host_id > 0
		ORDER BY position', array($tree['id'], $parent));

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			if (is_device_allowed($host['host_id'], $user)) {
				write_branch_conf($tree['id'], $parent, 'host', $host['host_id'], 0, $user, $export_path);

				$host_description = get_host_description($host['host_id']);

				$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . "' data-jstree='{ \"type\" : \"device\" }'>" . $host_description . "\n";

				$depth++;

				if ($expand_hosts == 'on') {
					$templates = get_allowed_graph_templates('gl.host_id =' . $host['host_id'], 'name', '', $total_rows, $user);
					$count = 0;
					if (sizeof($templates)) {
						if ($host['host_grouping_type'] == 1) {
							foreach($templates as $template) {
								$total_rows = write_branch_conf($tree['id'], $parent, 'host_gt', $host['host_id'], $template['id'], $user, $export_path);
								if ($total_rows) {
									if ($count == 0) {
										$jstree .= str_repeat("\t", $depth) . "<ul>\n";

										$depth++;
									}
									$count++;

									$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . "_gt_" . $template['id'] . "' data-jstree='{ \"type\" : \"graph_template\" }'>" . $template['name'] . "</li>\n";
								}
							}

							if ($count) {
								$depth--;
								$jstree .= str_repeat("\t", $depth) . "</ul>\n";
								$depth--;
								$jstree .= str_repeat("\t", $depth) . "</li>\n";
							}
						}else{
							$data_queries = db_fetch_assoc_prepared('SELECT sq.id, sq.name 
								FROM snmp_query AS sq
								INNER JOIN host_snmp_query AS hsq
								ON sq.id=hsq.snmp_query_id
								WHERE hsq.host_id = ?',
								array($host['host_id']));

							$data_queries[] = array('id' => '0', 'name' => __('Non Query Based'));

							foreach($data_queries as $query) {
								$total_rows = write_branch_conf($tree['id'], $parent, 'host_dq', $host['host_id'], $query['id'], $user, $export_path);
								if ($total_rows && $query['id'] > 0) {
									if ($count == 0) {
										$jstree .= '<ul>';
									}
									$count++;

									$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . "_dq_" . $query['id'] . "' data-jstree='{ \"type\" : \"data_query\" }'>" . $query['name'];

									$depth++;

									$jstree .= str_repeat("\t", $depth) . "<ul>\n";

									$depth++;

									$graphs = db_fetch_assoc_prepared('SELECT gl.* 
										FROM graph_local AS gl 
										WHERE host_id = ? 
										AND snmp_query_id = ?', 
										array($host['host_id'], $query['id']));

									$dqi = array();
									foreach($graphs as $graph) {
										$dqi[$graph['snmp_index']] = $graph['snmp_index'];
									}

									foreach($dqi as $i) {
										$total_rows = write_branch_conf($tree['id'], $parent, 'host_dqi', $host['host_id'], $query['id'] . ':' . $i, $user, $export_path);
										if ($total_rows) {
											$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . "_dq_" . $query['id'] . "_dqi_" . clean_up_name($i) . "' data-jstree='{ \"type\" : \"graph\" }'>" . $query['name'] . "</li>\n";
										}
									}

									$depth--;

									$jstree .= str_repeat("\t", $depth) . "</ul>\n";
								}
							}

							if ($count) {
								$depth--;
								$jstree .= str_repeat("\t", $depth) . "</ul>\n";
								$depth--;
								$jstree .= str_repeat("\t", $depth) . "</li>\n";
							}
						}
					}
				}
			}
		}
	}

	$depth--;

	$jstree .= str_repeat("\t", $depth) . "</ul>\n";

	return $jstree;
}

/* tree_export - the first of a series of functions that are designed to
   create the tree in html, and present the graphs within the tree into
   static configuration files that will hold the graphs to be rendered.
   @arg $export       - the export item data structure
   @arg $export_path  - the location to write the resulting index.html file */
function tree_export(&$export, $export_path) {
	global $config;

	$jstree     = "<div id='jstree'>\n";
	$user       = $export['export_effective_user'];
	$trees      = $export['graph_tree'];
	$ntree      = array();
	$sql_where  = '';
	$total_rows = 0;
	$parent     = 0;

	if ($user == 0) {
		$user = -1;
	}

	if ($trees != '') {
		$sql_where = 'gt.id IN(' . $trees . ')';
	}

	$trees = get_allowed_trees(false, false, $sql_where, 'name', '', $total_rows, $user);

	if (sizeof($trees)) {
		foreach($trees as $tree) {
			$jstree .= "\t<li id='tree|" . $tree['id'] . "' data-jstree='{ \"type\" : \"tree\" }'>" . get_tree_name($tree['id']) . "\n";;
			$jstree = export_generate_tree_html($export_path, $tree, $parent, $export['export_expand_hosts'], $user, $jstree);
			$jstree .= "\t</li>\n";
		}
	}

	if ($jstree != '') {
		$jstree .= "</div>\n";

		$fp = fopen($export_path . '/index.html', 'w');

		if (is_resource($fp)) {
			fwrite($fp, $jstree);
			fclose($fp);
		}
	}
}

/* create_export_directory_structure - builds the export directory strucutre and copies
   graphics and treeview scripts to those directories.
   @arg $cacti_root_path - the directory where Cacti is installed
        $dir - the export directory where graphs will either be staged or located.
*/
function create_export_directory_structure($cacti_root_path, $dir) {
	/* create the treeview sub-directory */
	if (!is_dir("$dir/js")) {
		if (!mkdir("$dir/js", 0755, true)) {
			export_fatal($export, "Create directory '" . $dir . "/js' failed.  Can not continue");
		}
		if (!mkdir("$dir/js/themes/default", 0755, true)) {
			export_fatal($export, "Create directory '" . $dir . "/js/themes/default' failed.  Can not continue");
		}
		if (!mkdir("$dir/js/images", 0755, true)) {
			export_fatal($export, "Create directory '" . $dir . "/js/images' failed.  Can not continue");
		}
	}

	/* create the graphs sub-directory */
	if (!is_dir("$dir/graphs")) {
		if (!mkdir("$dir/graphs", 0755, true)) {
			export_fatal($export, "Create directory '" . $dir . "/graphs' failed.  Can not continue");
		}
	}

	/* css */
	copy("$cacti_root_path/include/themes/modern/main.css", "$dir/main.css");

	/* images for html */
	copy("$cacti_root_path/images/tab_cacti.gif", "$dir/tab_cacti.gif");
	copy("$cacti_root_path/images/cacti_backdrop.gif", "$dir/cacti_backdrop.gif");
	copy("$cacti_root_path/images/transparent_line.gif", "$dir/transparent_line.gif");
	copy("$cacti_root_path/images/shadow.gif", "$dir/shadow.gif");
	copy("$cacti_root_path/images/shadow_gray.gif", "$dir/shadow_gray.gif");

	$files = array('server_chart_curve.png', 'server_chart.png', 'server_dataquery.png', 'server.png');
	foreach($files as $file) {
		copy("$cacti_root_path/images/$file", "$dir/$file");
	}

	/* jstree theme files */
	$files = array('32px.png', '40px.png', 'style.css', 'style.min.css', 'throbber.gif');
	foreach($files as $file) {
		copy("$cacti_root_path/include/themes/modern/default/$file", "$dir/js/themes/default/$file");
	}

	/* jquery-ui theme files */
	$files = array(
		'bar-alpha.png',
		'bar-opacity.png',
		'bar.png',
		'bar-pointer.png',
		'map-opacity.png',
		'map.png',
		'map-pointer.png',
		'preview-opacity.png',
		'ui-bg_flat_0_aaaaaa_40x100.png',
		'ui-bg_flat_75_ffffff_40x100.png',
		'ui-bg_glass_55_fbf9ee_1x400.png',
		'ui-bg_glass_65_ffffff_1x400.png',
		'ui-bg_glass_75_dadada_1x400.png',
		'ui-bg_glass_75_e6e6e6_1x400.png',
		'ui-bg_glass_95_fef1ec_1x400.png',
		'ui-bg_highlight-soft_75_cccccc_1x100.png',
		'ui-colorpicker.png',
		'ui-icons_222222_256x240.png',
		'ui-icons_2e83ff_256x240.png',
		'ui-icons_454545_256x240.png',
		'ui-icons_888888_256x240.png',
		'ui-icons_cd0a0a_256x240.png'
	);
	foreach($files as $file) {
		copy("$cacti_root_path/include/themes/modern/images/$file", "$dir/js/images/$file");
	}

	/* java scripts for the tree */
	copy("$cacti_root_path/include/js/jquery.js", "$dir/js/jquery.js");
	copy("$cacti_root_path/include/js/jquery-ui.js", "$dir/js/jquery-ui.js");
	copy("$cacti_root_path/include/js/jstree.js", "$dir/js/jstree.js");
	copy("$cacti_root_path/include/js/jquery.cookie.js", "$dir/js/jquery.cookie.js");
	copy("$cacti_root_path/include/themes/modern/jquery-ui.css", "$dir/js/jquery-ui.css");
}

/* get_host_description - a simple function to return the host description of a host.
   @arg $host_id - the id of the host in question */
function get_host_description($host_id) {
	return db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($host_id));
}

/* get_tree_name - a simple function to return the tree name of a tree.
   @arg $tree_id - the id of the tree in question */
function get_tree_name($tree_id) {
	return db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array($tree_id));
}

/* del_directory - delete the directory pointed to by the $path variable.
   @arg $path   - the directory to delete or clean 
   @arg $deldir - (optionnal parameter, true as default) delete the diretory (true) or just clean it (false) */
function del_directory($path, $deldir = true) {
	/* check if the directory name have a '/' at the end, add if not */
	if ($path[strlen($path)-1] != '/') {
		$path .= '/';
	}

	/* cascade through the directory structure(s) until they are all delected */
	if (is_dir($path)) {
		$d = opendir($path);
		while ($f = readdir($d)) {
			if ($f != '.' && $f != '..') {
				$rf = $path . $f;

				/* if it is a directory, recursive call to the function */
				if (is_dir($rf)) {
					del_directory($rf);
				}else if (is_file($rf) && is_writable($rf)) {
					unlink($rf);
				}
			}
		}
		closedir($d);

		/* if $deldir is true, remove the directory */
		if ($deldir && is_writable($path)) {
			rmdir($path);
		}
	}
}

/* check_remove - simple function to check for the existance of a file and remove it.
   @arg $filename - the file to remove. */
function check_remove($filename) {
	if (file_exists($filename) && is_writable($filename)) {
		unlink($filename);
	}
}

define('HTML_HEADER_TREE',
"<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
	<meta http-equiv='X-UA-Compatible' content='IE=edge'>
	<title>Cacti</title>
	<link rel='stylesheet' href='main.css' rel='stylesheet'>
	<link rel='stylesheet' href='./js/jquery-ui.css'>
	<link rel='stylesheet' href='./js/themes/default/style.css'>
	<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
	<meta http-equiv=Pragma content=no-cache>
	<meta http-equiv=cache-control content=no-cache>
	<script type='text/javascript' src='./js/jquery.js'></script>
	<script type='text/jsvascript' src='./js/jquery-ui.js'></script>
	<script type='text/javascript' src='./js/jstree.js'></script>
	<script type='text/javascript' src='./js/jquery.cookie.js'></script>
</head>
<body>
<table style='width:100%;height:100%;'>
	<tr style='height:37px;' bgcolor='#a9a9a9'>
		<td colspan='2' valign='bottom' nowrap>
			<table>
				<tr>
					<td nowrap>
						&nbsp;<a href='http://www.cacti.net/'><img src='tab_cacti.gif' alt='Cacti - http://www.cacti.net/' align='middle'></a>
					</td>
					<td align='right'>
						<img src='cacti_backdrop.gif' align='middle'>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr style='height:2px;' colspan='2' bgcolor='#183c8f'>
		<td colspan='2'>
			<img src='transparent_line.gif' style='width:170px;height:2px;'><br>
		</td>
	</tr>
	<tr style='height:5px;' bgcolor='#e9e9e9'>
		<td colspan='2'>
			<table width='100%'>
				<tr>
					<td>
						Exported Graphs
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td bgcolor='#efefef' colspan='1' style='height:8px;background-image: url(shadow_gray.gif); background-repeat: repeat-x; border-right: #aaaaaa 1px solid;'>
			<img src='transparent_line.gif' width='200' style='height:2px;'><br>
		</td>
		<td bgcolor='#ffffff' colspan='1' style='height:8px;background-image: url(shadow.gif); background-repeat: repeat-x;'>
		</td>
	</tr>
	<tr>
		<td valign='top' style='padding: 5px; border-right: #aaaaaa 1px solid;' bgcolor='#efefef' width='200'>
			<table>
				<tr>
					<td id='navigation' valign='top' style='padding: 5px; border-right: #aaaaaa 1px solid;background-repeat:repeat-y;background-color:#efefef;' bgcolor='#efefef' width='200' class='noprint'>\n"
);

define('HTML_GRAPH_HEADER_ONE_TREE', "
	<table style='background-color: #f5f5f5; border: 1px solid #bbbbbb;'>
		<tr bgcolor='#6d88ad'>
			<td width='390' colspan='3' class='textHeaderDark'>");

define('HTML_GRAPH_HEADER_TWO_TREE', "
		<tr>"
);

define('HTML_GRAPH_HEADER_ICE_TREE', "</td></tr></table><br><br>			<br>
		</td>
	</tr>
</table>\n");

define('HTML_GRAPH_FOOTER_TREE', "
	</tr></table>\n"
);

define('HTML_FOOTER_TREE', "
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>\n"
);

/* HTML header for the Classic Presentation */
define('HTML_HEADER_CLASSIC', "
	<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
	<html>
	<head>
		<title>cacti</title>
		<link href='main.css' rel='stylesheet'>
		<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
		<meta http-equiv=refresh content='300'; url='index.html'>
		<meta http-equiv=Pragma content=no-cache>
		<meta http-equiv=cache-control content=no-cache>
	</head>
	<body>
	<table>
		<tr style='height:37px;' bgcolor='#a9a9a9'>
			<td valign='bottom' colspan='3' nowrap>
				<table>
					<tr>
						<td valign='bottom'>
							&nbsp;<a href='http://www.cacti.net/'><img src='tab_cacti.gif' alt='Cacti - http://www.cacti.net/' align='middle'></a>
						</td>
						<td align='right'>
							<img src='cacti_backdrop.gif' align='middle'>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr style='height:2px;' bgcolor='#183c8f'>
			<td colspan='3'>
				<img src='transparent_line.gif' style='width:170px;height:2px;'><br>
			</td>
		</tr>
		<tr style='height:5px;' bgcolor='#e9e9e9'>
			<td colspan='3'>
				<table width='100%'>
					<tr>
						<td>
							Exported Graphs
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td colspan='3' style='height:8px;background-image: url(shadow.gif); background-repeat: repeat-x;' bgcolor='#ffffff'>

			</td>
		</tr>
	</table>
	<br>\n"
);

/* Traditional Graph Export Representation Graph Headers */
define('HTML_GRAPH_HEADER_ONE_CLASSIC', "
	<table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>
		<tr>
			<td class='textSubHeaderDark' colspan='" . read_config_option('export_num_columns') . "'>
				<table>
					<tr>
						<td align='center' class='textHeaderDark'>"
);

define('HTML_GRAPH_HEADER_TWO_CLASSIC', "
					</tr>
				</table>
			</td>
		</tr>
		<tr>"
);

define('HTML_GRAPH_FOOTER_CLASSIC', "
	</tr></table>
	<br><br>"
);

define('HTML_FOOTER_CLASSIC', "
	<br>
	</body>
	</html>"
);

