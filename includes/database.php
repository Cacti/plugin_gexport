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
 * The graph_exports table definition (each configured export job's
 * settings and last-run status), shared by the create path
 * (api_plugin_db_table_create()) and the upgrade path (db_update_table())
 * so both stay in sync from a single source.
 *
 * @return array<string, mixed> The table definition array.
 */
function gexport_graph_exports_table_data(): array {
	$data               = [];
	$data['columns'][]  = ['name' => 'id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][]  = ['name' => 'name', 'type' => 'varchar(64)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_type', 'type' => 'varchar(12)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'enabled', 'type' => 'char(3)', 'NULL' => true, 'default' => 'on'];
	$data['columns'][]  = ['name' => 'export_presentation', 'type' => 'varchar(20)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_effective_user', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'default' => '0'];
	$data['columns'][]  = ['name' => 'export_expand_hosts', 'type' => 'char(3)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_theme', 'type' => 'varchar(20)', 'NULL' => true, 'default' => 'modern'];
	$data['columns'][]  = ['name' => 'graph_tree', 'type' => 'varchar(255)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'graph_site', 'type' => 'varchar(255)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'graph_height', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'default' => '100'];
	$data['columns'][]  = ['name' => 'graph_width', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'default' => '300'];
	$data['columns'][]  = ['name' => 'graph_thumbnails', 'type' => 'char(3)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'graph_columns', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'default' => '2'];
	$data['columns'][]  = ['name' => 'graph_perpage', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'default' => '50'];
	$data['columns'][]  = ['name' => 'graph_max', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'default' => '2000'];
	$data['columns'][]  = ['name' => 'export_clear', 'type' => 'char(3)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_thumbs', 'type' => 'char(3)', 'NULL' => true, 'default' => 'on'];
	$data['columns'][]  = ['name' => 'export_args', 'type' => 'char(25)', 'NULL' => true, 'default' => '-zav'];
	$data['columns'][]  = ['name' => 'export_directory', 'type' => 'varchar(255)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_temp_directory', 'type' => 'varchar(255)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_timing', 'type' => 'varchar(20)', 'NULL' => true, 'default' => 'disabled'];
	$data['columns'][]  = ['name' => 'export_skip', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'default' => '0'];
	$data['columns'][]  = ['name' => 'export_hourly', 'type' => 'varchar(20)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_daily', 'type' => 'varchar(20)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_threads', 'type' => 'int(10)', 'NULL' => true, 'default' => '0'];
	$data['columns'][]  = ['name' => 'export_sanitize_remote', 'type' => 'char(3)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_host', 'type' => 'varchar(64)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_port', 'type' => 'varchar(5)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_passive', 'type' => 'char(3)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_user', 'type' => 'varchar(40)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_password', 'type' => 'varchar(64)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'export_private_key_path', 'type' => 'varchar(255)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'status', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'default' => '0'];
	$data['columns'][]  = ['name' => 'export_pid', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][]  = ['name' => 'next_start', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][]  = ['name' => 'last_checked', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][]  = ['name' => 'last_started', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][]  = ['name' => 'last_ended', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][]  = ['name' => 'last_errored', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][]  = ['name' => 'last_runtime', 'type' => 'double', 'NULL' => false, 'default' => '0'];
	$data['columns'][]  = ['name' => 'last_error', 'type' => 'varchar(255)', 'NULL' => true, 'default' => null];
	$data['columns'][]  = ['name' => 'total_graphs', 'type' => 'double', 'NULL' => true, 'default' => '0'];
	$data['primary']    = ['id'];
	$data['type']       = 'InnoDB';
	$data['comment']    = 'Stores Graph Export Settings for Cacti';

	return $data;
}

/**
 * The graph_exports_tasks table definition (per-graph worker tasks for an
 * in-progress export job), shared by the create path
 * (api_plugin_db_table_create()) and the upgrade path (db_update_table())
 * so both stay in sync from a single source.
 *
 * @return array<string, mixed> The table definition array.
 */
function gexport_graph_exports_tasks_table_data(): array {
	$data               = [];
	$data['columns'][]  = ['name' => 'id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][]  = ['name' => 'local_graph_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0'];
	$data['columns'][]  = ['name' => 'export_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0'];
	$data['columns'][]  = ['name' => 'pid', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0'];
	$data['columns'][]  = ['name' => 'user', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0'];
	$data['columns'][]  = ['name' => 'folder', 'type' => 'varchar(255)', 'NULL' => true, 'default' => ''];
	$data['columns'][]  = ['name' => 'status', 'type' => 'int(1)', 'unsigned' => true, 'NULL' => false, 'default' => '0'];
	$data['columns'][]  = ['name' => 'start_time', 'type' => 'int(1)', 'unsigned' => true, 'NULL' => false, 'default' => '0'];
	$data['primary']    = ['id'];
	$data['keys'][]     = ['name' => 'status', 'columns' => ['status']];
	$data['keys'][]     = ['name' => 'pid', 'columns' => ['pid']];
	$data['keys'][]     = ['name' => 'start_time', 'columns' => ['start_time']];
	$data['type']       = 'InnoDB';
	$data['comment']    = 'Stores Graph Export Tasks for Cacti';

	return $data;
}

/**
 * Creates every table this plugin owns via the tracked plugin table API
 * (idempotent, so it is safe to call on both install and upgrade). Called
 * from plugin_gexport_install() during installation.
 *
 * @return bool Always returns true.
 *
 * @global array  $config           Cacti global configuration array; used
 *                                 to load database.php.
 * @global object $database_default Reserved/declared for parity with other
 *                                 database-touching functions; not used
 *                                 directly here.
 */
function gexport_setup_table() {
	global $config, $database_default;
	require_once($config['library_path'] . '/database.php');

	gexport_create_table();
	gexport_create_table_tasks();

	return true;
}

/**
 * Creates the graph_exports table (holding each configured export job's
 * settings and last-run status) via the tracked plugin table API. Called
 * from gexport_setup_table() during installation.
 *
 * @return bool Always returns true.
 */
function gexport_create_table() {
	api_plugin_db_table_create('gexport', 'graph_exports', gexport_graph_exports_table_data());

	return true;
}

/**
 * Creates the graph_exports_tasks table (holding per-graph worker tasks
 * for an in-progress export job) via the tracked plugin table API. Called
 * from gexport_setup_table() during installation.
 *
 * @return bool Always returns true.
 */
function gexport_create_table_tasks() {
	api_plugin_db_table_create('gexport', 'graph_exports_tasks', gexport_graph_exports_tasks_table_data());

	return true;
}

/**
 * Refreshes this plugin's tables to their current definition on upgrade:
 * db_update_table() diffs the live schema against the definition and issues
 * the exact ALTER (columns, indexes, engine) when the table already exists,
 * otherwise the table is created outright. A true column rename (which
 * db_update_table() can not express) is applied as a guarded pre-step first.
 * Called from gexport_check_upgrade() when the stored version changes.
 *
 * @return void
 *
 * @global array  $config           Cacti global configuration array; used
 *                                 to load database.php.
 * @global object $database_default Reserved/declared for parity with other
 *                                 database-touching functions; not used
 *                                 directly here.
 */
function gexport_upgrade_tables() {
	global $config, $database_default;
	require_once($config['library_path'] . '/database.php');

	// db_update_table() diffs by column name and can not rename, so preserve
	// this historical rename (and its data) before the schema refresh below.
	if (db_column_exists('graph_exports', 'export_index_key_path')) {
		db_execute('ALTER TABLE graph_exports
			CHANGE COLUMN `export_index_key_path` `export_private_key_path` varchar(255)');
	}

	$tables = [
		'graph_exports'       => gexport_graph_exports_table_data(),
		'graph_exports_tasks' => gexport_graph_exports_tasks_table_data(),
	];

	foreach ($tables as $table => $data) {
		if (db_table_exists($table)) {
			db_update_table($table, $data);
		} else {
			api_plugin_db_table_create('gexport', $table, $data);
		}
	}
}

/**
 * Drops every table this plugin owns. Called from plugin_gexport_uninstall()
 * when an administrator uninstalls the plugin.
 *
 * @return void
 */
function gexport_drop_tables() {
	db_execute('DROP TABLE IF EXISTS graph_exports');
	db_execute('DROP TABLE IF EXISTS graph_exports_tasks');
}
