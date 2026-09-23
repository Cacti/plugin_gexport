<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Validates and normalizes a submitted bulk-action value to one of this
 * plugin's four known action codes, rejecting anything else. Called from
 * gexport.php's bulk-actions handler before acting on a submitted
 * 'drp_action' value, to guard against an attacker-supplied action code.
 *
 * @param mixed $value The candidate bulk-action value.
 *
 * @return string The normalized action code as a numeric string ('1'-'4'),
 *                or '' when $value isn't a valid action code.
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

/**
 * Builds a safely-encoded URL for the Graph Export list filtered by a
 * given search term. Called from gexport.php while rendering navigation/
 * pagination links that need to preserve the current filter value.
 *
 * @param mixed $filter The filter/search term to embed in the URL.
 *
 * @return string The encoded 'gexport.php?filter=...' URL.
 */
function gexport_build_nav_filter_url($filter) {
	return 'gexport.php?filter=' . rawurlencode((string) $filter);
}
