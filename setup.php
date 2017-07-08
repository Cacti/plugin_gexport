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

function plugin_gexport_install() {
	# graph setup all arrays needed for automation
	api_plugin_register_hook('gexport', 'config_arrays',        'gexport_config_arrays',        'setup.php');
	api_plugin_register_hook('gexport', 'draw_navigation_text', 'gexport_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('gexport', 'poller_bottom',        'gexport_poller_bottom',        'setup.php');

	api_plugin_register_realm('gexport', 'gexport.php', __('Export Cacti Graphs Settings', 'gexport'), 1);

	gexport_setup_table();
}

function plugin_gexport_uninstall() {
	db_execute('DROP TABLE graph_exports');

	return true;
}

function plugin_gexport_check_config() {
	return true;
}

function plugin_gexport_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/gexport/INFO', true);
	return $info['info'];
}

function gexport_poller_bottom() {
	global $config;

	/* graph export */
	if ($config['poller_id'] == 1) {
		$exports = db_fetch_assoc('SELECT * FROM graph_exports WHERE enabled="on"');
		if (sizeof($exports)) {
			$command_string = read_config_option('path_php_binary');
			$extra_args = '-q "' . $config['base_path'] . '/plugins/gexport/poller_export.php"';
			exec_background($command_string, $extra_args);
		}
	}
}

function gexport_check_upgrade() {
	global $config, $database_default;

	include_once($config['library_path'] . '/database.php');
	include_once($config['library_path'] . '/functions.php');

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php', 'gexport.php');
	if (!in_array(get_current_page(), $files)) {
		return;
	}

	$version = plugin_gexport_version ();
	$current = $version['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='gexport'");
	if ($current != $old) {
		if (api_plugin_is_enabled('gexport')) {
			# may sound ridiculous, but enables new hooks
			api_plugin_enable_hooks('gexport');
		}

		db_execute("UPDATE plugin_config
			SET version='$current'
			WHERE directory='gexport'");

		db_execute("UPDATE plugin_config SET
			version='" . $version['version']  . "',
			name='"    . $version['longname'] . "',
			author='"  . $version['author']   . "',
			webpage='" . $version['homepage'] . "'
			WHERE directory='" . $version['name'] . "' ");

		db_execute("ALTER TABLE graph_exports MODIFY column export_user VARCHAR(40) DEFAULT ''");
	}
}

function gexport_check_dependencies() {
	return true;
}

function gexport_setup_table() {
	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');

	db_execute("CREATE TABLE `graph_exports` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`name` varchar(64) DEFAULT '',
		`export_type` varchar(12) DEFAULT '',
		`enabled` char(3) DEFAULT 'on',
		`export_presentation` varchar(20) DEFAULT '',
		`export_effective_user` int(10) unsigned DEFAULT '0',
		`export_expand_hosts` char(3) DEFAULT '',
		`export_theme` varchar(20) DEFAULT 'modern',
		`graph_tree` varchar(255) DEFAULT '',
		`graph_site` varchar(255) DEFAULT '',
		`graph_height` int(10) unsigned DEFAULT '100',
		`graph_width` int(10) unsigned DEFAULT '300',
		`graph_thumbnails` char(3) DEFAULT '',
		`graph_columns` int(10) unsigned DEFAULT '2',
		`graph_perpage` int(10) unsigned DEFAULT '50',
		`graph_max` int(10) unsigned DEFAULT '2000',
		`export_directory` varchar(255) DEFAULT '',
		`export_temp_directory` varchar(255) DEFAULT '',
		`export_timing` varchar(20) DEFAULT 'disabled',
		`export_skip` int(10) unsigned DEFAULT '0',
		`export_hourly` varchar(20) DEFAULT '',
		`export_daily` varchar(20) DEFAULT '',
		`export_sanitize_remote` char(3) DEFAULT '',
		`export_host` varchar(64) DEFAULT '',
		`export_port` varchar(5) DEFAULT '',
		`export_passive` char(3) DEFAULT '',
		`export_user` varchar(40) DEFAULT '',
		`export_password` varchar(64) DEFAULT '',
		`export_private_key_path` varchar(255) DEFAULT '',
		`status` int(10) unsigned DEFAULT '0',
		`export_pid` int(10) unsigned DEFAULT NULL,
		`next_start` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		`last_checked` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		`last_started` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		`last_ended` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		`last_errored` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		`last_runtime` double NOT NULL DEFAULT '0',
		`last_error` varchar(255) DEFAULT NULL,
		`total_graphs` double DEFAULT '0',
		PRIMARY KEY (`id`))
		ENGINE=InnoDB
		COMMENT='Stores Graph Export Settings for Cacti'");

	return true;
}

function gexport_config_arrays() {
	global $menu, $fields_export_edit, $messages, $config, $graphs_per_page;

	/* perform database upgrade, if required */
	gexport_check_upgrade();

	$dir = dir($config['base_path'] . '/include/themes/');
	while (false !== ($entry = $dir->read())) {
		if ($entry != '.' && $entry != '..') {
			if (is_dir($config['base_path'] . '/include/themes/' . $entry)) {
				$themes[$entry] = ucwords($entry);
			}
		}
	}
	asort($themes);
	$dir->close();

	if ($config['cacti_server_os'] == 'unix') {
		$tmp = '/tmp/';
	}else{
		$tmp = getenv('TEMP');
	}

	if (isset($_SESSION['gexport_message']) && $_SESSION['gexport_message'] != '') {
		$messages['gexport_message'] = array('message' => $_SESSION['gexport_message'], 'type' => 'info');
	}

	$menu[__('Utilities', 'gexport')]['plugins/gexport/gexport.php'] = __('Graph Exports', 'gexport');

	$sites = array_rekey(db_fetch_assoc('SELECT "0" AS id, "All Sites" AS name UNION SELECT id, name FROM sites ORDER BY name'), 'id', 'name');
	$trees = array_rekey(db_fetch_assoc('SELECT "0" AS id, "All Trees" AS name UNION SELECT id, name FROM graph_tree ORDER BY name'), 'id', 'name');

	$fields_export_edit = array(
		'export_hdr_general' => array(
			'friendly_name' => __('General', 'gexport'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'name' => array(
			'friendly_name' => __('Export Name', 'gexport'),
			'description' => __('The name of this Graph Export Definition.', 'gexport'),
			'method' => 'textbox',
			'value' => '|arg1:name|',
			'default' => 'New Graph Export',
			'max_length' => '64',
			'size' => '40'
		),
		'export_type' => array(
			'friendly_name' => __('Export Method', 'gexport'),
			'description' => __('Choose which export method to use.', 'gexport'),
			'method' => 'drop_array',
			'value' => '|arg1:export_type|',
			'default' => 'disabled',
			'array' => array(
				'local' => __('Local', 'gexport'),
				'ftp' => __('FTP', 'gexport'),
				'sftp' => __('SFTP', 'gexport'),
				'ftp_nc' => __('FTP NC', 'gexport'),
				'scp' => __('SCP', 'gexport'),
				'rsync' => __('RSYNC', 'gexport')
			)
		),
		'enabled' => array(
			'friendly_name' => __('Enabled', 'gexport'),
			'description' => __('Check this Checkbox if you wish this Graph Export Definition to be enabled.', 'gexport'),
			'value' => '|arg1:enabled|',
			'default' => 'on',
			'method' => 'checkbox',
		),
		'export_theme' => array(
			'friendly_name' => __('Theme', 'gexport'),
			'description' => __('Please select one of the available Themes to skin your Cacti Graph Exports with.', 'gexport'),
			'method' => 'drop_array',
			'value' => '|arg1:export_theme|',
			'default' => 'modern',
			'array' => $themes
			),
		'export_presentation' => array(
			'friendly_name' => __('Presentation Method', 'gexport'),
			'description' => __('Choose which presentation would you want for the html generated pages. If you choose classical presentation; the Graphs will be in a only-one-html page. If you choose tree presentation, the Graph Tree architecture will be kept in the static html pages', 'gexport'),
			'method' => 'drop_array',
			'value' => '|arg1:export_presentation|',
			'default' => 'disabled',
			'array' => array(
				'preview' => __('Site', 'gexport'),
				'tree' => __('Tree', 'gexport'),
			)
		),
		'export_hdr_timing' => array(
			'friendly_name' => __('Export Timing', 'gexport'),
			'method' => 'spacer',
		),
		'export_timing' => array(
			'friendly_name' => __('Timing Method', 'gexport'),
			'description' => __('Choose when to Export Graphs.', 'gexport'),
			'method' => 'drop_array',
			'value' => '|arg1:export_timing|',
			'default' => 'export_hourly',
			'array' => array(
				'periodic' => __('Periodic', 'gexport'),
				'hourly' => __('Hourly', 'gexport'),
				'daily' => __('Daily', 'gexport')
			),
		),
		'export_skip' => array(
			'friendly_name' => __('Periodic Export Cycle', 'gexport'),
			'description' => __('How often do you wish Cacti to Export Graphs.  This is the unit of Polling Cycles you wish to export Graphs.', 'gexport'),
			'method' => 'drop_array',
			'value' => '|arg1:export_skip|',
			'array' => array(
				1  => __('Every Polling Cycle', 'gexport'),
				2  => __('Every %d Polling Cycles', 2, 'gexport'),
				3  => __('Every %d Polling Cycles', 3, 'gexport'),
				4  => __('Every %d Polling Cycles', 4, 'gexport'),
				5  => __('Every %d Polling Cycles', 5, 'gexport'),
				6  => __('Every %d Polling Cycles', 6, 'gexport'),
				7  => __('Every %d Polling Cycles', 7, 'gexport'),
				8  => __('Every %d Polling Cycles', 8, 'gexport'),
				9  => __('Every %d Polling Cycles', 9, 'gexport'),
				10 => __('Every %d Polling Cycles', 10, 'gexport'),
				11 => __('Every %d Polling Cycles', 11, 'gexport'),
				12 => __('Every %d Polling Cycles', 12, 'gexport')
			),
		),
		'export_hourly' => array(
			'friendly_name' => __('Hourly at specified minutes', 'gexport'),
			'description' => __('If you want Cacti to export static images on an hourly basis, put the minutes of the hour when to do that. Cacti assumes that you run the data gathering script every 5 minutes, so it will round your value to the one closest to its runtime. For instance, 43 would equal 40 minutes past the hour.', 'gexport'),
			'method' => 'textbox',
			'placeholder' => 'MM',
			'value' => '|arg1:export_hourly|',
			'default' => '00',
			'max_length' => '10',
			'size' => '5'
		),
		'export_daily' => array(
			'friendly_name' => __('Daily at specified time', 'gexport'),
			'description' => __('If you want Cacti to export static images on an daily basis, put here the time when to do that. Cacti assumes that you run the data gathering script every 5 minutes, so it will round your value to the one closest to its runtime. For instance, 21:23 would equal 20 minutes after 9 PM.', 'gexport'),
			'method' => 'textbox',
			'placeholder' => 'HH:MM',
			'value' => '|arg1:export_daily|',
			'default' => '00:00',
			'max_length' => '10',
			'size' => '5'
		),
		'export_thumb_options' => array(
			'friendly_name' => __('Graph Selection & Settings', 'gexport'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'export_effective_user' => array(
			'friendly_name' => __('Effective User', 'gexport'),
			'description' => __('The user name to utilize for establishing permissions to Cacti Graphs.  This user name will be used to determine which Graphs/Trees can be exported.  N/A means don\'t assume any security is in place.', 'gexport'),
			'method' => 'drop_sql',
			'value' => '|arg1:export_effective_user|',
			'sql' => 'SELECT id, username AS name FROM user_auth ORDER BY name',
			'none_value' => 'N/A',
			'default' => '0'
		),
		'graph_tree' => array(
			'friendly_name' => __('Tree', 'gexport'),
			'description' => __('The Tree(s) to export.', 'gexport'),
			'method' => 'drop_multi',
			'value' => '|arg1:graph_tree|',
			'array' => $trees,
			'default' => '0'
		),
		'export_expand_hosts' => array(
			'friendly_name' => __('Expand Devices/Sites', 'gexport'),
			'description' => __('This setting determines if Tree Devices and Site Templates and Devices will be expanded or not.  If set to expanded, each host will have a sub-folder containing either Graph Templates or Data Query items.', 'gexport'),
			'method' => 'drop_array',
			'value' => '|arg1:export_expand_hosts|',
			'default' => 'off',
			'array' => array(
				'off' => __('Off', 'gexport'),
				'on' => __('On', 'gexport')
			)
		),
		'graph_site' => array(
			'friendly_name' => __('Site', 'gexport'),
			'description' => __('The Site for Cacti Graphs to be Exported from.', 'gexport'),
			'method' => 'drop_multi',
			'value' => '|arg1:graph_site|',
			'array' => $sites,
			'default' => '0'
		),
		'graph_width' => array(
			'friendly_name' => __('Graph Thumbnail Width', 'gexport'),
			'description' => __('The default width of Thumbnail Graphs in pixels.', 'gexport'),
			'method' => 'textbox',
			'value' => '|arg1:graph_width|',
			'default' => '300',
			'max_length' => '10',
			'size' => '5'
		),
		'graph_height' => array(
			'friendly_name' => __('Graph Thumbnail Height', 'gexport'),
			'description' => __('The height of Thumbnail Graphs in pixels.', 'gexport'),
			'method' => 'textbox',
			'value' => '|arg1:graph_height|',
			'default' => '100',
			'max_length' => '10',
			'size' => '5'
		),
		'graph_thumbnails' => array(
			'friendly_name' => __('Default View Thumbnails', 'gexport'),
			'description' => __('Check this if you want the default Graph View to be in Thumbnail mode.', 'gexport'),
			'method' => 'checkbox',
			'value' => '|arg1:export_sanitize_remote|',
			'max_length' => '255'
		),
		'graph_columns' => array(
			'friendly_name' => __('Default Graph Columns', 'gexport'),
			'description' => __('The number of columns to use by default when displaying Graphs.', 'gexport'),
			'method' => 'textbox',
			'value' => '|arg1:graph_columns|',
			'default' => '2',
			'max_length' => '5',
			'size' => '5'
		),
		'graph_perpage' => array(
			'friendly_name' => __('Graphs Per Page', 'gexport'),
			'description' => __('Choose the number of Graphs Per Page to display.', 'gexport'),
			'method' => 'drop_array',
			'value' => '|arg1:graph_perpage|',
			'default' => 20,
			'array' => $graphs_per_page,
		),
		'graph_max' => array(
			'friendly_name' => __('Maximum Graphs to Export', 'gexport'),
			'description' => __('After this number is reached, an error will be generated, but exporting will continue.  Set to 0 for no limit.', 'gexport'),
			'method' => 'textbox',
			'value' => '|arg1:graph_max|',
			'default' => '2000',
			'max_length' => '5',
			'size' => '5'
		),
		'export_hdr_paths' => array(
			'friendly_name' => __('Export Location Information', 'gexport'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'export_directory' => array(
			'friendly_name' => __('Export Directory', 'gexport'),
			'description' => __('This is the directory, either on the local system or on the remote system, that will contain the exported data.', 'gexport'),
			'method' => 'dirpath',
			'value' => '|arg1:export_directory|',
			'max_length' => '255'
		),
		'export_temp_directory' => array(
			'friendly_name' => __('Local Scratch Directory', 'gexport'),
			'description' => __('This is the directory that Cacti will temporarily store output prior to sending to the remote site via the transfer method.  The contents of this directory will be deleted after the data is transferred.', 'gexport'),
			'method' => 'dirpath',
			'value' => '|arg1:export_temp_directory|',
			'default' => $tmp,
			'max_length' => '255'
		),
		'export_hdr_remote' => array(
			'friendly_name' => __('Remote Options', 'gexport'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'export_sanitize_remote' => array(
			'friendly_name' => __('Sanitize remote directory', 'gexport'),
			'description' => __('Check this if you want to delete any existing files in the FTP remote directory. This option is in use only when using the PHP built-in ftp functions.', 'gexport'),
			'method' => 'checkbox',
			'value' => '|arg1:export_sanitize_remote|',
			'max_length' => '255'
		),
		'export_host' => array(
			'friendly_name' => __('Remote Host', 'gexport'),
			'description' => __('Denotes the host to send Exported Graphs to.', 'gexport'),
			'placeholder' => 'hostname',
			'method' => 'textbox',
			'value' => '|arg1:export_host|',
			'max_length' => '255'
		),
		'export_port' => array(
			'friendly_name' => __('Remote Port', 'gexport'),
			'description' => __('Communication port to use for the export method if non-standard (leave empty for defaults).', 'gexport'),
			'placeholder' => 'default',
			'method' => 'textbox',
			'value' => '|arg1:export_port|',
			'max_length' => '10',
			'size' => '5'
		),
		'export_passive' => array(
			'friendly_name' => __('Use passive mode', 'gexport'),
			'description' => __('Check this if you want to connect in passive mode to the FTP server.', 'gexport'),
			'method' => 'checkbox',
			'value' => '|arg1:export_passive|',
			'max_length' => '255'
		),
		'export_user' => array(
			'friendly_name' => __('User', 'gexport'),
			'description' => __('Account to logon on the remote server (leave empty for defaults).', 'gexport'),
			'method' => 'textbox',
			'value' => '|arg1:export_user|',
			'max_length' => '40',
			'size' => 30
		),
		'export_password' => array(
			'friendly_name' => __('Password', 'gexport'),
			'description' => __('Password for the remote ftp account (leave empty for blank).', 'gexport'),
			'method' => 'textbox_password',
			'value' => '|arg1:export_password|',
			'max_length' => '64',
			'size' => 30
		),
		'export_private_key_path' => array(
			'friendly_name' => __('Private Key Path', 'gexport'),
			'description' => __('For SCP and RSYNC, enter the Private Key path if required.', 'gexport'),
			'method' => 'filepath',
			'value' => '|arg1:export_private_key_path|',
			'max_length' => '255',
			'size' => 50
		)
	);
}

function gexport_draw_navigation_text($nav) {
	$nav['gexport.php:'] = array(
		'title' => __('Exported Cacti Graph Pages', 'gexport'),
		'mapping' => 'index.php:',
		'url' => 'gexport.php',
		'level' => '1');

	$nav['gexport.php:edit'] = array(
		'title' => __('(Edit)', 'gexport'),
		'mapping' => 'index.php:,gexport.php:',
		'url' => '',
		'level' => '2');

	$nav['gexport.php:actions'] = array(
		'title' => __('Actions', 'gexport'),
		'mapping' => 'index.php:,gexport.php:',
		'url' => '',
		'level' => '2');

	return $nav;
}

