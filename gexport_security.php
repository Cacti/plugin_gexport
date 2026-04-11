<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

function gexport_normalize_bulk_action($value) {
	if (is_int($value)) {
		$action = $value;
	} elseif (is_string($value) && preg_match('/^[0-9]+$/', $value)) {
		$action = (int) $value;
	} else {
		return '';
	}

	if ($action < 1 || $action > 4) {
		return '';
	}

	return (string) $action;
}

function gexport_build_nav_filter_url($filter) {
	return 'gexport.php?filter=' . rawurlencode((string) $filter);
}
