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

chdir('../../');
include('./include/auth.php');
include_once('./plugins/gexport/functions.php');

$export_actions = array(
	'1' => __('Delete', 'gexport'),
	'2' => __('Enable', 'gexport'),
	'3' => __('Disable', 'gexport'),
	'4' => __('Export Now', 'gexport')
);

$export_timing = array(
	__('Periodic', 'gexport'),
	__('Daily', 'gexport'),
	__('Hourly', 'gexport')
);

set_default_action();

switch (get_request_var('action')) {
	case 'save':
		export_form_save();

		break;
	case 'actions':
		export_form_actions();

		break;
	case 'edit':
		top_header();

		export_edit();

		bottom_footer();

		break;
	default:
		top_header();

		gexport();

		bottom_footer();

		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function export_form_save() {
	if (isset_request_var('save_component_export')) {
		$save['id']                      = get_filter_request_var('id');
		$save['name']                    = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['export_type']             = form_input_validate(get_nfilter_request_var('export_type'), 'export_type', '^local|ftp|scp|sftp|rsync$', false, 3);

		$save['enabled']                 = isset_request_var('enabled') ? 'on':'';

		$save['export_presentation']     = form_input_validate(get_nfilter_request_var('export_presentation'), 'export_presentation', '^preview|tree$', false, 3);
		$save['export_effective_user']   = form_input_validate(get_nfilter_request_var('export_effective_user'), 'export_effective_user', '', true, 3);
		$save['export_expand_hosts']     = form_input_validate(get_nfilter_request_var('export_expand_hosts'), 'export_expand_hosts', '^on|off$', false, 3);

		$save['export_theme']            = form_input_validate(get_nfilter_request_var('export_theme'), 'export_theme', '', false, 3);

		if (isset_request_var('graph_tree')) {
			$save['graph_tree'] = form_input_validate(implode(',', get_nfilter_request_var('graph_tree')), 'graph_tree', '', true, 3);
		} else {
			$save['graph_tree'] = '';
		}

		if (isset_request_var('graph_site')) {
			$save['graph_site'] = form_input_validate(implode(',', get_nfilter_request_var('graph_site')), 'graph_site', '', true, 3);
		} else {
			$save['graph_site'] = '';
		}

		$save['graph_width']             = form_input_validate(get_nfilter_request_var('graph_width'), 'graph_width', '^[0-9]+$', false, 3);
		$save['graph_height']            = form_input_validate(get_nfilter_request_var('graph_height'), 'graph_height', '^[0-9]+$', false, 3);
		$save['graph_thumbnails']        = isset_request_var('graph_thumbnails') ? 'on':'';
		$save['graph_perpage']           = form_input_validate(get_nfilter_request_var('graph_perpage'), 'graph_perpage', '^[0-9]+$', false, 3);
		$save['graph_columns']           = form_input_validate(get_nfilter_request_var('graph_columns'), 'graph_columns', '^[0-9]+$', false, 3);
		$save['graph_max']               = form_input_validate(get_nfilter_request_var('graph_max'), 'graph_max', '^[0-9]+$', false, 3);

		$save['export_args']             = form_input_validate(get_nfilter_request_var('export_args'), 'export_args', '', false, 3);
		$save['export_clear']            = isset_request_var('export_clear') ? 'on':'';
		$save['export_thumbs']           = isset_request_var('export_thumbs') ? 'on':'';
		$save['export_directory']        = form_input_validate(get_nfilter_request_var('export_directory'), 'export_directory', '', false, 3);
		$save['export_temp_directory']   = form_input_validate(get_nfilter_request_var('export_temp_directory'), 'export_temp_directory', '', false, 3);
		$save['export_timing']           = form_input_validate(get_nfilter_request_var('export_timing'), 'export_timing', '^periodic|hourly|daily$', false, 3);
		$save['export_threads']          = form_input_validate(get_nfilter_request_var('export_threads'), 'export_threads', '^[0-9]+$', false, 3);
		$save['export_skip']             = form_input_validate(get_nfilter_request_var('export_skip'), 'export_skip', '^[0-9]+$', false, 3);
		$save['export_hourly']           = form_input_validate(get_nfilter_request_var('export_hourly'), 'export_hourly', '^[0-9]+$', false, 3);
		$save['export_daily']            = form_input_validate(get_nfilter_request_var('export_daily'), 'export_daily', '^[0-9]+:[0-9]+$', false, 3);

		$save['export_sanitize_remote']  = isset_request_var('export_sanitize_remote') ? 'on':'';

		$save['export_host']             = form_input_validate(get_nfilter_request_var('export_host'), 'export_host', '', true, 3);
		$save['export_port']             = form_input_validate(get_nfilter_request_var('export_port'), 'export_port', '^[0-9]+$', true, 3);

		$save['export_passive']          = isset_request_var('export_passive') ? 'on':'';

		$save['export_user']             = form_input_validate(get_nfilter_request_var('export_user'), 'export_user', '', true, 3);
		$save['export_password']         = form_input_validate(get_nfilter_request_var('export_password'), 'export_password', '', true, 3);
		$save['export_private_key_path'] = form_input_validate(get_nfilter_request_var('export_private_key_path'), 'export_private_key_path', '', true, 3);

		/* determine the start time */
		$next_start = gexport_calc_next_start($save);
		$save['next_start'] = $next_start;

		if (!is_error_message()) {
			$export_id = sql_save($save, 'graph_exports');

			if ($export_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: gexport.php?action=edit&header=false&id=' . (empty($export_id) ? get_request_var('id') : $export_id));
	}
}

function duplicate_export($_export_id, $export_title) {
	global $fields_export_edit;

	$export = db_fetch_row_prepared('SELECT * FROM graph_exports WHERE id = ?', array($_export_id));

	/* substitute the title variable */
	$export['name'] = str_replace('<export_title>', $export['name'], $export_title);

	/* create new entry: device_template */
	$save['id']   = 0;

	reset($fields_export_edit);
	foreach($fields_export_edit as $field => $array) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $export[$field];
		}
	}

	$export_id = sql_save($save, 'export');
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function export_form_actions() {
	global $export_actions;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') === '1') { /* delete */
				/* do a referential integrity check */
				if (sizeof($selected_items)) {
					foreach($selected_items as $export_id) {
						/* ================= input validation ================= */
						input_validate_input_number($export_id);
						/* ==================================================== */

						$export_ids[] = $export_id;
					}
				}

				if (isset($export_ids)) {
					db_execute('DELETE FROM graph_exports WHERE ' . array_to_sql_or($export_ids, 'id'));
				}
			} elseif (get_nfilter_request_var('drp_action') === '2') { /* enable */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					export_enable($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') === '3') { /* disable */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					export_disable($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') === '4') { /* run now */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					export_runnow($selected_items[$i]);
				}

				header('Location: gexport.php?header=false&refresh=20');

				exit;
			}
		}

		header('Location: gexport.php?header=false');

		exit;
	}

	/* setup some variables */
	$export_list = '';

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$export_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM graph_exports WHERE id = ?', array($matches[1])) . '</li>';
			$export_array[] = $matches[1];
		}
	}

	top_header();

	form_start('gexport.php', 'export_actions');

	html_start_box($export_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($export_array)) {
		if (get_nfilter_request_var('drp_action') === '1') { /* delete */
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to delete the following Graph Export Definition.', 'Click \'Continue\' to delete following Graph Export Definitions.', sizeof($export_array), 'gexport') . "</p>
						<div class='itemlist'><ul>$export_list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel', 'gexport') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Graph Export Definition(s)'>";
		} elseif (get_nfilter_request_var('drp_action') === '2') { /* disable */
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to disable the following Graph Export Definition.', 'Click \'Continue\' to disable following Graph Export Definitions.', sizeof($export_array), 'gexport') . "</p>
						<div class='itemlist'><ul>$export_list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel', 'gexport') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'gexport') . "' title='" . __esc('Disable Graph Export Definition(s)', 'gexport') . "'>";
		} elseif (get_nfilter_request_var('drp_action') === '3') { /* enable */
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to enable the following Graph Export Definition.', 'Click \'Continue\' to enable following Graph Export Definitions.', sizeof($export_array), 'gexport') . "</p>
						<div class='itemlist'><ul>$export_list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel', 'gexport') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'gexport') . "' title='" . __esc('Enable Graph Export Definition(s)', 'gexport') . "'>";
		} elseif (get_nfilter_request_var('drp_action') === '4') { /* export now */
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to run the following Graph Export Definition now.', 'Click \'Continue\' to run following Graph Export Definitions now.', sizeof($export_array)) . "</p>
						<div class='itemlist'><ul>$export_list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel', 'gexport') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'gexport') . "' title='" . __esc('Run Graph Export Definition(s) Now', 'gexport') . "'>";
		}
	} else {
		print "<tr><td class='odd'><span class='textError'>" . __('You must select at least one Graph Export Definition.', 'gexport') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __esc('Return', 'gexport') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($export_array) ? serialize($export_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ---------------------
    Graph Export Functions
   --------------------- */

function export_enable($export_id) {
	db_execute_prepared('UPDATE graph_exports SET enabled="on" WHERE id = ?', array($export_id));
}

function export_disable($export_id) {
	db_execute_prepared('UPDATE graph_exports SET enabled="" WHERE id = ?', array($export_id));
}

function export_runnow($export_id) {
	global $config;

	include_once('./lib/poller.php');

	$status = db_fetch_row_prepared('SELECT status, enabled FROM graph_exports WHERE id = ?', array($export_id));

	if (($status['status'] == 0 || $status['status'] == 2) && $status['enabled'] == 'on') {
		$command_string = read_config_option('path_php_binary');
		$extra_args = '-q "' . $config['base_path'] . '/plugins/gexport/poller_export.php" --id=' . $export_id . ' --force';
		exec_background($command_string, $extra_args);

		sleep(2);
	}
}

function export_edit() {
	global $fields_export_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$export = db_fetch_row_prepared('SELECT * FROM graph_exports WHERE id = ?', array(get_request_var('id')));
		$header_label = __('Graph Export Definition [edit: %s]', $export['name'], 'gexport');
	} else {
		$header_label = __('Graph Export Definition [new]', 'gexport');
	}

	form_start('gexport.php', 'export_edit');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_export_edit, (isset($export) ? $export : array()))
		)
	);

	html_end_box();

	form_hidden_box('id', (isset($export['id']) ? $export['id'] : '0'), '');
	form_hidden_box('save_component_export', '1', '');

	form_save_button('gexport.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		$('#graph_tree').multiselect({
			noneSelectedText: '<?php print __('Select Tree(s)', 'gexport');?>',
			selectedText: function(numChecked, numTotal, checkedItems) {
				myReturn = numChecked + ' <?php print __('Trees Selected', 'gexport');?>';
				$.each(checkedItems, function(index, value) {
					if (value.value == '0') {
						myReturn='<?php print __('All Trees Selected', 'gexport');?>';
						return false;
					}
				});
				return myReturn;
			},
			checkAllText: '<?php print __('All', 'gexport');?>',
			uncheckAllText: '<?php print __('None', 'gexport');?>',
			uncheckall: function() {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).prop('checked', true);
				});
			},
			click: function(event, ui) {
				checked=$(this).multiselect('widget').find('input:checked').length;

				if (ui.value == '0') {
					if (ui.checked == true) {
						$('#graph_tree').multiselect('uncheckAll');
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).prop('checked', true);
						});
					}
				}else if (checked == 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
					});
				}else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
					if (checked > 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
							$(this).prop('disable', true);
						});
					}
				}
			}
		}).multiselectfilter( {
			label: '<?php print __('Search', 'gexport');?>', width: '150'
		});

		$('#graph_site').multiselect({
			noneSelectedText: '<?php print __('Select Site(s)', 'gexport');?>',
			selectedText: function(numChecked, numTotal, checkedItems) {
				myReturn = numChecked + ' <?php print __('Sites Selected', 'gexport');?>';
				$.each(checkedItems, function(index, value) {
					if (value.value == '0') {
						myReturn='<?php print __('All Sites Selected', 'gexport');?>';
						return false;
					}
				});
				return myReturn;
			},
			checkAllText: '<?php print __('All', 'gexport');?>',
			uncheckAllText: '<?php print __('None', 'gexport');?>',
			uncheckall: function() {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).prop('checked', true);
				});
			},
			click: function(event, ui) {
				checked=$(this).multiselect('widget').find('input:checked').length;

				if (ui.value == '0') {
					if (ui.checked == true) {
						$('#graph_site').multiselect('uncheckAll');
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).prop('checked', true);
						});
					}
				}else if (checked == 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
					});
				}else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
					if (checked > 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
							$(this).prop('disable', true);
						});
					}
				}
			}
		}).multiselectfilter( {
			label: '<?php print __('Search', 'gexport');?>', width: '150'
		});

		setRemoteVisibility();
		setTimingVisibility();
		setTreeVisibility();

		$('#export_type').change(function() {
			setRemoteVisibility();
		});

		$('#export_timing').change(function() {
			setTimingVisibility();
		});

		$('#export_presentation').change(function() {
			setTreeVisibility();
		});
	});

	function setTimingVisibility() {
		if ($('#export_timing').val() == 'periodic') {
			$('#row_export_skip').show();
			$('#row_export_hourly').hide();
			$('#row_export_daily').hide();
		}else if ($('#export_timing').val() == 'hourly') {
			$('#row_export_skip').hide();
			$('#row_export_hourly').show();
			$('#row_export_daily').hide();
		}else if ($('#export_timing').val() == 'daily') {
			$('#row_export_skip').hide();
			$('#row_export_hourly').hide();
			$('#row_export_daily').show();
		} else {
			$('#row_export_skip').hide();
			$('#row_export_hourly').hide();
			$('#row_export_daily').hide();
		}
	}

	function setRemoteVisibility() {
		if ($('#export_type').val() != 'local') {
			$('#row_export_temp_directory').show();
			$('#row_export_hdr_remote').show();
			$('#row_export_sanitize_remote').show();
			$('#row_export_host').show();
			$('#row_export_port').show();
			$('#row_export_user').show();

			if ($('#export_type').val() == 'sftp' || $('#export_type').val() == 'ftp') {
				$('#row_export_password').show();
				$('#row_export_passive').show();
			} else {
				$('#row_export_password').hide();
				$('#row_export_passive').hide();
			}

			if ($('#export_type').val() == 'rsync' || $('#export_type').val() == 'scp') {
				$('#row_export_private_key_path').show();
			} else {
				$('#row_export_private_key_path').hide();
			}
		} else {
			$('#row_export_temp_directory').hide();
			$('#row_export_hdr_remote').hide();
			$('#row_export_sanitize_remote').hide();
			$('#row_export_host').hide();
			$('#row_export_port').hide();
			$('#row_export_user').hide();
			$('#row_export_password').hide();
			$('#row_export_passive').hide();
			$('#row_export_private_key_path').hide();
		}
	}

	function setTreeVisibility() {
		if ($('#export_presentation').val() == 'tree') {
			$('#row_graph_tree').show();
			$('#row_export_expand_hosts').show();
			$('#row_graph_site').hide();
		} else {
			$('#row_graph_tree').hide();
			$('#row_export_expand_hosts').hide();
			$('#row_graph_site').show();
		}
	}

	</script>
	<?php
}

function export_filter() {
	global $item_rows;

	html_start_box(__('Graph Export Definitions', 'gexport'), '100%', '', '3', 'center', 'gexport.php?action=edit');
	?>
	<tr class='even'>
		<td>
			<form id='form_export' action='gexport.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'gexport');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Exports', 'gexport');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default', 'gexport');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh' onChange='applyFilter()'>
							<?php
							$frequency = array(
								99999999 => __('Never', 'gexport'),
								10       => __('%d Seconds', 10, 'gexport'),
								20       => __('%d Seconds', 20, 'gexport'),
								30       => __('%d Seconds', 30, 'gexport'),
								45       => __('%d Seconds', 45, 'gexport'),
								60       => __('%d Minute', 1, 'gexport'),
								120      => __('%d Minutes', 2, 'gexport'),
								300      => __('%d Minutes', 5, 'gexport')
							);

							foreach ($frequency as $r => $row) {
								echo "<option value='" . $r . "'" . (isset_request_var('refresh') && $r == get_request_var('refresh') ? ' selected' : '') . '>' . $row . '</option>';
							}
							?>
						</select>
					<td>
						<span>
							<input type='submit' Value='<?php print __x('filter: use', 'Go', 'gexport');?>' id='go'>
							<input type='button' Value='<?php print __x('filter: reset', 'Clear', 'gexport');?>' id='clear'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'gexport.php?header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&refresh='+$('#refresh').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'gexport.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#has_graphs').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_export').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();
}

function get_export_records(&$total_rows, &$rows) {
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM graph_exports $sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	return db_fetch_assoc("SELECT *
		FROM graph_exports
		$sql_where
		$sql_order
		$sql_limit");
}

function gexport() {
	global $export_actions;

	$running = db_fetch_cell('SELECT COUNT(*)
		FROM graph_exports
		WHERE export_pid > 0
		AND status > 0');

	if ($running == 0) {
		set_request_var('refresh', 99999999);
	}

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '20'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_gexport');
	/* ================= input validation ================= */

	$refresh            = array();
	$refresh['page']    = 'gexport.php?header=false';
	$refresh['seconds'] = get_request_var('refresh');
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	export_filter();

	$total_rows = 0;
	$exports = array();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$exports = get_export_records($total_rows, $rows);

	$display_text = array(
		'name' => array(
			'display' => __('Export Name', 'gexport'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The name of this Graph Export Definition.', 'gexport')
		),
		'id' => array(
			'display' => __('ID', 'gexport'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The internal ID of the Graph Export Definition.', 'gexport')
		),
		'export_timing' => array(
			'display' => __('Schedule', 'gexport'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The frequency that Graphs will be exported.', 'gexport')
		),
		'next_start' => array(
			'display' => __('Next Start', 'gexport'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The next time the Graph Export should run.', 'gexport')
		),
		'nosort' => array(
			'display' => __('Enabled', 'gexport'),
			'align' => 'right',
			'tip' => __('If enabled, this Graph Export definition will run as required.', 'gexport')
		),
		'status' => array(
			'display' => __('Status', 'gexport'),
			'align' => 'right',
			'tip' => __('The current Graph Export Status.', 'gexport')
		),
		'nosort1' => array(
			'display' => __('Exporting (Sites/Trees)', 'gexport'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('What is being Exported.', 'gexport')
		),
		'export_effective_user' => array(
			'display' => __('Effective User', 'gexport'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The user that this export will impersonate.', 'gexport')
		),
		'last_runtime' => array(
			'display' => __('Last Runtime', 'gexport'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The last runtime for the Graph Export.', 'gexport')
		),
		'total_graphs' => array(
			'display' => __('Graphs', 'gexport'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The number of Graphs Exported on the last run.', 'gexport')
		),
		'last_started' => array(
			'display' => __('Last Started', 'gexport'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The last time that this Graph Export was started.', 'gexport')
		),
		'last_errored' => array(
			'display' => __('Last Errored', 'gexport'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The last time that this Graph Export experienced an error.', 'gexport')
		)
	);

	$nav = html_nav_bar('gexport.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, sizeof($display_text) + 1, __('Export Definitions', 'gexport'), 'page', 'main');

	form_start('gexport.php', 'chk');

    print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($exports)) {
		foreach ($exports as $export) {
			$user = db_fetch_cell_prepared('SELECT username
				FROM user_auth
				WHERE id = ?',
				array($export['export_effective_user']));

			if ($export['export_pid'] > 0 && $export['status'] > 0) {
				if (function_exists('posix_getpgid')) {
					$running = posix_getpgid($export['export_pid']);
				} elseif (function_exists('posix_kill')) {
					$running = posix_kill($export['export_pid'], 0);
				}

				if (!$running) {
					db_execute_prepared('UPDATE graph_exports
						SET status=0, export_pid=0, last_error="Killed Outside Cacti", last_errored=NOW()
						WHERE id = ?',
						array($export['id']));
				}
			}

			form_alternate_row('line' . $export['id'], true);
			form_selectable_cell(filter_value($export['name'], get_request_var('filter'), 'gexport.php?action=edit&id=' . $export['id']), $export['id']);
			form_selectable_cell($export['id'], $export['id'], '', 'text-align:right');

			form_selectable_cell(__(ucfirst($export['export_timing']), 'gexport'), $export['id'], '', 'text-align:right');

			form_selectable_cell($export['enabled'] == '' ? __('N/A', 'gexport'):substr($export['next_start'], 5, 11), $export['id'], '', 'text-align:right');

			form_selectable_cell($export['enabled'] == '' ? __('No', 'gexport'):__('Yes', 'gexport'), $export['id'], '', 'text-align:right');

			switch($export['status']) {
			case '0':
				form_selectable_cell("<span class='idle'>" .  __('Idle', 'gexport') . "</span>", $export['id'], '', 'text-align:right');
				break;
			case '1':
				form_selectable_cell("<span class='running'>" .  __('Running', 'gexport') . "</span>", $export['id'], '', 'text-align:right');
				break;
			case '2':
				form_selectable_cell("<span class='errored'>" .  __('Error', 'gexport') . "</span>", $export['id'], '', 'text-align:right');
				break;
			}

			if ($export['export_presentation'] == 'preview') {
				if ($export['graph_site'] == '0') {
					form_selectable_cell(__('All Sites', 'gexport'), $export['id'], '', 'text-align:right');
				} else {
					if ($export['graph_site'] != '') {
						$sites = db_fetch_cell('SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR ", ") FROM sites WHERE id IN(' . $export['graph_site'] . ')');
					} else {
						$sites = '';
					}
					form_selectable_cell($sites, $export['id'], '', 'text-align:right');
				}
			} else {
				if ($export['graph_tree'] == '0') {
					form_selectable_cell(__('All Trees', 'gexport'), $export['id'], '', 'text-align:right');
				} else {
					if ($export['graph_tree'] != '') {
						$trees = db_fetch_cell('SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR ", ") FROM graph_tree WHERE id IN(' . $export['graph_tree'] . ')');
					} else {
						$trees = '';
					}
					form_selectable_cell($trees, $export['id'], '', 'text-align:right');
				}
			}

			form_selectable_cell($export['export_effective_user'] == 0 ? __('N/A', 'gexport'):$user, $export['id'], '', 'text-align:right');

			if ($export['last_started'] != '0000-00-00 00:00:00') {
				form_selectable_cell(round($export['last_runtime'],2) . ' ' . __('Sec', 'gexport'), $export['id'], '', 'text-align:right');
				form_selectable_cell(number_format_i18n($export['total_graphs']), $export['id'], '', 'text-align:right');
				form_selectable_cell(substr($export['last_started'], 5, 11), $export['id'], '', 'text-align:right');

				if ($export['last_errored'] != '0000-00-00 00:00:00') {
					form_selectable_cell(substr($export['last_errored'], 5, 11), $export['id'], '', 'text-align:right', $export['last_error']);
				} else {
					form_selectable_cell(__('Never', 'gexport'), $export['id'], '', 'text-align:right');
				}
			} else {
				form_selectable_cell(__('N/A', 'gexport'), $export['id'], '', 'text-align:right');
				form_selectable_cell(__('N/A', 'gexport'), $export['id'], '', 'text-align:right');
				form_selectable_cell(__('Never', 'gexport'), $export['id'], '', 'text-align:right');
				form_selectable_cell(__('Never', 'gexport'), $export['id'], '', 'text-align:right');
			}

			form_checkbox_cell($export['name'], $export['id']);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='4'><em>" . __('No Graph Export Definitions', 'gexport') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (sizeof($exports)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($export_actions);

	form_end();
}

