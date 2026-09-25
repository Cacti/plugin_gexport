<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

/**
 * Computes an export configuration's next scheduled run time, based on
 * its timing mode (periodic/hourly/daily) and configured skip interval/
 * time-of-day. Called from gexport.php's form_save() when a schedule is
 * saved, and from graph_export() when a due export is detected, to
 * schedule its next run before executing it via run_export().
 *
 * @param array $export     The graph_exports row (or submitted form
 *                          values) describing the export's timing
 *                          configuration.
 * @param int   $start_time The reference Unix timestamp to compute the
 *                          next run relative to; defaults to the current
 *                          time when 0.
 *
 * @return string The computed next start time, formatted
 *                'Y-m-d H:i:s'/'Y-m-d H:i:00'.
 */
function gexport_calc_next_start($export, $start_time = 0) {
	if ($start_time == 0) {
		$start_time = time();
	}

	$poller_interval = read_config_option('poller_interval');

	// Fallback for an unrecognized/'disabled' export_timing value (the
	// graph_exports.export_timing column's own DB default). Push this
	// far into the future rather than "now", so an enabled row with an
	// invalid/disabled schedule is treated as never-due instead of
	// running on every subsequent poller cycle.
	$next_start = date('Y-m-d H:i:s', $start_time + (86400 * 365 * 100));

	if ($export['export_timing'] == 'periodic') {
		$now        = date('Y-m-d H:i:00', time());
		$next_run   = (int) strtotime($now) + $export['export_skip'] * $poller_interval;
		$next_start = date('Y-m-d H:i:s', $next_run);
	} else {
		switch($export['export_timing']) {
			case 'hourly':
				$next_start = date('Y-m-d H:' . $export['export_hourly'] . ':00', $start_time);
				$now_time   = (int) strtotime(date('Y-m-d H:i:00', $start_time));
				$next_run   = (int) strtotime($next_start);

				if ($next_run <= $now_time) {
					$next_run += 3600;
				}

				$next_start = date('Y-m-d H:i:00', $next_run);

				break;
			case 'daily':
				$next_start = date('Y-m-d ' . $export['export_daily'] . ':00', $start_time);
				$now_time   = (int) strtotime(date('Y-m-d H:i:00', $start_time));
				$next_run   = (int) strtotime($next_start);

				if ($next_run <= $now_time) {
					$next_run += 86400;
				}

				$next_start = date('Y-m-d H:i:00', $next_run);

				break;
		}
	}

	return $next_start;
}

/**
 * A function that determines, for each export definition, if it's time
 * to run or not. This function is currently single threaded and some
 * thought should be given to making multi-threaded. Called from
 * poller_export.php's main flow (or, for a single export, via the
 * '--id' CLI argument) once per poller cycle to check every enabled
 * export definition and start any that are due.
 *
 * @arg $id    - the id of the export to check, '0' for all export definitions.
 * @arg $force - force the export to run no regardless of it's timing settings.
 *
 * @param int  $id    The graph_exports.id to check, or 0 for every
 *                    enabled export definition; defaults to 0.
 * @param bool $force Whether to run regardless of scheduled timing;
 *                    defaults to false.
 *
 * @return void
 *
 * @global bool  $debug Reserved/declared for parity with other export
 *                       functions; not used directly here.
 * @global float $start Set to the run's start time (from microtime(true)),
 *                       used to compute and log overall run duration.
 */
function graph_export($id = 0, $force = false) {
	global $debug, $start;

	// take time to log performance data
	$start           = microtime(true);
	$start_time      = time();
	$poller_interval = read_config_option('poller_interval');
	$runnow          = false;
	$sql_where       = '';
	$started_exports = 0;

	if ($force) {
		export_debug('This is a forced run');
	}

	// force run
	if ($id > 0) {
		$sql_where = ' AND id=' . $id;
	}

	$exports = db_fetch_assoc('SELECT *
		FROM graph_exports
		WHERE enabled="on"' . $sql_where);

	if (cacti_sizeof($exports)) {
		foreach ($exports as $export) {
			export_debug("Checking export '" . $export['name'] . "' to determine if it's time to run.");

			// insert poller stats into the settings table
			db_execute_prepared('UPDATE graph_exports
				SET last_checked = NOW()
				WHERE id = ?',
				[$export['id']]);

			$runnow = false;

			if (!$force) {
				if (strtotime($export['next_start']) < $start_time) {
					$runnow     = true;
					$next_start = gexport_calc_next_start($export);

					db_execute_prepared('UPDATE graph_exports
						SET next_start = ? WHERE id = ?',
						[$next_start, $export['id']]);
				}
			} else {
				$runnow = true;
			}

			if ($runnow) {
				$started_exports++;
				export_debug('Running Export for id ' . $export['id']);
				run_export($export);
			}
		}
	}

	$end = microtime(true);

	$export_stats = sprintf('Time:%01.2f Exports:%s Exported:%s', $end - $start, cacti_sizeof($exports), $started_exports);

	cacti_log('MASTER STATS: ' . $export_stats, true, 'EXPORT');
}

/**
 * A function the pre-processes the export structure and then executes
 * the required functions to export graphs, html and config, to sanitize
 * directories, and transfer data to the remote host(s). Dispatches to
 * the type-specific exporter/transfer functions based on the export's
 * configured 'export_type' (local, sftp, ftp, ftp_nc, rsync, scp).
 * Called from graph_export() for each export definition that is due to
 * run.
 *
 * @arg $export   - the export item structure.
 *
 * @param array $export Reference to the graph_exports row describing the
 *                      export to run; its export_pid/status/last_started
 *                      fields are updated as a side effect.
 *
 * @return void
 *
 * @global array  $config      Cacti global configuration array; used to
 *                             resolve OS-specific temp directory
 *                             defaults.
 * @global string $export_path Set to the resolved local/temp export
 *                             directory for the current export, for
 *                             other functions in this file to read.
 */
function run_export(&$export) {
	global $config, $export_path;

	$exported = 0;

	if (!empty($export['export_pid'])) {
		export_warn('Previous run of the following Graph Export ended in an unclean state Export:' . $export['name']);

		if (posix_kill($export['export_pid'], 0) !== false) {
			export_warn('Can not start the following Graph Export:' . $export['name'] . ' is still running');

			return;
		}
	}

	db_execute_prepared('UPDATE graph_exports
		SET export_pid = ?, status = 1, last_started=NOW()
		WHERE id = ?',
		[getmypid(), $export['id']]);

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

			// set the temp directory
			if (strlen($export['export_temp_directory']) == 0) {
				$stExportDir = getenv('TEMP') . '/cacti-ftp-temp-' . $export['id'];
			} else {
				$stExportDir = rtrim($export['export_temp_directory'], "/ \n\r") . '/cacti-ftp-temp-' . $export['id'];
			}

			$exported = exporter($export, $stExportDir);

			export_pre_ftp_upload($export);

			export_log('Using PHP built-in FTP functions.');
			export_ftp_php_execute($export, $stExportDir);
			export_post_ftp_upload($export, $stExportDir);

			break;
		case 'ftp_nc':
			export_debug("Export Type is 'ftp_nc'");

			if (strstr(PHP_OS, 'WIN')) {
				export_fatal($export, 'ncftpput only available in unix environment!  Export can not continue.');
			}

			// set the temp directory
			if (trim($export['export_temp_directory']) == '') {
				if ($config['cacti_server_os'] == 'win32') {
					$stExportDir = getenv('TEMP') . '/cacti-ftp-temp-' . $export['id'];
				} else {
					$stExportDir = '/tmp/cacti-ftp-temp-' . $export['id'];
				}
			} else {
				$stExportDir = rtrim($export['export_temp_directory'], "/ \n\r") . '/cacti-ftp-temp-' . $export['id'];
			}

			$exported = exporter($export, $stExportDir);

			export_pre_ftp_upload($export);

			export_log('Using ncftpput.');
			export_ftp_ncftpput_execute($stExportDir);
			export_post_ftp_upload($export, $stExportDir);

			break;
		case 'rsync':
			export_debug("Export Type is 'rsync'");

			// set the temp directory
			if (trim($export['export_temp_directory']) == '') {
				if ($config['cacti_server_os'] == 'win32') {
					$stExportDir = getenv('TEMP') . '/cacti-rsync-temp-' . $export['id'];
				} else {
					$stExportDir = '/tmp/cacti-rsync-temp-' . $export['id'];
				}
			} else {
				$stExportDir = rtrim($export['export_temp_directory'], "/ \n\t") . '/cacti-rsync-temp-' . $export['id'];
			}

			$exported = exporter($export, $stExportDir);

			export_rsync_execute($export, $stExportDir);

			break;
		case 'scp':
			export_debug("Export Type is 'scp'");

			// set the temp directory
			if (trim($export['export_temp_directory']) == '') {
				if ($config['cacti_server_os'] == 'win32') {
					$stExportDir = getenv('TEMP') . '/cacti-scp-temp-' . $export['id'];
				} else {
					$stExportDir = '/tmp/cacti-scp-temp-' . $export['id'];
				}
			} else {
				$stExportDir = rtrim($export['export_temp_directory'], "/ \n\r") . '/cacti-scp-temp-' . $export['id'];
			}

			$exported = exporter($export, $stExportDir);

			export_scp_execute($export, $stExportDir);

			break;
		default:
			export_fatal($export, 'Export method not specified. Exporting can not continue.  Please set method properly in Cacti configuration.');
	}

	db_execute_prepared('UPDATE graph_exports SET export_pid = 0 WHERE id = ?', [$export['id']]);

	config_export_stats($export, $exported);
}

/**
 * Transfers the exported files in the temporary export directory to a
 * remote host via rsync, honoring an optional SSH private key and port,
 * and optionally pruning empty/deleted directories on the destination.
 * Calls export_fatal() (which exits) on validation or transfer failure.
 * Called from run_export() for exports of type 'rsync'.
 *
 * @param array  $export      Reference to the graph_exports row; used
 *                            for its rsync/SSH connection settings.
 * @param string $stExportDir The local temp directory whose contents are
 *                            rsync'd to the remote host.
 *
 * @return void
 */
function export_rsync_execute(&$export, $stExportDir) {
	$keyopt = '';
	$user   = $export['export_user'];
	$port   = $export['export_port'];
	$host   = $export['export_host'];
	$output = [];
	$prune  = '';
	$retvar = 0;

	if ($export['export_private_key_path'] != '') {
		if (file_exists($export['export_private_key_path'])) {
			if (is_readable($export['export_private_key_path'])) {
				$ssh_cmd = 'ssh -i ' . cacti_escapeshellarg($export['export_private_key_path']);
			} else {
				export_fatal($export, 'ssh Private Key file is not readable.');
			}
		} else {
			export_fatal($export, 'ssh Private Key file does not exist.');
		}
	}

	if (preg_match('~^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){3}$~', $host) != 1 && gethostbyname($host) == $host) {
		export_fatal($export, "Hostname '" . $host . "' can not be resolved to an IP Address.");
	}

	if ($port != '') {
		if (!is_numeric($port)) {
			export_fatal($export, "SSH port '" . $port . "' must be numeric.");
		} else {
			$ssh_cmd = (isset($ssh_cmd) ? $ssh_cmd : 'ssh') . ' -p ' . cacti_escapeshellarg((string) $port);
		}
	}

	if (isset($ssh_cmd)) {
		$keyopt = ' -e ' . cacti_escapeshellarg($ssh_cmd);
	}

	if ($export['export_sanitize_remote'] == 'on') {
		$prune = '--delete-delay --prune-empty-dirs';
	}

	// NOTE: export_args is a free-form, admin-configured rsync option
	// string; it is intentionally not escaped as a single argument
	// since it can legitimately hold multiple options/flags, matching
	// this plugin's existing configuration model (Cacti admin-only,
	// realm-gated access).
	$remote_target = ($user != '' ? $user . '@' : '') . $host . ':' . $export['export_directory'];

	exec('rsync -q ' . $export['export_args'] . ' ' . $prune . $keyopt . ' ' .
		cacti_escapeshellarg($stExportDir . '/.') . ' ' .
		cacti_escapeshellarg($remote_target) . ' 2>&1', $output, $retvar);

	if ($retvar != 0) {
		$retvar_message = export_rsync_get_message($retvar);
		export_note('RSYNC OUTPUT: \'' . trim(implode(',',$output)) . '\'');
		export_fatal($export, "RSYNC FAILED! Return Code was '$retvar' with message '" . $retvar_message . "'");
	}
}

/**
 * Translates an rsync process exit code into a human-readable message.
 * Called from export_rsync_execute() (and, atypically, reused by
 * export_scp_execute()) when a transfer fails, to describe the failure
 * in the fatal error message.
 *
 * @param int $error_code The rsync process's exit code.
 *
 * @return string The corresponding human-readable error description, or
 *                a generic 'Unknown error' message with the code
 *                appended.
 */
function export_rsync_get_message($error_code) {
	switch ($error_code) {
		case 0: return __('Success','gexport');
		case 1: return __('Syntax or usage error','gexport');
		case 2: return __('Protocol incompatibility','gexport');
		case 3: return __('Errors selecting input/output files, dirs','gexport');
		case 4: return __('Requested action not supported: an attempt was made to manipulate 64-bit files on a platform that cannot support them; or an option was specified that is supported by the client and not by the server.','gexport');
		case 5: return __('Error starting client-server protocol','gexport');
		case 6: return __('Daemon unable to append to log-file','gexport');
		case 10: return __('Error in socket I/O','gexport');
		case 11: return __('Error in file I/O','gexport');
		case 12: return __('Error in rsync protocol data stream','gexport');
		case 13: return __('Errors with program diagnostics','gexport');
		case 14: return __('Error in IPC code','gexport');
		case 20: return __('Received SIGUSR1 or SIGINT','gexport');
		case 21: return __('Some error returned by waitpid()','gexport');
		case 22: return __('Error allocating core memory buffers','gexport');
		case 23: return __('Partial transfer due to error','gexport');
		case 24: return __('Partial transfer due to vanished source files','gexport');
		case 25: return __('The --max-delete limit stopped deletions','gexport');
		case 30: return __('Timeout in data send/receive','gexport');
		case 35: return __('Timeout waiting for daemon connection','gexport');
		default:
			return __('Unknown error ','gexport') . $error_code;
	}
}

/**
 * Transfers the exported files in the temporary export directory to a
 * remote host via scp, honoring an optional SSH private key and port.
 * Calls export_fatal() (which exits) on validation or transfer failure.
 * Called from run_export() for exports of type 'scp'.
 *
 * @param array  $export      Reference to the graph_exports row; used
 *                            for its scp/SSH connection settings.
 * @param string $stExportDir The local temp directory whose contents are
 *                            copied to the remote host.
 *
 * @return void
 */
function export_scp_execute(&$export, $stExportDir) {
	$keyopt = '';
	$user   = $export['export_user'];
	$port   = $export['export_port'];
	$host   = $export['export_host'];
	$output = [];
	$retvar = 0;

	if ($export['export_private_key_path'] != '') {
		if (file_exists($export['export_private_key_path'])) {
			if (is_readable($export['export_private_key_path'])) {
				$keyopt = ' -i ' . cacti_escapeshellarg($export['export_private_key_path']);
			} else {
				export_fatal($export, 'ssh Private Key file is not readable.');
			}
		} else {
			export_fatal($export, 'ssh Private Key file does not exist.');
		}
	}

	if (preg_match('~^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){3}$~', $host) != 1 && gethostbyname($host) == $host) {
		export_fatal($export, "Hostname '" . $host . "' can not be resolved to an IP Address.");
	}

	if ($port != '' && !is_numeric($port)) {
		export_fatal($export, "SCP port '" . $port . "' must be numeric.");
	}

	// NOTE: export_args is a free-form, admin-configured scp option
	// string; it is intentionally not escaped as a single argument
	// since it can legitimately hold multiple options/flags, matching
	// this plugin's existing configuration model (Cacti admin-only,
	// realm-gated access).
	$remote_target = ($user != '' ? $user . '@' : '') . $host . ':' . $export['export_directory'];

	exec('scp ' . $export['export_args'] . '  ' . $keyopt . ($port != '' ? ' -P ' . cacti_escapeshellarg((string) $port) . ' ' : '') .
		cacti_escapeshellarg($stExportDir . '/.') . ' ' .
		cacti_escapeshellarg($remote_target) . ' 2>&1', $output, $retvar);

	if ($retvar != 0) {
		$retvar_message = export_scp_get_message($retvar);
		export_note('SCP OUTPUT: \'' . trim(implode(',',$output)) . '\'');
		export_fatal($export, "SCP FAILED! Return Code was '$retvar' with message '" . $retvar_message . "'");
	}
}

/**
 * Translates an scp process exit code into a human-readable message.
 * Called from export_scp_execute() when a transfer fails, to describe
 * the failure in the fatal error message.
 *
 * @param int $error_code The scp process's exit code.
 *
 * @return string The corresponding human-readable error description, or
 *                a generic 'Unknown error' message with the code
 *                appended.
 */
function export_scp_get_message($error_code) {
	switch ($error_code) {
		case 0: return __('Operation was successful', 'gexport');
		case 1: return __('General error in file copy', 'gexport');
		case 2: return __('Destination is not directory, but it should be', 'gexport');
		case 3: return __('Maximum symlink level exceeded', 'gexport');
		case 4: return __('Connecting to host failed.', 'gexport');
		case 5: return __('Connection broken', 'gexport');
		case 6: return __('File does not exist', 'gexport');
		case 7: return __('No permission to access file.', 'gexport');
		case 8: return __('General error in sftp protocol', 'gexport');
		case 9: return __('File transfer protocol mismatch', 'gexport');
		case 10: return __('No file matches a given criteria', 'gexport');
		case 65: return __('Host not allowed to connect', 'gexport');
		case 66: return __('General error in ssh protocol', 'gexport');
		case 67: return __('Key exchange failed', 'gexport');
		case 68: return __('Reserved', 'gexport');
		case 69: return __('MAC error', 'gexport');
		case 70: return __('Compression error', 'gexport');
		case 71: return __('Service not available', 'gexport');
		case 72: return __('Protocol version not supported', 'gexport');
		case 73: return __('Host key not verifiable', 'gexport');
		case 74: return __('Connection failed', 'gexport');
		case 75: return __('Disconnected by application', 'gexport');
		case 76: return __('Too many connections', 'gexport');
		case 77: return __('Authentication cancelled by user', 'gexport');
		case 78: return __('No more authentication methods available', 'gexport');
		case 79: return __('Invalid user name', 'gexport');
		default:
			return __('Unknown error ', 'gexport') . $error_code;
	}
}
/**
 * A wrapper function that reduces clutter in the run_export function.
 * Creates the export directory structure and generates the exported
 * graph images/HTML/config for a single export definition. Called from
 * run_export() for every export type, before any remote transfer step.
 *
 * @arg $export      - the export item structure.
 * @arg $export_path - the location to storage export output.
 *
 * @param array  $export      Reference to the graph_exports row
 *                            describing the export to generate.
 * @param string $export_path The local (or temp) directory to write the
 *                            exported graphs/HTML/config into.
 *
 * @return int The number of graphs exported.
 *
 * @global array $config Cacti global configuration array; used to
 *                       resolve the base install path.
 */
function exporter(&$export, $export_path) {
	global $config;

	$root_path = $config['base_path'];

	$exported = 0;

	create_export_directory_structure($export, $root_path, $export_path);

	$exported = export_graphs($export, $export_path);
	tree_site_export($export, $export_path);

	return $exported;
}

/**
 * A function to export stats to the Cacti system for information and
 * possible graphing. It uses a global variable to get the start time of
 * the export process. Called from run_export() after an export
 * definition finishes (successfully or not), to record run duration and
 * counts.
 *
 * @arg $export   - the export item structure
 * @arg $exported - the number of graphs exported.
 *
 * @param array $export   Reference to the graph_exports row being
 *                        updated with its final run statistics.
 * @param int   $exported The number of graphs exported during this run.
 *
 * @return void
 *
 * @global float $start The run's start time (from microtime(true)), set
 *                      by run_export()'s caller, used to compute
 *                      duration.
 */
function config_export_stats(&$export, $exported) {
	global $start;
	// take time to log performance data
	$end = microtime(true);

	$export_stats = sprintf(
		'ExportID:%s (%s) ExportDate:%s ExportDuration:%01.2f TotalGraphsExported:%s MaximumThreads: %s',
		$export['id'], $export['name'], date('Y-m-d_G:i:s'), $end - $start, $exported, $export['export_threads']);

	cacti_log('STATS: ' . $export_stats, true, 'EXPORT');

	// insert poller stats into the settings table
	db_execute_prepared('UPDATE graph_exports
		SET last_runtime = ?, total_graphs = ?, last_ended=NOW(), status=0
		WHERE id = ?',
		[$end - $start, $exported, $export['id']]);

	db_execute_prepared(sprintf("REPLACE INTO settings (name,value) values ('stats_export_%s', ?)", $export['id']), [$export_stats]);
}

/**
 * A simple export logging function that indicates a fatal condition for
 * developers and users. Records the error on the export's graph_exports
 * row and terminates the script. Called throughout this file whenever an
 * unrecoverable export/transfer error occurs.
 *
 * @arg $export    - the export item structure
 * @arg $stMessage - the debug message.
 *
 * @param array  $export    Reference to the graph_exports row to record
 *                          the fatal error against.
 * @param string $stMessage The fatal error message to log and record.
 *
 * @return never This function always terminates script execution via
 *               exit and therefore never returns.
 */
function export_fatal(&$export, $stMessage) {
	export_recordlog('FATAL ERROR: ' . $stMessage, POLLER_VERBOSITY_NONE);

	// insert poller stats into the settings table
	db_execute_prepared('UPDATE graph_exports
		SET last_error = ?, last_ended=NOW(), last_errored=NOW(), status=2
		WHERE id = ?',
		[$stMessage, $export['id']]);

	exit;
}

/**
 * A simple export logging function that indicates a warning condition
 * for developers and users. Called from run_export() when a previous run
 * ended in an unclean state.
 *
 * @arg $stMessage - the message
 *
 * @param string $stMessage The warning message to log.
 *
 * @return void
 */
function export_warn($stMessage) {
	export_recordlog($stMessage, POLLER_VERBOSITY_MEDIUM);
}

/**
 * A simple export logging function, logged at the 'none' verbosity
 * level so it is always recorded. Called throughout this file to record
 * transfer command output for troubleshooting failures.
 *
 * @param string $stMessage The message to log.
 *
 * @return void
 */
function export_note($stMessage) {
	export_recordlog($stMessage, POLLER_VERBOSITY_NONE);
}

/**
 * A simple export logging function that also logs to stdout for
 * developers. Called throughout this file to record notable
 * higher-level progress messages.
 *
 * @arg $stMessage - the debug message.
 *
 * @param string $stMessage The message to log.
 *
 * @return void
 */
function export_log($stMessage) {
	export_recordlog($stMessage, POLLER_VERBOSITY_HIGH);
}

/**
 * A common cli debug level output for developers. Called throughout this
 * file to record verbose diagnostic/progress messages during an export
 * run.
 *
 * @arg $stMessage - the debug message.
 *
 * @param string $stMessage The debug message to log.
 *
 * @return void
 */
function export_debug($stMessage) {
	export_recordlog($stMessage, POLLER_VERBOSITY_DEBUG);
}

/**
 * A common logging function to output to the cacti log file and screen.
 * Default is to use debug mode unless the loglevel is overridden or the
 * global debug flag is set. Called by all of this file's export_fatal/
 * export_warn/export_note/export_log/export_debug wrapper functions to
 * actually perform the logging.
 *
 * @message  - the message to record
 * @loglevel - the cacti loggiing level to use which affects both
 *             screen and log file output
 *
 * @param string $message  The message to record.
 * @param int    $loglevel The Cacti logging verbosity level to use;
 *                         defaults to POLLER_VERBOSITY_DEBUG.
 *
 * @return void
 *
 * @global bool $debug When true, forces the log level to
 *                     POLLER_VERBOSITY_NONE and prepends the current
 *                     memory usage to the message.
 */
function export_recordlog($message, $loglevel = POLLER_VERBOSITY_DEBUG) {
	global $debug;

	if ($debug) {
		$loglevel = POLLER_VERBOSITY_NONE;
		$message  = 'MEMUSE: ' . number_format_i18n(memory_get_usage()) . ', MESSAGE: ' . rtrim($message);
	}

	$message = 'PID: ' . getmypid() . ' ' . rtrim($message);
	cacti_log($message, true, 'EXPORT', $loglevel);
}

/** export_pre_ftp_upload - this function creates a global variable
 * of your pre-checked ftp credentials and settings that will be used
 * for the actual ftp transfer.
 * @param mixed $export
 * @arg $export       - the export item structure */
/**
 * Builds the $aFtpExport connection-settings array (host, remote
 * directory, port, credentials, passive/active mode) used by the
 * PHP-native FTP transfer functions, defaulting to anonymous/active
 * transfer when no username is configured. Calls export_fatal() when
 * the host or remote directory is blank. Called from run_export() before
 * an FTP-based transfer.
 *
 * @param array $export Reference to the graph_exports row supplying the
 *                      FTP connection settings.
 *
 * @return void
 *
 * @global array $config     Cacti global configuration array (declared
 *                           but not directly used here).
 * @global array $aFtpExport Set to the resolved FTP connection settings
 *                           for export_ftp_php_execute() and related
 *                           functions to use.
 */
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

	$aFtpExport['username'] = $export['export_user'];
	$aFtpExport['password'] = $export['export_password'];

	if (empty($aFtpExport['username'])) {
		$aFtpExport['username'] = 'Anonymous';
		$aFtpExport['password'] = '';
		export_log('Using Anonymous transfer method.');
	}

	if ($export['export_passive'] == 'on') {
		$aFtpExport['passive'] = true;
		export_log('Using passive transfer method.');
	} else {
		$aFtpExport['passive'] = false;
		export_log('Using active transfer method.');
	}
}

/**
 * This function is looking for bad export paths that can potentially get
 * the user in trouble. We avoid paths that can get erased by accident,
 * such as being within, equal to, or a parent of the Cacti install's own
 * system directories. Calls export_fatal() (which exits) when the export
 * path is unsafe. Called from export_graphs() before any local export
 * path is created or cleared.
 *
 * @arg $export       - the export item structure
 * @arg $export_path  - the directory holding the export contents.
 *
 * @param array  $export      Reference to the graph_exports row, used
 *                            for fatal-error reporting.
 * @param string $export_path The local export path to validate.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       resolve the web root path to compare against.
 */
function check_cacti_paths(&$export, $export_path) {
	global $config;

	$root_path = $config['base_path'];

	// check for bad directories within the cacti path
	if (strcasecmp($root_path, $export_path) < 0) {
		$cacti_system_paths = [
			'include',
			'lib',
			'install',
			'rra',
			'log',
			'scripts',
			'plugins',
			'images',
			'resource'];

		foreach ($cacti_system_paths as $cacti_system_path) {
			if (substr_count(strtolower($export_path), strtolower($cacti_system_path)) > 0) {
				export_fatal($export, "Export path '" . $export_path . "' is potentially within a Cacti system path '" . $cacti_system_path . "'.  Can not continue.");
			}
		}
	}

	// can not be the web root
	if ((strcasecmp($root_path, $export_path) == 0) &&
		(read_config_option('export_type') == 'local')) {
		export_fatal($export, "Export path '" . $export_path . "' is the Cacti web root.  Can not continue.");
	}

	// can not be a parent of the Cacti web root
	if (strncasecmp($root_path, $export_path, strlen($export_path)) == 0) {
		export_fatal($export, "Export path '" . $export_path . "' is a parent folder from the Cacti web root.  Can not continue.");
	}
}

/**
 * Prevents exporting to well-known operating system directories (e.g.
 * /boot, /etc, Windows/Program Files) by comparing the export path
 * against a denylist. Calls export_fatal() (which exits) when the export
 * path matches a system path. Called from export_graphs() before any
 * local export path is created or cleared.
 *
 * @param array  $export      Reference to the graph_exports row, used
 *                            for fatal-error reporting.
 * @param string $export_path The local export path to validate.
 *
 * @return void
 */
function check_system_paths(&$export, $export_path) {
	// don't allow to export to system paths
	$system_paths = [
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
		'program files'];

	foreach ($system_paths as $system_path) {
		if (substr($system_path, 0, 1) == '/') {
			if ($system_path == substr($export_path, 0, strlen($system_path))) {
				export_fatal($export, "Export path '" . $export_path . "' is within a system path '" . $system_path . "'.  Can not continue.");
			}
		} elseif (substr_count(strtolower($export_path), strtolower($system_path)) > 0) {
			export_fatal($export, "Export path '" . $export_path . "' is within a system path '" . $system_path . "'.  Can not continue.");
		}
	}
}

/**
 * This function exports all the graphs and some html for mgtg view data.
 * These are all the graphs that are in scope for the export be it a tree
 * export, or a site export. Validates/prepares the local export
 * directory, determines the set of graphs allowed for the configured
 * user and tree/site selection, then exports each one (directly, or via
 * the multi-threaded task queue when export_threads > 0). Called from
 * exporter() for every export run.
 *
 * @arg $export       - the export item structure
 * @arg $export_path  - the directory holding the export contents.
 *
 * @param array  $export      Reference to the graph_exports row
 *                            describing what/how to export.
 * @param string $export_path The local directory to write exported
 *                            graphs/HTML into.
 *
 * @return int The number of graphs actually exported.
 *
 * @global array $config Cacti global configuration array (declared but
 *                       not directly used here).
 */
function export_graphs(&$export, $export_path) {
	global $config;

	// check for bad directories
	check_cacti_paths($export, $export_path);
	check_system_paths($export, $export_path);

	if (strlen($export_path) < 3) {
		export_fatal($export, 'Export path is not long enough ! Export can not continue. ');
	}

	// if the path is not a directory, don't continue
	clearstatcache();

	if (!is_dir($export_path)) {
		if (!mkdir($export_path)) {
			export_fatal($export, "Unable to create path '" . $export_path . "'!  Export can not continue.");
		}
	} else {
		if ($export['export_clear'] == 'on') {
			delTree($export_path, true);
		}
	}

	clearstatcache();

	if (!is_dir($export_path . '/graphs')) {
		if (!mkdir($export_path . '/graphs')) {
			export_fatal($export, "Unable to create path '" . $export_path . "/graphs'!  Export can not continue.");
		}
	}

	clearstatcache();

	if (!is_writable($export_path)) {
		export_fatal($export, "Unable to write to path '" . $export_path . "'!  Export can not continue.");
	}

	clearstatcache();

	if (!is_writable($export_path . '/graphs')) {
		export_fatal($export, "Unable to write to path '" . $export_path . "/graphs'!  Export can not continue.");
	}

	// blank paths are not good
	if (strlen($export_path) == 0) {
		export_fatal($export, 'Export path is null!  Export can not continue.');
	}

	export_log('Running graph export');

	$user       = $export['export_effective_user'];
	$trees      = $export['graph_tree'];
	$sites      = $export['graph_site'];
	$export_id  = $export['id'];

	$ntree      = [];
	$graphs     = [];
	$ngraph     = [];

	$limit      = 1000;
	$sql_where  = '';
	$hosts      = '';
	$total_rows = 0;
	$exported   = 0;
	$metadata   = [];

	if ($user == 0) {
		$user = -1;
	}

	export_debug('Export presentation is ' . $export['export_presentation']);

	if ($export['export_presentation'] == 'tree') {
		if ($trees != '0') {
			$sql_where = 'gt.id IN(' . $trees . ')';
		}

		$trees = get_allowed_trees(false, false, $sql_where, 'name', '', $total_rows, $user);
		$trees = is_array($trees) ? $trees : [];

		export_debug('There are ' . cacti_sizeof($trees) . ' trees to export');

		if (cacti_sizeof($trees)) {
			foreach ($trees as $tree) {
				$ntree[] = $tree['id'];

				export_debug('Tree \'' . $tree['name'] . '\' with id \'' . $tree['id'] . '\' is allowed');
			}
		}

		if (cacti_sizeof($ntree)) {
			$graphs = array_rekey(
				db_fetch_assoc('SELECT DISTINCT local_graph_id
					FROM graph_tree_items
					WHERE local_graph_id > 0
					AND graph_tree_id IN(' . implode(', ', $ntree) . ')'),
				'local_graph_id', 'local_graph_id'
			);

			if (cacti_sizeof($graphs)) {
				foreach ($graphs as $local_graph_id) {
					if (is_graph_allowed($local_graph_id, $user)) {
						$ngraph[$local_graph_id] = $local_graph_id;
					}
				}
			}

			export_debug('There are ' . cacti_sizeof($graphs) . ' graphs not in hosts to export for all trees');

			$hosts = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT host_id)
				FROM graph_tree_items
				WHERE graph_tree_id IN(?)',
				[implode(', ', $ntree)]);

			export_debug('There are ' . cacti_sizeof(explode(',',$hosts)) . ' hosts to export for all trees');

			if ($hosts != '') {
				$sql_where = 'gl.host_id IN(' . $hosts . ')';
				$graphs    = get_allowed_graphs($sql_where, 'gtg.title_cache', '', $total_rows, $user);

				if (cacti_sizeof($graphs)) {
					foreach ($graphs as $graph) {
						if (is_graph_allowed($graph['local_graph_id'], $user)) {
							$ngraph[$graph['local_graph_id']] = $graph['local_graph_id'];
						}
					}
				}
			}

			export_debug('There are ' . cacti_sizeof($ngraph) . ' total graphs to export for all trees');
		}
	} else {
		if ($sites != '0' && $sites != '') {
			$hosts = db_fetch_cell('SELECT GROUP_CONCAT(id) FROM host WHERE site_id IN(' . $sites . ')');
		} elseif ($sites == '0') {
			$hosts = db_fetch_cell('SELECT GROUP_CONCAT(id) FROM host WHERE site_id > 0');
		}

		if ($hosts != '') {
			$sql_where = 'gl.host_id IN(' . $hosts . ')';
		}

		$graphs = get_allowed_graphs($sql_where, 'gtg.title_cache', '', $total_rows, $user);

		if (cacti_sizeof($graphs)) {
			foreach ($graphs as $graph) {
				if (is_graph_allowed($graph['local_graph_id'], $user)) {
					$ngraph[$graph['local_graph_id']] = $graph['local_graph_id'];
				}
			}
		}
	}

	if (cacti_sizeof($ngraph)) {
		if ($export['export_threads'] > 0) {
			export_graph_clear_tasks();
		}

		foreach ($ngraph as $local_graph_id) {
			if ($export['export_threads'] > 0) {
				export_graph_prepare_task($export_id, $user, $export_path, $local_graph_id);
			} else {
				export_graph_files($export, $user, $export_path, $local_graph_id);
			}

			$exported++;

			if ($exported >= $export['graph_max'] && $export['graph_max'] > 0) {
				db_execute_prepared('UPDATE graph_exports
					SET last_error="WARNING: Max number of Graphs ' . $export['graph_max'] . ' reached",
					last_errored=NOW()
					WHERE id = ?',
					[$export['id']]);

				break;
			}
		}

		if ($export['export_threads'] > 0) {
			export_graph_monitor_tasks($export);
		}
	}

	return $exported;
}

/**
 * Recursively deletes all files and subdirectories under a directory
 * (following/removing symlinks without traversing them), optionally
 * leaving the top-level directory itself in place. Called from
 * export_graphs() to clear a previous export's contents when the export
 * definition's 'export_clear' option is enabled.
 *
 * @param string $dir  The directory to empty.
 * @param bool   $skip Whether to leave the top-level directory itself in
 *                     place after emptying it; defaults to false
 *                     (removes it too).
 *
 * @return bool|int False if $dir does not resolve to a real path;
 *                  otherwise 0 when $skip is true, or the result of
 *                  rmdir() on the now-empty directory.
 */
function delTree($dir, $skip = false) {
	$dir = realpath($dir);

	if ($dir === false) {
		return false;
	}

	$scan_result = scandir($dir);
	$files       = array_diff(is_array($scan_result) ? $scan_result : [], ['.', '..']);

	foreach ($files as $file) {
		$path = "$dir/$file";

		if (is_link($path)) {
			unlink($path);
		} elseif (is_dir($path)) {
			delTree($path);
		} else {
			unlink($path);
		}
	}

	return ($skip ? 0 : rmdir($dir));
}

/**
 * Checks whether a process id is still alive, without actually sending a
 * signal (signal 0 is a no-op used purely for existence testing). Called
 * from export_graph_monitor_tasks() to detect worker processes that died
 * without updating their task status.
 *
 * @param int $pid The process id to check.
 *
 * @return bool True if the process exists (and is signalable), false
 *              otherwise.
 */
function export_is_task_running($pid) {
	return posix_kill($pid, 0);
}

/**
 * Runs the multi-threaded export supervisor loop: periodically checks
 * the graph_exports_tasks queue, marks stalled tasks as failed, detects
 * worker processes that died without updating their status, and spawns
 * new background poller_export.php worker processes (up to the export's
 * configured thread count) until every queued task has completed.
 * Called from export_graphs() after all tasks have been queued, when the
 * export definition's 'export_threads' setting is greater than 0.
 *
 * @param array $export The graph_exports row; used for its
 *                      'export_threads' concurrency limit.
 *
 * @return void Blocks (looping with a 2-second sleep) until all queued
 *              tasks reach a terminal status.
 *
 * @global bool $debug Reserved/declared for parity with other export
 *                     functions; not used directly here.
 */
function export_graph_monitor_tasks($export) {
	global $debug;

	$max_threads = $export['export_threads'];
	$script_file = __DIR__ . '/poller_export.php';
	$script_php  = read_config_option('path_php_binary');

	$spawn_time = new DateTime();

	while (true) {
		$expire_time = new DateTime();
		$expire_time->modify('-30 seconds');

		$pids_left = db_fetch_cell('SELECT COUNT(*)
			FROM graph_exports_tasks
			WHERE status IN (0,1)');

		// printf("%s: Found %s pids, spawn %s, expire %s\n", (new DateTime())->format('H:i:s'), $pids_left, $spawn_time->format('H:i:s'), $expire_time->format('H:i:s'));
		if ($pids_left == 0) {
			break;
		}

		if ($spawn_time < $expire_time) {
			// printf("%s: Running failure checks\n", (new DateTime())->format('H:i:s'));
			$pids_fail = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM graph_exports_tasks
				WHERE status < 2
				AND start_time != 0
				AND start_time < ?',
				[$expire_time->getTimestamp()]);

			if ($pids_fail > 0) {
				db_execute_prepared('UPDATE graph_exports_tasks
					SET status = 3
					WHERE status = 1
					AND start_time != 0
					AND start_time < ?',
					[$expire_time->getTimestamp()]);
			}
		}

		$pids_running = array_rekey(
			db_fetch_assoc('SELECT DISTINCT id, pid
				FROM graph_exports_tasks
				WHERE status = 1'),
			'id', 'pid'
		);

		$thread_run = cacti_sizeof($pids_running);
		$thread_adj = $thread_run;

		if ($thread_adj > 0 && $spawn_time < $expire_time) {
			// printf("%s: Running adjustments checks\n", (new DateTime())->format('H:i:s'));
			foreach ($pids_running as $id => $pid) {
				if ($pid != 0 && !export_is_task_running($pid)) {
					export_debug('TASKS Pid ' . $pid . ' is not running, adjusting');
					db_execute_prepared('UPDATE graph_exports_tasks
						SET status = 4
						WHERE status = 1
						AND id = ?
						AND pid = ?',
						[$id, $pid]);
					$thread_adj--;
				}
			}
		}
		export_debug('TASKS ' . $thread_run . ' Database, ' . $thread_adj . ' Active');

		if ($thread_adj < $max_threads) {
			$wanted_count = $max_threads - $thread_adj;
			// printf("%s: Spawning threads %s of %s\n", (new DateTime())->format('H:i:s'), $wanted_count, $max_threads);

			$tasks = db_fetch_assoc('SELECT DISTINCT *
				FROM graph_exports_tasks
				WHERE status = 0
				LIMIT ' . $wanted_count);

			export_debug('TASKS ' . $wanted_count . ' available, ' . cacti_sizeof($tasks) . ' found');

			if (cacti_sizeof($tasks) > 0) {
				$spawn_time = new DateTime();

				foreach ($tasks as $task) {
					db_execute_prepared('UPDATE graph_exports_tasks
						SET status = 1, start_time = ?
						WHERE id = ?',
						[time(), $task['id']]);

					export_debug('TASKS Spawning task ' . $task['id'] . ' for Export[' . $task['export_id'] . '], Graph[' . $task['local_graph_id'] . ']');
					exec_background($script_php, '-q ' . $script_file . ' --thread=' . $task['id']);
				}
			}
		}

		sleep(2);
	}
}

/**
 * Empties the graph_exports_tasks queue table. Called from
 * export_graphs() before queuing a new batch of per-graph export tasks,
 * when multi-threaded exporting is enabled.
 *
 * @return void
 */
function export_graph_clear_tasks() {
	db_execute('TRUNCATE TABLE graph_exports_tasks');
}

/**
 * Queues a single graph's export as a pending row in graph_exports_tasks
 * for a background worker to later pick up and process. Called from
 * export_graphs() for each allowed graph, when multi-threaded exporting
 * is enabled.
 *
 * @param int    $export_id      The graph_exports.id this task belongs
 *                               to.
 * @param int    $user           The effective user id to export the
 *                               graph as (permissions context).
 * @param string $folder         The local export directory the worker
 *                               should write the graph's files into.
 * @param int    $local_graph_id The graph_local.id to export.
 *
 * @return void
 */
function export_graph_prepare_task($export_id, $user, $folder, $local_graph_id) {
	export_debug('TASKS Preparing task for Export[' . $export_id . '], Graph[' . $local_graph_id . ']');

	// MJV: Do something here
	db_execute_prepared('INSERT INTO graph_exports_tasks (export_id, local_graph_id, user, folder)
		VALUES (?, ?, ?, ?)', [$export_id, $local_graph_id, $user, $folder]);
}

/**
 * Executes a single queued export task: looks up its graph_exports_tasks
 * and parent graph_exports rows, records this worker process's pid,
 * exports the graph's files, and marks the task complete. Invoked by a
 * background poller_export.php worker process started (via
 * export_graph_monitor_tasks()) with a '--thread=<task_id>' argument.
 *
 * @param int $task_id The graph_exports_tasks.id to execute.
 *
 * @return void
 */
function export_graph_start_task($task_id) {
	$start = microtime(true);

	$exports = 0;

	$task = db_fetch_row_prepared('SELECT * FROM graph_exports_tasks
		WHERE id = ?',
		[$task_id]);

	if (!is_array($task) || !cacti_sizeof($task)) {
		export_warn('TASKS Launched ' . $task_id . ' - Invalid ID, Aborting');
	} else {
		db_execute_prepared('UPDATE graph_exports_tasks
			SET pid = ?
			WHERE id = ?',
			[getmypid(), $task['id']]);

		export_debug('TASKS Launched ' . $task['id'] . ', exporting graph ' . $task['local_graph_id'] . ' as user \'' . $task['user'] . '\'');

		$export = db_fetch_row_prepared('SELECT * FROM graph_exports
			WHERE id = ?',
			[$task['export_id']]);
		$export = is_array($export) ? $export : [];

		if (empty($export)) {
			export_warn('TASKS Launched ' . $task['id'] . ' - Export[' . $task['export_id'] . '] no longer exists, Aborting');
		} else {
			$exports = export_graph_files($export, $task['user'], $task['folder'], $task['local_graph_id']);
		}

		db_execute_prepared('UPDATE graph_exports_tasks
			SET status = 2
			WHERE id = ?',
			[$task['id']]);
	}
	$end = microtime(true);

	$export_stats = sprintf('Time:%01.2f Exports:%s', $end - $start, $exports);

	export_debug('THREAD STATS: ' . $export_stats);
}

/**
 * This function exports the actual files for a given graph: the graph's
 * PNG image (and optional thumbnail), and an HTML wrapper page linking
 * to it, applying the export definition's sizing/theme/thumbnail
 * settings. Called from export_graphs() (directly, in single-threaded
 * mode) or export_graph_start_task() (for a queued multi-threaded task)
 * for each graph in scope for the export.
 *
 * @arg $export       - the export item structure
 * @arg $export_path  - the directory holding the export contents.
 *
 * @param array  $export         The graph_exports row describing the
 *                               export's graph sizing/theme/thumbnail
 *                               settings.
 * @param int    $user           The effective user id to export the
 *                               graph as (permissions context).
 * @param string $export_path    The local directory to write the
 *                               graph's PNG/HTML files into.
 * @param int    $local_graph_id The graph_local.id to export.
 *
 * @return int The number of graph files successfully exported (0 or 1).
 */
function export_graph_files($export, $user, $export_path, $local_graph_id) {
	if ($user == 0) {
		$user = -1;
	}

	// open a pipe to rrdtool for writing
	$rrdtool_pipe = rrd_init();

	export_debug('Exporting Graph ID: ' . $local_graph_id);

	check_remove($export_path . '/graph_' . $local_graph_id . '.html');

	if ($export['export_thumbs'] == 'on') {
		// settings for preview graphs
		$graph_data_array['export_filename'] = $export_path . '/graphs/thumb_' . $local_graph_id . '.png';

		$graph_data_array['graph_height']    = $export['graph_height'];
		$graph_data_array['graph_width']     = $export['graph_width'];
		$graph_data_array['graph_nolegend']  = true;
		$graph_data_array['export']          = true;
		$graph_data_array['graph_theme']     = $export['export_theme'];
		$graph_data_array['image_format']    = 'png';

		export_log("Creating Graph Thumbnail '" . $graph_data_array['export_filename'] . "'");
		check_remove($graph_data_array['export_filename']);

		rrdtool_function_graph($local_graph_id, 0, $graph_data_array, $rrdtool_pipe, $metadata, $user);
		unset($graph_data_array);
	}

	// settings for preview graphs
	$graph_data_array['export_filename'] = $export_path . '/graphs/graph_' . $local_graph_id . '.png';
	$graph_data_array['export']          = true;
	$graph_data_array['graph_theme']     = $export['export_theme'];
	$graph_data_array['image_format']    = 'png';

	export_log("Creating Graph '" . $graph_data_array['export_filename'] . "'");
	check_remove($graph_data_array['export_filename']);

	rrdtool_function_graph($local_graph_id, 0, $graph_data_array, $rrdtool_pipe, $metadata, $user);
	unset($graph_data_array);

	// generate html files for each graph
	export_log("Creating File  '" . $export_path . '/graph_' . $local_graph_id . '.html');

	$fp_graph_index = fopen($export_path . '/graph_' . $local_graph_id . '.html', 'w');

	if (is_resource($fp_graph_index)) {
		fwrite($fp_graph_index, '<table class="center"><tr><td class="center"><strong>Graph - ' . get_graph_title($local_graph_id) . '</strong></td></tr>');

		$rras = get_associated_rras($local_graph_id, ' AND dspr.id IS NOT NULL');

		// generate graphs for each rra
		if (cacti_sizeof($rras)) {
			foreach ($rras as $rra) {
				$graph_data_array['export_filename'] = $export_path . '/graphs/graph_' . $local_graph_id . '_' . $rra['id'] . '.png';
				$graph_data_array['export']          = true;
				$graph_data_array['graph_end']       = time() - read_config_option('poller_interval');
				$graph_data_array['graph_theme']     = $export['export_theme'];
				$graph_data_array['image_format']    = 'png';

				if (!empty($rra['timespan'])) {
					$graph_data_array['graph_start']  = $graph_data_array['graph_end'] - $rra['timespan'];
				} else {
					$graph_data_array['graph_start']     = $graph_data_array['graph_end'] - ($rra['rows'] * $rra['step'] * $rra['steps']);
				}

				check_remove($graph_data_array['export_filename']);

				export_log("Creating Graph '" . $graph_data_array['export_filename'] . "'");

				rrdtool_function_graph($local_graph_id, $rra['id'], $graph_data_array, $rrdtool_pipe, $metadata, $user);
				unset($graph_data_array);

				fwrite($fp_graph_index, "<tr><td class='center'><div><img src='graphs/graph_" . $local_graph_id . '_' . $rra['id'] . ".png'></div></td></tr>\n");
				fwrite($fp_graph_index, "<tr><td class='center'><div><strong>" . $rra['name'] . '</strong></div></td></tr>');
			}

			fwrite($fp_graph_index, '</table>');
			fclose($fp_graph_index);
		}
	} else {
		export_warn('Unable to write to file ' . $export_path . '/graph_' . $local_graph_id . '.html');
	}

	// close the rrdtool pipe
	rrd_close($rrdtool_pipe);

	return isset($rras) ? cacti_sizeof($rras) : 0;
}

/** export_ftp_php_execute - this function creates the ftp connection object,
 * optionally sanitizes the destination and then calls the function to copy
 * data to the remote host.
 * @arg $export       - the export item structure
 * @arg $stExportDir  - the temporary data holding the staged export contents.
 * @param mixed $export
 * @param mixed $stExportDir
 * @param mixed $stFtpType
 * @arg $stFtpType    - the type of ftp transfer, secure or unsecure. */
/**
 * Connects to the remote FTP/SFTP server using the PHP FTP extension,
 * logs in, sets active/passive mode, changes into the remote directory,
 * optionally sanitizes (deletes) its existing contents, uploads the
 * local export directory's contents, then closes the connection. Calls
 * export_fatal() (which exits) on any connection/login/directory
 * failure. Called from run_export() for exports of type 'ftp' or
 * 'sftp'.
 *
 * @param array  $export      Reference to the graph_exports row; used
 *                            for its remote-sanitize option.
 * @param string $stExportDir The local temp directory whose contents are
 *                            uploaded.
 * @param string $stFtpType   Either 'ftp' or 'sftp', selecting the PHP
 *                            connection function to use; defaults to
 *                            'ftp'.
 *
 * @return void
 *
 * @global array $aFtpExport The FTP connection settings prepared by
 *                           export_pre_ftp_upload().
 */
function export_ftp_php_execute(&$export, $stExportDir, $stFtpType = 'ftp') {
	global $aFtpExport;

	// Turn off warnings
	error_reporting(0);

	// connect to foreign system
	switch($stFtpType) {
		case 'ftp':
			$oFtpConnection = ftp_connect($aFtpExport['server'], $aFtpExport['port']);

			if (!$oFtpConnection) {
				export_fatal($export, 'FTP Connection failed! Check hostname and port.  Export can not continue.');
			} else {
				export_log('Connection to remote server was successful.');
			}

			break;
		case 'sftp':
			$oFtpConnection = ftp_ssl_connect($aFtpExport['server'], $aFtpExport['port']);

			if (!$oFtpConnection) {
				export_fatal($export, 'SFTP Connection failed! Check hostname and port.  Export can not continue.');
			} else {
				export_log('Connection to remote server was successful.');
			}

			break;
		default:
			export_fatal($export, "FTP type '" . $stFtpType . "' is not supported.  Export can not continue.");
	}

	// login to foreign system
	if (!ftp_login($oFtpConnection, $aFtpExport['username'], $aFtpExport['password'])) {
		ftp_close($oFtpConnection);
		export_fatal($export, 'FTP Login failed! Check username and password.  Export can not continue.');
	} else {
		export_log('Remote login was successful.');
	}

	// set connection type
	if ($aFtpExport['passive']) {
		ftp_pasv($oFtpConnection, true);
	} else {
		ftp_pasv($oFtpConnection, false);
	}

	// change directories into the remote upload directory
	if (!@ftp_chdir($oFtpConnection, $aFtpExport['remotedir'])) {
		ftp_close($oFtpConnection);
		export_fatal($export, "FTP Remote directory '" . $aFtpExport['remotedir'] . "' does not exist!.  Export can not continue.");
	}

	// sanitize the remote location if the user has asked so
	if ($export['export_sanitize_remote'] == 'on') {
		export_log('Deleting remote files.');

		// get rid of the files first
		$aFtpRemoteFiles = ftp_nlist($oFtpConnection, $aFtpExport['remotedir']);

		if (is_array($aFtpRemoteFiles)) {
			foreach ($aFtpRemoteFiles as $stFile) {
				export_log("Removing recursively remote file/directory '" . $stFile . "'");
				export_ftp_rmdirr($oFtpConnection, $stFile);
			}
		}

		$aFtpRemoteFiles = ftp_nlist($oFtpConnection, $aFtpExport['remotedir']);

		if (cacti_sizeof($aFtpRemoteFiles) > 0) {
			ftp_close($oFtpConnection);
			export_fatal($export, 'Problem sanitizing remote ftp location, must exit.');
		}
	}

	// upload files to remote system
	export_log('Uploading files to remote location.');
	ftp_chdir($oFtpConnection, $aFtpExport['remotedir']);
	export_ftp_php_uploaddir($stExportDir,$oFtpConnection);

	// end connection
	export_log('Closing ftp connection.');
	ftp_close($oFtpConnection);
}

/**
 * Recursively removes a remote file or directory over an existing FTP
 * connection: attempts a direct delete first, and if that fails (because
 * it is a non-empty directory), lists and removes its contents before
 * removing the directory itself. Called from export_ftp_php_execute()
 * when sanitizing the remote destination before upload.
 *
 * @param \FTP\Connection $handle The active FTP connection resource.
 * @param string          $path   The remote file or directory path to remove.
 *
 * @return bool True if the path was successfully removed, false
 *              otherwise.
 */
function export_ftp_rmdirr($handle, $path) {
	// Remove the cacti error handler
	restore_error_handler();

	if (!@ftp_delete($handle, $path)) {
		$list = @ftp_nlist($handle, $path);
		$list = is_array($list) ? $list : [];

		if (cacti_sizeof($list)) {
			foreach ($list as $value) {
				export_ftp_rmdirr($handle, $value);
			}
		}
	}

	$result = @ftp_rmdir($handle, $path) ? true : false;

	// Restore the cacti error handler
	set_error_handler('CactiErrorHandler');

	return $result;
}

/**
 * This function performs the transfer of the exported data to the remote
 * host, recursing into subdirectories and re-creating them remotely as
 * needed. Called from export_ftp_php_execute() to upload the local
 * export directory's contents over an existing FTP connection.
 *
 * @arg $dir - the directory to transfer to the remote host.
 * @arge $oFtpConnection - the ftp connection object created previously.
 *
 * @param string          $dir            The local directory to upload.
 * @param \FTP\Connection $oFtpConnection The active FTP connection resource.
 *
 * @return void
 *
 * @global array $aFtpExport The FTP connection settings (declared but
 *                           not directly used here).
 */
function export_ftp_php_uploaddir($dir, $oFtpConnection) {
	global $aFtpExport;

	export_log("Uploading directory: '$dir' to remote location.");

	if ($dh = opendir($dir)) {
		export_log('Uploading files to remote location.');

		while (($file = readdir($dh)) !== false) {
			$filePath = $dir . '/' . $file;

			if ($file != '.' && $file != '..' && !is_dir($filePath)) {
				if (!ftp_put($oFtpConnection, $file, $filePath, FTP_BINARY)) {
					export_log("Failed to upload '$file'.");
				}
			}

			if (($file != '.') &&
				($file != '..') &&
				(is_dir($filePath))) {
				export_log("Create remote directory: '$file'.");
				@ftp_mkdir($oFtpConnection,$file);

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

/**
 * This function performs the transfer of the exported data to the remote
 * host, using the external ncftpput command-line utility instead of
 * PHP's FTP extension. Called from run_export() for exports of type
 * 'ftp_nc'.
 *
 * @arg $stExportDir - the directory to transfer to the remote host.
 *
 * @param string $stExportDir The local directory to transfer.
 *
 * @return void
 *
 * @global array $aFtpExport The FTP connection settings prepared by
 *                           export_pre_ftp_upload().
 */
function export_ftp_ncftpput_execute($stExportDir) {
	global $aFtpExport;

	$stExportDir = realpath($stExportDir);

	if ($stExportDir === false) {
		cacti_log('ERROR: Export directory does not exist for ncftpput', true, 'EXPORT');

		return;
	}

	chdir($stExportDir);

	// set the initial command structure
	$stExecute = 'ncftpput -R -V -r 1 -u ' . cacti_escapeshellarg($aFtpExport['username']) . ' -p ' . cacti_escapeshellarg($aFtpExport['password']);

	// if the user requested passive mode, use it
	if ($aFtpExport['passive']) {
		$stExecute .= ' -F ';
	}

	// setup the port, server, remote directory and all files
	$stExecute .= ' -P ' . cacti_escapeshellarg($aFtpExport['port']) . ' ' . cacti_escapeshellarg($aFtpExport['server']) . ' ' . cacti_escapeshellarg($aFtpExport['remotedir']) . '.';

	// run the command
	$iExecuteReturns = 0;
	system($stExecute, $iExecuteReturns);

	$aNcftpputStatusCodes = [
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
		'Session initialization failed.'];

	export_log('Ncftpput returned: ' . $aNcftpputStatusCodes[$iExecuteReturns]);
}

/**
 * This function clean's up the local temporary directory after the data
 * transfer has completed, recursively deleting its files and
 * subdirectories but preserving the configured temp-directory root
 * itself. Called from run_export() after an FTP/ncftpput/rsync transfer
 * completes.
 *
 * @arg $export - the export structure
 * @arg $stExportDir  - the temporary directory where files were staged.
 *
 * @param array  $export      Reference to the graph_exports row; used to
 *                            check its configured temp directory so it
 *                            is not itself removed.
 * @param string $stExportDir The temporary directory to clean up.
 *
 * @return void
 */
function export_post_ftp_upload(&$export, $stExportDir) {
	// clean-up after ftp-put
	if ($dh = opendir($stExportDir)) {
		while (($file = readdir($dh)) !== false) {
			$filePath = $stExportDir . '/' . $file;

			if ($file != '.' && $file != '..' && !is_dir($filePath)) {
				export_log("Removing Local File '" . $file . "'");
				unlink($filePath);
			}

			// if the directory turns out to be a sub-directory, delete it too
			if ($file != '.' && $file != '..' && is_dir($filePath)) {
				export_log("Removing Local Directory '" . $filePath . "'");
				export_post_ftp_upload($export, $filePath);
			}
		}
		closedir($dh);

		// don't delete the root of the temporary export directory
		if ($export['export_temp_directory'] != $stExportDir) {
			rmdir($stExportDir);
		}
	}
}

/**
 * This function writes a json array of all graphs that lie on a branch
 * within a tree (or the equivalent grouping for a site/host/data-query
 * branch type), to a per-branch .json file used by the exported jstree
 * navigation to lazily load each branch's graph list. Memoizes already-
 * written files (via a static array) so repeated calls for the same
 * branch are a no-op. Called from create_export_directory_structure()
 * while walking a tree's or site's branches during export.
 *
 * @arg $tree_site_id - the tree or site id of the branch
 * @arg $branch_id    - the branch id of the branch
 * @arg $type         - the type of conf file including tree, branch, host, host_gt, host_dq, and host_dqi
 * @arg $host_id      - the host id of any host level tree objects
 * @arg $sub_id       - the sub id of the object passed.  This is either a numeric data point, or a hybrid
 *                      the case of the host_dqi object.
 * @arg $user         - the effective user to use for export, -1 indicates no permission check
 * @arg $export_path  - the location to store the json array configuration file
 *
 * @param int    $tree_site_id The tree or site id of the branch.
 * @param int    $branch_id    The branch id of the branch (used for the
 *                             'branch' type).
 * @param string $type         The type of conf file: 'branch',
 *                             'gtbranch', 'dqbranch', 'site',
 *                             'site_dt', 'site_gt', 'site_dq',
 *                             'site_dqi', 'host', 'host_gt', 'host_dq',
 *                             or 'host_dqi'.
 * @param int    $host_id      The host id, for host-level tree object
 *                             types.
 * @param mixed  $sub_id       An additional sub-identifier whose meaning
 *                             depends on $type (a data query id,
 *                             graph template id, or a
 *                             'dq:index:values' composite string for
 *                             the *_dqi types).
 * @param int    $user         The effective user id for permission
 *                             checks; -1 indicates no permission check.
 * @param string $export_path  The directory to write the resulting
 *                             .json configuration file into.
 *
 * @return int|void The number of graphs written to the json file; returns
 *                  early with no value if the file was already written
 *                  for this branch in this run, or 0 if $export_path
 *                  does not resolve to a real path.
 */
function write_branch_conf($tree_site_id, $branch_id, $type, $host_id, $sub_id, $user, $export_path) {
	static $json_files = [];
	$total_rows        = 0;
	$graph_array       = [];

	$export_path   = realpath($export_path);

	if ($export_path === false) {
		return 0;
	}
	$tree_site_id  = (int) $tree_site_id;
	$branch_id     = (int) $branch_id;
	$host_id       = (int) $host_id;

	if ($type == 'branch') {
		$json_file = $export_path . '/tree_' . $tree_site_id . '_branch_' . $branch_id . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$graphs = db_fetch_assoc_prepared('SELECT DISTINCT local_graph_id
			FROM graph_tree_items
			WHERE graph_tree_id = ?
			AND parent = ?
			AND local_graph_id > 0
			ORDER BY position', [$tree_site_id, $branch_id]);
	} elseif ($type == 'gtbranch') {
		$json_file = $export_path . '/site_' . $tree_site_id . '_gtbranch_0.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$graphs = [];
	} elseif ($type == 'dqbranch') {
		$json_file = $export_path . '/site_' . $tree_site_id . '_gtbranch_0.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$graphs = [];
	} elseif ($type == 'site') {
		$json_file = $export_path . '/site_' . $tree_site_id . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$graphs = [];
	} elseif ($type == 'site_dt') {
		$json_file = $export_path . '/site_' . $tree_site_id . '_dt_' . $sub_id . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$graphs = [];
	} elseif ($type == 'site_gt') {
		$json_file = $export_path . '/site_' . $tree_site_id . '_gt_' . $sub_id . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$devices = array_rekey(db_fetch_assoc_prepared('SELECT id FROM host WHERE site_id = ?', [$tree_site_id]), 'id', 'id');

		if (empty($devices)) {
			$graphs = [];
		} else {
			$graphs = get_allowed_graphs('(gl.host_id IN(' . implode(',', $devices) . ') AND gt.id = ' . $sub_id . ')', 'gtg.title_cache', '', $total_rows, $user);
		}
	} elseif ($type == 'site_dq') {
		$json_file = $export_path . '/site_' . $tree_site_id . '_dq_' . $sub_id . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$devices = array_rekey(db_fetch_assoc_prepared('SELECT id FROM host WHERE site_id = ?', [$tree_site_id]), 'id', 'id');

		if (empty($devices)) {
			$graphs = [];
		} else {
			$graphs = get_allowed_graphs('(gl.host_id IN(' . implode(',', $devices) . ') AND gl.snmp_query_id = ' . $sub_id . ')', 'gtg.title_cache', '', $total_rows, $user);
		}
	} elseif ($type == 'site_dqi') {
		$parts     = explode(':', $sub_id);
		$dq        = $parts[0];
		$index     = $parts[1];
		$values    = json_decode($parts[2]);
		$nindex    = clean_up_name($parts[1]);
		$json_file = $export_path . '/site_' . $tree_site_id . '_dq_' . $dq . '_dqi_' . $nindex . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$devices = array_rekey(db_fetch_assoc_prepared('SELECT id FROM host WHERE site_id = ?', [$tree_site_id]), 'id', 'id');

		$sql_where = '';

		if (is_array($values) && cacti_sizeof($values)) {
			foreach ($values as $value) {
				// host_id | snmp_index
				$parts = explode('|', $value);
				$sql_where .= ($sql_where != '' ? ' OR ' : ' AND (') . '(host_id = ' . $parts[0] . ' AND snmp_index = ' . db_qstr($parts[1]) . ')';
			}
			$sql_where .= ')';
		}

		if (empty($devices)) {
			$graphs = [];
		} else {
			$graphs = get_allowed_graphs('(gl.host_id IN(' . implode(',', $devices) . ') AND gl.snmp_query_id=' . $dq . $sql_where . ')', 'gtg.title_cache', '', $total_rows, $user);
		}
	} elseif ($type == 'host') {
		$json_file = $export_path . '/host_' . $host_id . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$graphs = get_allowed_graphs('gl.host_id=' . $host_id, 'gtg.title_cache', '', $total_rows, $user);
	} elseif ($type == 'host_gt') {
		$json_file = $export_path . '/host_' . $host_id . '_gt_' . $sub_id . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$graphs = get_allowed_graphs('gl.host_id=' . $host_id . ' AND gl.graph_template_id=' . $sub_id, 'gtg.title_cache', '', $total_rows, $user);
	} elseif ($type == 'host_dq') {
		$json_file = $export_path . '/host_' . $host_id . '_dq_' . $sub_id . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$graphs = get_allowed_graphs('gl.host_id=' . $host_id . ' AND gl.snmp_query_id=' . $sub_id, 'gtg.title_cache', '', $total_rows, $user);
	} elseif ($type == 'host_dqi') {
		$parts     = explode(':', $sub_id);
		$dq        = $parts[0];
		$index     = clean_up_name($parts[1]);
		$json_file = $export_path . '/host_' . $host_id . '_dq_' . $dq . '_dqi_' . $index . '.json';

		if (isset($json_files[$json_file])) {
			return;
		}

		$graphs = get_allowed_graphs('gl.host_id=' . $host_id . ' AND gl.snmp_query_id=' . $dq . ' AND gl.snmp_index=' . db_qstr($parts[1]), 'gtg.title_cache', '', $total_rows, $user);
	} else {
		// Unrecognized $type; nothing to write.
		return 0;
	}

	$fp = fopen($json_file, 'w');

	if (is_resource($fp)) {
		if (cacti_sizeof($graphs)) {
			foreach ($graphs as $graph) {
				if ($host_id == 0) {
					if (is_graph_allowed($graph['local_graph_id'], $user)) {
						$graph_array[] = $graph['local_graph_id'];
					}
				} else {
					$graph_array[] = $graph['local_graph_id'];
				}
			}
		}

		fwrite($fp, json_encode($graph_array) . "\n");
		fclose($fp);
	} else {
		cacti_log('Unable to open ' . $json_file);
	}

	$json_files[$json_file] = true;

	return cacti_sizeof($graph_array);
}

/**
 * Creates jstree compatible static tree html. This is a set of unsorted
 * lists that jstree can properly parse into a tree object. Note that
 * this is a reentrant/recursive function that will call itself for each
 * child branch/host. Called from tree_site_export() for each tree in
 * scope for the export, and recursively by itself while descending the
 * tree structure.
 *
 * @arg $export_path  - the location to write the resulting index.html file
 * @arg $tree         - the tree array including information about the tree
 * @arg $parent       - the parent of any branch to be searched
 * @arg $expand_hosts - the setting of expand hosts for the export
 * @arg $user         - the effective user to use for permission checks.  -1 indicated
 *                      no permission check.
 * @arg $jstree       - the html of the jstree compatible unsorted list
 *
 * @param string $export_path  The directory to write the resulting
 *                             index.html file into.
 * @param array  $tree         The tree array including information
 *                             about the tree.
 * @param int    $parent       The parent branch id to search under (0
 *                             for the tree's top level).
 * @param bool   $expand_hosts The export's 'export_expand_hosts'
 *                             setting, controlling whether hosts under
 *                             a branch are expanded inline.
 * @param int    $user         The effective user id for permission
 *                             checks; -1 indicates no permission check.
 * @param string $jstree       The accumulated html of the jstree
 *                             compatible unsorted list built up across
 *                             recursive calls.
 *
 * @return string The jstree compatible unsorted-list html fragment for
 *                this branch (and, at the top-level call, written to
 *                $export_path's index.html).
 */
function export_generate_tree_html($export_path, $tree, $parent, $expand_hosts, $user, $jstree) {
	static $depth = 5;

	$total_rows = 0;

	$jstree .= str_repeat("\t", $depth) . "<ul>\n";

	$depth++;

	write_branch_conf($tree['id'], $parent, 'branch', 0, 0, $user, $export_path);

	$branches = db_fetch_assoc_prepared('SELECT id, title
		FROM graph_tree_items
		WHERE local_graph_id = 0
		AND host_id = 0
		AND graph_tree_id = ?
		AND parent = ?',
		[$tree['id'], $parent]);

	if (cacti_sizeof($branches)) {
		foreach ($branches as $branch) {
			write_branch_conf($tree['id'], $branch['id'], 'branch', 0, 0, $user, $export_path);

			$has_children = db_fetch_cell_prepared('SELECT count(*)
				FROM graph_tree_items
				WHERE graph_tree_id = ?
				AND local_graph_id = 0
				AND parent = ?',
				[$tree['id'], $branch['id']]);

			$jstree .= str_repeat("\t", $depth) . '<li id="tree_' . $tree['id'] . '_branch_' . $branch['id'] . '">' . $branch['title'];

			if ($has_children) {
				$depth++;
				$jstree .= "\n";

				$jstree = export_generate_tree_html($export_path, $tree, $branch['id'], $expand_hosts, $user, $jstree);

				$depth--;
				$jstree .= str_repeat("\t", $depth) . "</li>\n";
			} else {
				$jstree .= "</li>\n";
			}
		}
	}

	$hosts = db_fetch_assoc_prepared('SELECT DISTINCT host_id, host_grouping_type
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND host_id > 0
		ORDER BY position', [$tree['id'], $parent]);

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			if (is_device_allowed($host['host_id'], $user)) {
				write_branch_conf($tree['id'], $parent, 'host', $host['host_id'], 0, $user, $export_path);

				$host_description = get_host_description($host['host_id']);

				$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . "' data-jstree='{ \"type\" : \"device\" }'>" . $host_description . "\n";

				$depth++;

				if ($expand_hosts == 'on') {
					$templates = get_allowed_graph_templates('gl.host_id =' . $host['host_id'], 'name', '', $total_rows, $user);
					$count     = 0;

					if (cacti_sizeof($templates)) {
						if ($host['host_grouping_type'] == 1) {
							foreach ($templates as $template) {
								$total_rows = write_branch_conf($tree['id'], $parent, 'host_gt', $host['host_id'], $template['id'], $user, $export_path);

								if ($total_rows) {
									if ($count == 0) {
										$jstree .= str_repeat("\t", $depth) . "<ul>\n";

										$depth++;
									}
									$count++;

									$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . '_gt_' . $template['id'] . "' data-jstree='{ \"type\" : \"graph_template\" }'>" . $template['name'] . "</li>\n";
								}
							}

							if ($count) {
								$depth--;
								$jstree .= str_repeat("\t", $depth) . "</ul>\n";
								$depth--;
								$jstree .= str_repeat("\t", $depth) . "</li>\n";
							}
						} else {
							$data_queries = db_fetch_assoc_prepared('SELECT sq.id, sq.name
								FROM snmp_query AS sq
								INNER JOIN host_snmp_query AS hsq
								ON sq.id=hsq.snmp_query_id
								WHERE hsq.host_id = ?',
								[$host['host_id']]);

							$data_queries[] = ['id' => '0', 'name' => __('Non Query Based', 'gexport')];

							foreach ($data_queries as $query) {
								$total_rows = write_branch_conf($tree['id'], $parent, 'host_dq', $host['host_id'], $query['id'], $user, $export_path);

								if ($total_rows && $query['id'] > 0) {
									if ($count == 0) {
										$jstree .= str_repeat("\t", $depth) . "<ul>\n";
										$depth++;
									}
									$count++;

									$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . '_dq_' . $query['id'] . "' data-jstree='{ \"type\" : \"data_query\" }'>" . $query['name'] . "\n";

									$depth++;
									$jstree .= str_repeat("\t", $depth) . "<ul>\n";

									$depth++;

									$graphs = db_fetch_assoc_prepared('SELECT gl.*
										FROM graph_local AS gl
										WHERE host_id = ?
										AND snmp_query_id = ?',
										[$host['host_id'], $query['id']]);

									$dqi = [];

									foreach ($graphs as $graph) {
										$dqi[$graph['snmp_index']] = $graph['snmp_index'];
									}

									// fetch a list of field names that are sorted by the preferred sort field
									$sort_field_data = get_formatted_data_query_indexes($host['host_id'], $query['id']);

									foreach ($dqi as $i) {
										$total_rows = write_branch_conf($tree['id'], $parent, 'host_dqi', $host['host_id'], $query['id'] . ':' . $i, $user, $export_path);

										if ($total_rows) {
											if (isset($sort_field_data[$i])) {
												$title = $sort_field_data[$i];
											} else {
												$title = $i;
											}
											$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . '_dq_' . $query['id'] . '_dqi_' . clean_up_name($i) . "' data-jstree='{ \"type\" : \"graph\" }'>" . $title . "</li>\n";
										}
									}

									$depth--;
									$jstree .= str_repeat("\t", $depth) . "</ul>\n";
									$depth--;
									$jstree .= str_repeat("\t", $depth) . "</li>\n";
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

/**
 * Creates jstree compatible static site html. This is a set of unsorted
 * lists that jstree can properly parse into a tree object. Note that
 * this is a reentrant/recursive function that will call itself for each
 * device-template/data-query branch and host. Called from
 * tree_site_export() for each site in scope for the export, and
 * recursively by itself while descending the site's structure.
 *
 * @arg $export_path  - the location to write the resulting index.html file
 * @arg $site         - the site array including information about the site
 * @arg $parent       - the parent of any branch to be searched
 * @arg $expand_hosts - the setting of expand hosts for the export
 * @arg $user         - the effective user to use for permission checks.  -1 indicated
 *                      no permission check.
 * @arg $jstree       - the html of the jstree compatible unsorted list
 *
 * @param string $export_path  The directory to write the resulting
 *                             index.html file into.
 * @param array  $site         The site array including information
 *                             about the site.
 * @param int    $parent       The parent branch id to search under.
 * @param bool   $expand_hosts The export's 'export_expand_hosts'
 *                             setting, controlling whether hosts under
 *                             a branch are expanded inline.
 * @param int    $user         The effective user id for permission
 *                             checks; -1 indicates no permission check.
 * @param string $jstree       The accumulated html of the jstree
 *                             compatible unsorted list built up across
 *                             recursive calls.
 *
 * @return string The jstree compatible unsorted-list html fragment for
 *                this site/branch (and, at the top-level call, written
 *                to $export_path's index.html).
 */
function export_generate_site_html($export_path, $site, $parent, $expand_hosts, $user, $jstree) {
	static $depth = 5;

	$total_rows = 0;
	$jstree .= str_repeat("\t", $depth) . "<ul>\n";

	$depth++;

	write_branch_conf($site['id'], $parent, 'site', 0, 0, $user, $export_path);

	$gtcount = 0;
	$dqcount = 0;
	$ingt    = false;
	$indq    = false;

	$device_templates = db_fetch_assoc_prepared('SELECT DISTINCT "site_dt" AS type, dt.id, dt.name
		FROM host_template AS dt
		INNER JOIN host AS h
		ON h.host_template_id=dt.id
		WHERE h.site_id = ?', [$site['id']]);

	if (cacti_sizeof($device_templates)) {
		foreach ($device_templates as $branch) {
			write_branch_conf($site['id'], 0, $branch['type'], 0, $branch['id'], $user, $export_path);

			$jstree .= str_repeat("\t", $depth) . "<li id='site_" . $site['id'] . '_dt_' . $branch['id'] . "' data-jstree='{ \"type\" : \"device_template\" }'>" . $branch['name'] . "\n";
			$depth++;

			$jstree .= str_repeat("\t", $depth) . "<ul>\n";
			$depth++;

			$hosts = db_fetch_assoc_prepared('SELECT DISTINCT id AS host_id, "1" AS host_grouping_type
				FROM host
				WHERE site_id = ?
				AND host_template_id = ?
				ORDER BY description',
				[$site['id'], $branch['id']]);

			if (cacti_sizeof($hosts)) {
				foreach ($hosts as $host) {
					if (is_device_allowed($host['host_id'], $user)) {
						write_branch_conf($site['id'], $parent, 'host', $host['host_id'], 0, $user, $export_path);

						$host_description = get_host_description($host['host_id']);

						$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . "' data-jstree='{ \"type\" : \"device\" }'>" . $host_description . "\n";

						$depth++;

						if ($expand_hosts == 'on') {
							$templates = get_allowed_graph_templates('gl.host_id =' . $host['host_id'], 'name', '', $total_rows, $user);
							$count     = 0;

							if (cacti_sizeof($templates)) {
								if ($host['host_grouping_type'] == 1) {
									foreach ($templates as $template) {
										$total_rows = write_branch_conf($site['id'], $parent, 'host_gt', $host['host_id'], $template['id'], $user, $export_path);

										if ($total_rows) {
											if ($count == 0) {
												$jstree .= str_repeat("\t", $depth) . "<ul>\n";

												$depth++;
											}
											$count++;

											$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . '_gt_' . $template['id'] . "' data-jstree='{ \"type\" : \"graph_template\" }'>" . $template['name'] . "</li>\n";
										}
									}

									if ($count) {
										$depth--;
										$jstree .= str_repeat("\t", $depth) . "</ul>\n";
										$depth--;
										$jstree .= str_repeat("\t", $depth) . "</li>\n";
									}
								} else {
									$data_queries = db_fetch_assoc_prepared('SELECT sq.id, sq.name
										FROM snmp_query AS sq
										INNER JOIN host_snmp_query AS hsq
										ON sq.id=hsq.snmp_query_id
										WHERE hsq.host_id = ?',
										[$host['host_id']]);

									$data_queries[] = ['id' => '0', 'name' => __('Non Query Based', 'gexport')];

									foreach ($data_queries as $query) {
										$total_rows = write_branch_conf($site['id'], $parent, 'host_dq', $host['host_id'], $query['id'], $user, $export_path);

										if ($total_rows && $query['id'] > 0) {
											if ($count == 0) {
												$jstree .= str_repeat("\t", $depth) . "<ul>\n";
												$depth++;
											}
											$count++;

											$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . '_dq_' . $query['id'] . "' data-jstree='{ \"type\" : \"data_query\" }'>" . $query['name'] . "\n";

											$depth++;
											$jstree .= str_repeat("\t", $depth) . "<ul>\n";

											$depth++;

											$graphs = db_fetch_assoc_prepared('SELECT gl.*
												FROM graph_local AS gl
												WHERE host_id = ?
												AND snmp_query_id = ?',
												[$host['host_id'], $query['id']]);

											$dqi = [];

											foreach ($graphs as $graph) {
												$dqi[$graph['snmp_index']] = $graph['snmp_index'];
											}

											// fetch a list of field names that are sorted by the preferred sort field
											$sort_field_data = get_formatted_data_query_indexes($host['host_id'], $query['id']);

											foreach ($dqi as $i) {
												$total_rows = write_branch_conf($site['id'], $parent, 'host_dqi', $host['host_id'], $query['id'] . ':' . $i, $user, $export_path);

												if ($total_rows) {
													if (isset($sort_field_data[$i])) {
														$title = $sort_field_data[$i];
													} else {
														$title = $i;
													}
													$jstree .= str_repeat("\t", $depth) . "<li id='host_" . $host['host_id'] . '_dq_' . $query['id'] . '_dqi_' . clean_up_name($i) . "' data-jstree='{ \"type\" : \"graph\" }'>" . $title . "</li>\n";
												}
											}

											$depth--;
											$jstree .= str_repeat("\t", $depth) . "</ul>\n";
											$depth--;
											$jstree .= str_repeat("\t", $depth) . "</li>\n";
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
						} else {
							$depth--;
							$jstree .= str_repeat("\t", $depth) . "</li>\n";
						}
					}
				}
			}

			$depth--;
			$jstree .= str_repeat("\t", $depth) . "</ul>\n";
			$depth--;
			$jstree .= str_repeat("\t", $depth) . "</li>\n";
		}
	}

	$graph_templates = db_fetch_assoc_prepared('SELECT DISTINCT "site_gt" AS type, gt.id, gt.name
		FROM graph_templates AS gt
		INNER JOIN graph_local AS gl
		ON gl.graph_template_id = gt.id
		INNER JOIN host AS h
		ON h.id = gl.host_id
		WHERE h.site_id = ?
		ORDER BY gt.name', [$site['id']]);

	if (cacti_sizeof($graph_templates)) {
		$jstree .= str_repeat("\t", $depth) . "<li id='site_" . $site['id'] . "_gtbranch_0' data-jstree='{ \"type\" : \"graph_template_anchor\" }'>" . __('Graph Templates', 'gexport') . "\n";
		$depth++;
		$jstree .= str_repeat("\t", $depth) . "<ul>\n";
		$depth++;

		write_branch_conf($site['id'], 0, 'gtbranch', 0, 0, $user, $export_path);

		foreach ($graph_templates as $branch) {
			if (is_graph_template_allowed($branch['id'], $user)) {
				$jstree .= str_repeat("\t", $depth) . "<li id='site_" . $site['id'] . '_gt_' . $branch['id'] . "' data-jstree='{ \"type\" : \"graph_template\" }'>" . $branch['name'] . "</li>\n";
				write_branch_conf($site['id'], 0, $branch['type'], 0, $branch['id'], $user, $export_path);
			}
		}

		$depth--;
		$jstree .= str_repeat("\t", $depth) . "</ul>\n";
		$depth--;
		$jstree .= str_repeat("\t", $depth) . "</li>\n";
	}

	$data_queries = db_fetch_assoc_prepared('SELECT DISTINCT "site_dq" AS type, dq.id, dq.name
		FROM snmp_query AS dq
		INNER JOIN graph_local AS gl
		ON dq.id=gl.snmp_query_id
		INNER JOIN host AS h
		ON h.id = gl.host_id
		WHERE h.site_id = ?', [$site['id']]);

	if (cacti_sizeof($data_queries)) {
		$jstree .= str_repeat("\t", $depth) . "<li id='site_" . $site['id'] . "_dqbranch_0' data-jstree='{ \"type\" : \"data_query_anchor\" }'>" . __('Data Queries', 'gexport') . "\n";
		$depth++;
		$jstree .= str_repeat("\t", $depth) . "<ul>\n";
		$depth++;

		write_branch_conf($site['id'], 0, 'dqbranch', 0, 0, $user, $export_path);

		foreach ($data_queries as $branch) {
			$total_rows = write_branch_conf($site['id'], 0, 'site_dq', 0, $branch['id'], $user, $export_path);

			if ($total_rows) {
				$jstree .= str_repeat("\t", $depth) . "<li id='site_" . $site['id'] . '_dq_' . $branch['id'] . "' data-jstree='{ \"type\" : \"data_query\" }'>" . $branch['name'] . "\n";
				$depth++;

				$jstree .= str_repeat("\t", $depth) . "<ul>\n";
				$depth++;

				$graphs = db_fetch_assoc_prepared('SELECT gl.*
					FROM graph_local AS gl
					INNER JOIN host AS h
					ON gl.host_id=h.id
					WHERE h.site_id = ?
					AND snmp_query_id = ?',
					[$site['id'], $branch['id']]);

				$sort_field_data = [];

				$dqi = [];

				foreach ($graphs as $graph) {
					if (!isset($sort_field_data[$graph['host_id']])) {
						$sort_field_data[$graph['host_id']] = get_formatted_data_query_indexes($graph['host_id'], $branch['id']);
					}

					if (isset($sort_field_data[$graph['host_id']][$graph['snmp_index']])) {
						$index = $sort_field_data[$graph['host_id']][$graph['snmp_index']];
						$value = $graph['host_id'] . '|' . $graph['snmp_index'];

						$dqi[$index][] = $value;
					}
				}

				foreach ($dqi as $index => $values) {
					$values     = json_encode($values);
					$total_rows = write_branch_conf($site['id'], 0, 'site_dqi', 0, $branch['id'] . ':' . $index . ':' . $values, $user, $export_path);

					if ($total_rows) {
						$jstree .= str_repeat("\t", $depth) . "<li id='site_" . $site['id'] . '_dq_' . $branch['id'] . '_dqi_' . clean_up_name($index) . "' data-jstree='{ \"type\" : \"graph\" }'>" . $index . "</li>\n";
					}
				}

				$depth--;
				$jstree .= str_repeat("\t", $depth) . "</ul>\n";
				$depth--;
				$jstree .= str_repeat("\t", $depth) . "</li>\n";
			}
		}

		$depth--;
		$jstree .= str_repeat("\t", $depth) . "</ul>\n";
		$depth--;
		$jstree .= str_repeat("\t", $depth) . "</li>\n";
	}

	$depth--;

	$jstree .= str_repeat("\t", $depth) . "</ul>\n";

	return $jstree;
}

/**
 * The first of a series of functions that are designed to create the
 * jstree in html, and present the graphs within the tree into static
 * configuration files that will hold the graphs to be rendered. Builds
 * the top-level jstree HTML (including a small inline script exposing
 * display settings to client-side JavaScript) for each allowed tree or
 * site in scope, delegating branch-level generation to
 * export_generate_tree_html()/export_generate_site_html(), then writes
 * the result into the export directory's index.html using the plugin's
 * website.template. Called from exporter() after graphs have been
 * exported.
 *
 * @arg $export       - the export item data structure
 * @arg $export_path  - the location to write the resulting index.html file
 *
 * @param array  $export      Reference to the graph_exports row
 *                            describing the tree/site scope and display
 *                            settings.
 * @param string $export_path The directory to write the resulting
 *                            index.html file into.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the website.template file.
 */
function tree_site_export(&$export, $export_path) {
	global $config;

	// Define javascript global variables for form elements
	$jstree     = str_repeat("\t", 4) . "<script type='text/javascript'>\n";
	$jstree .= str_repeat("\t", 5) . 'var columnsPerRow=' . $export['graph_columns'] . ";\n";
	$jstree .= str_repeat("\t", 5) . 'var graphsPerPage=' . $export['graph_perpage'] . ";\n";
	$jstree .= str_repeat("\t", 5) . 'var thumbnails=' . ($export['graph_thumbnails'] == 'on' ? 'true' : 'false') . ";\n";
	$jstree .= str_repeat("\t", 5) . "var theme='" . $export['export_theme'] . "';\n";
	$jstree .= str_repeat("\t", 4) . "</script>\n";

	$jstree .= str_repeat("\t", 4) . "<div id='jstree'><ul>\n";
	$user       = $export['export_effective_user'];
	$ntree      = [];
	$sql_where  = '';
	$total_rows = 0;
	$parent     = 0;

	if ($user == 0) {
		$user = -1;
	}

	if ($export['export_presentation'] == 'tree') {
		$trees = $export['graph_tree'];

		if ($trees != '') {
			$sql_where = 'gt.id IN(' . $trees . ')';
		}

		$trees = get_allowed_trees(false, false, $sql_where, 'name', '', $total_rows, $user);
		$trees = is_array($trees) ? $trees : [];

		if (cacti_sizeof($trees)) {
			foreach ($trees as $tree) {
				$jstree .= str_repeat("\t", 4) . "<li id='tree_" . $tree['id'] . "' data-jstree='{ \"type\" : \"tree\" }'>" . get_tree_name($tree['id']) . "\n";
				$jstree = export_generate_tree_html($export_path, $tree, $parent, $export['export_expand_hosts'], $user, $jstree);
				$jstree .= str_repeat("\t", 4) . "</li>\n";
			}
		}
	} else {
		$sites = $export['graph_site'];

		if ($sites != '0') {
			$sites = explode(',', $sites);
		} else {
			$sites = array_rekey(db_fetch_assoc('SELECT id FROM sites ORDER BY name'), 'id', 'id');
		}

		if (cacti_sizeof($sites)) {
			foreach ($sites as $site_id) {
				$site_data = db_fetch_row_prepared('SELECT * FROM sites WHERE id = ?', [$site_id]);
				$site_data = is_array($site_data) ? $site_data : [];

				if (cacti_sizeof($site_data)) {
					$jstree .= str_repeat("\t", 4) . "<li id='site_" . $site_id . "' data-jstree='{ \"type\" : \"site\" }'>" . $site_data['name'] . "\n";
					$jstree  = export_generate_site_html($export_path, $site_data, $parent, $export['export_expand_hosts'], $user, $jstree);
					$jstree .= str_repeat("\t", 4) . "</li>\n";
				}
			}
		}
	}

	if ($jstree != '') {
		$jstree .= str_repeat("\t", 3) . "</ul></div>\n";

		$website = file_get_contents($config['base_path'] . '/plugins/gexport/website.template');
		$website = is_string($website) ? $website : '';
		$website = str_replace('TREEDATA', $jstree, $website);

		$fp = fopen($export_path . '/index.html', 'w');

		if (is_resource($fp)) {
			fwrite($fp, $website);
			fclose($fp);
		}
	}
}

/**
 * Builds the export directory structure and copies graphics and
 * treeview scripts to those directories: creates the js/, js/images/,
 * images/, fonts/, css/, css/images/, webfonts/, and svgs/
 * subdirectories under the export path and copies the configured
 * theme's images/fonts/icons into them. Calls export_fatal() (which
 * exits) if any subdirectory cannot be created. Called from exporter()
 * before graphs are exported.
 *
 * @arg $root_path   - the directory where Cacti is installed
 * @arg $export_path - the export directory where graphs will either be staged or located.
 *
 * @param array  $export      Reference to the graph_exports row; used
 *                            for its configured display theme and for
 *                            fatal-error reporting.
 * @param string $root_path   The directory where Cacti is installed.
 * @param string $export_path The export directory to build the
 *                            supporting directory structure under.
 *
 * @return void
 */
function create_export_directory_structure(&$export, $root_path, $export_path) {
	$theme = $export['export_theme'];

	// create the treeview sub-directory
	if (!is_dir("$export_path/js")) {
		if (!mkdir("$export_path/js", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/js failed.  Can not continue');
		}
	}

	if (!is_dir("$export_path/js/images")) {
		if (!mkdir("$export_path/js/images", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/js/images failed.  Can not continue');
		}
	}

	// create the images sub-directory
	if (!is_dir("$export_path/images")) {
		if (!mkdir("$export_path/images", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/images failed.  Can not continue');
		}
	}

	// create the images sub-directory
	if (!is_dir("$export_path/fonts")) {
		if (!mkdir("$export_path/fonts", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/fonts failed.  Can not continue');
		}
	}

	// create the images sub-directory
	if (!is_dir("$export_path/css")) {
		if (!mkdir("$export_path/css", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/css failed.  Can not continue');
		}
	}

	if (!is_dir("$export_path/css/default")) {
		if (!mkdir("$export_path/css/default", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/css/default failed.  Can not continue');
		}
	}

	if (!is_dir("$export_path/css/images")) {
		if (!mkdir("$export_path/css/images", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/css/images failed.  Can not continue');
		}
	}

	// create the graphs sub-directory
	if (!is_dir("$export_path/graphs")) {
		if (!mkdir("$export_path/graphs", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/graphs failed.  Can not continue');
		}
	}

	if (!is_dir("$export_path/webfonts")) {
		if (!mkdir("$export_path/webfonts", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/webfonts failed.  Can not continue');
		}
	}

	if (!is_dir("$export_path/svgs")) {
		if (!mkdir("$export_path/svgs", 0755, true)) {
			export_fatal($export, 'Create directory ' . $export_path . '/svgs failed.  Can not continue');
		}
	}

	// java scripts for the tree
	copy("$root_path/include/js/jquery.js", "$export_path/js/jquery.js");
	copy("$root_path/include/js/jquery-ui.js", "$export_path/js/jquery-ui.js");
	copy("$root_path/include/js/jquery.tablesorter.js", "$export_path/js/jquery.tablesorter.js");
	copy("$root_path/include/js/jstree.js", "$export_path/js/jstree.js");
	copy("$root_path/include/js/jquery.cookie.js", "$export_path/js/jquery.cookie.js");
	copy("$root_path/include/js/js.storage.js", "$export_path/js/js.storage.js");
	copy("$root_path/include/js/jquery.ui.touch.punch.js", "$export_path/js/jquery.ui.touch.punch.js");
	copy("$root_path/include/js/pace.js", "$export_path/js/pace.js");
	copy("$root_path/include/layout.js", "$export_path/js/layout.js");

	if (file_exists("$root_path/include/vendor/csrf/csrf-magic.js")) {
		copy("$root_path/include/vendor/csrf/csrf-magic.js", "$export_path/js/csrf-magic.js");
	} else {
		copy("$root_path/include/csrf/csrf-magic.js", "$export_path/js/csrf-magic.js");
	}
	copy("$root_path/include/themes/$theme/main.js", "$export_path/js/main.js");

	// css
	copy("$root_path/include/themes/$theme/main.css", "$export_path/css/main.css");
	copy("$root_path/include/themes/$theme/jquery-ui.css", "$export_path/css/jquery-ui.css");
	copy("$root_path/include/themes/$theme/default/style.css", "$export_path/css/default/style.css");
	copy("$root_path/include/themes/$theme/pace.css", "$export_path/css/pace.css");
	copy("$root_path/include/fa/css/all.css", "$export_path/css/all.css");

	// fonts
	copy("$root_path/include/fa/webfonts/fa-solid-900.ttf", "$export_path/webfonts/fa-solid-900.ttf");
	copy("$root_path/include/fa/webfonts/fa-solid-900.woff", "$export_path/webfonts/fa-solid-900.woff");
	copy("$root_path/include/fa/webfonts/fa-solid-900.woff2", "$export_path/webfonts/fa-solid-900.woff2");

	// images for html
	copy("$root_path/images/favicon.ico", "$export_path/images/favicon.ico");

	copy("$root_path/images/tree.png", "$export_path/images/tree.png");
	copy("$root_path/images/server.png", "$export_path/images/server.png");
	copy("$root_path/images/server_chart_curve.png", "$export_path/images/server_chart_curve.png");
	copy("$root_path/images/server_chart.png", "$export_path/images/server_chart.png");
	copy("$root_path/images/server_dataquery.png", "$export_path/images/server_dataquery.png");
	copy("$root_path/images/site.png", "$export_path/images/site.png");
	copy("$root_path/images/device_template.png", "$export_path/images/device_template.png");
	copy("$root_path/images/server_table.png", "$export_path/images/server_table.png");
	copy("$root_path/images/server_graph_template.png", "$export_path/images/server_graph_template.png");
	copy("$root_path/include/themes/$theme/images/cacti_logo.svg", "$export_path/images/cacti_logo.svg");

	// jstree theme files
	$files = ['32px.png', '40px.png', 'style.css', 'throbber.gif'];

	foreach ($files as $file) {
		copy("$root_path/include/themes/$theme/default/$file", "$export_path/css/default/$file");
	}

	$directory   = "$root_path/include/themes/$theme/images";
	$glob_result = glob("$directory/*.*");
	$directory   = array_diff(is_array($glob_result) ? $glob_result : [], ["$directory/.", "$directory/.."]);

	foreach ($directory as $file) {
		$file = basename($file);
		copy("$root_path/include/themes/$theme/images/$file", "$export_path/css/images/$file");
	}

	$directory   = "$root_path/include/fa/webfonts";
	$glob_result = glob("$directory/*.*");
	$directory   = array_diff(is_array($glob_result) ? $glob_result : [], ["$directory/.", "$directory/.."]);

	foreach ($directory as $file) {
		$file = basename($file);
		copy("$root_path/include/fa/webfonts/$file", "$export_path/webfonts/$file");
	}

	$directory   = "$root_path/include/fa/svgs";
	$glob_result = glob("$directory/*.*");
	$directory   = array_diff(is_array($glob_result) ? $glob_result : [], ["$directory/.", "$directory/.."]);

	foreach ($directory as $file) {
		$file = basename($file);
		copy("$root_path/include/fa/svgs/$file", "$export_path/svgs/$file");
	}
}

/**
 * A simple function to return the host description of a host. Called
 * while building the jstree html to label host-level tree nodes.
 *
 * @arg $host_id - the id of the host in question
 *
 * @param int $host_id The id of the host in question.
 *
 * @return string|false The host's description, or false if not found.
 */
function get_host_description($host_id) {
	return db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$host_id]);
}

/**
 * A simple function to return the tree name of a tree. Called from
 * tree_site_export() to label a tree's top-level jstree node.
 *
 * @arg $tree_id - the id of the tree in question
 *
 * @param int $tree_id The id of the tree in question.
 *
 * @return string|false The tree's name, or false if not found.
 */
function get_tree_name($tree_id) {
	return db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', [$tree_id]);
}

/**
 * Delete the directory pointed to by the $path variable, recursively
 * removing writable files and subdirectories. Currently unused/dead
 * code: not called from anywhere else in this file.
 *
 * @arg $path   - the directory to delete or clean
 * @arg $deldir - (optional parameter, true as default) delete the directory (true) or just clean it (false)
 *
 * @param string $path   The directory to delete or clean.
 * @param bool   $deldir Whether to also remove the directory itself
 *                       (true) or just empty it (false); defaults to
 *                       true.
 *
 * @return void
 */
function del_directory($path, $deldir = true) {
	// check if the directory name have a '/' at the end, add if not
	if ($path[strlen($path) - 1] != '/') {
		$path .= '/';
	}

	// cascade through the directory structure(s) until they are all delected
	if (is_dir($path)) {
		$d = opendir($path);

		if ($d !== false) {
			while ($f = readdir($d)) {
				if ($f != '.' && $f != '..') {
					$rf = $path . $f;

					// if it is a directory, recursive call to the function
					if (is_dir($rf)) {
						del_directory($rf);
					} elseif (is_file($rf) && is_writable($rf)) {
						unlink($rf);
					}
				}
			}
			closedir($d);
		}

		// if $deldir is true, remove the directory
		if ($deldir && is_writable($path)) {
			rmdir($path);
		}
	}
}

/**
 * Simple function to check for the existence of a file and remove it.
 * Called from export_graph_files() to remove a previously exported
 * graph's HTML file before regenerating it.
 *
 * @arg $filename - the file to remove.
 *
 * @param string $filename The file to remove.
 *
 * @return void
 */
function check_remove($filename) {
	if (file_exists($filename) && is_writable($filename)) {
		unlink($filename);
	}
}
