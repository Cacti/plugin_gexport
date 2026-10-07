## Changelog

--- 2.1 ---

- security: Move the filter selects' inline onChange handlers into the ready block (bound via change, not click) so the page no longer trips Cacti's Content-Security-Policy script-src-attr directive
- refactor: Move the functions.php and gexport_security.php library files into includes/ and switch every file inclusion from include/include_once to require/require_once for fail-fast consistency (references updated across setup.php, gexport.php, poller_export.php, and the test suite)
- refactor: Move all schema management into includes/database.php (the thold model) and manage the graph_exports/graph_exports_tasks schema through Cacti's plugin table API - create with api_plugin_db_table_create() and refresh existing tables with db_update_table() from a single shared definition, replacing the raw CREATE TABLE/version-gated ALTER TABLE migrations (the one column rename is kept as a pre-step since db_update_table() can not rename)
- dev: Measure CI coverage with xdebug instead of pcov so the plugin's own sources are instrumented (pcov auto-scopes to the Composer root and skipped cacti/plugins/, leaving the patch-coverage gate with nothing to measure)
- dev: Enforce patch coverage of changed lines in CI and remove the inert COMPOSER_ROOT_VERSION env from the Pest step
- security: Add a version-safe CSP nonce (`plugin_gexport_csp_nonce()`) to every inline `<script>` tag so pages stay compatible with Cacti's Content-Security-Policy nonce enforcement, while falling back cleanly on older Cacti releases that lack the `CactiSecureHeaders` class

- issue: Fix undefined function call in export_ftp_rmdirr() (recursive
  call referenced the non-existent 'ftp_rmdirr' instead of
  'export_ftp_rmdirr', causing a fatal error whenever a non-empty remote
  directory needed to be sanitized before an FTP upload)

- issue: Fix gexport_calc_next_start() returning an undefined value for
  an unrecognized/disabled export timing setting

- issue: Fix write_branch_conf() writing to an undefined json file path
  for an unrecognized branch type

- issue: Fix export_form_actions() and duplicate_export()/export_edit()/
  export_runnow() not safely handling a missing/invalid bulk action or
  database row

- issue#54: CMDPHP PHP ERROR WARNING

- issue#55: PNG not readable


--- 2.0 ----

- issue#37: Empty site variable may cause errors to appear

- issue#38: Searching for graph exports does not work with some international
  characters

- issue#39: error in functions.php

- issue#40: graph_max set to 0 does not allow any exports

- issue#41: Additional logging in export_graphs function

- issue#44: Cacti Log Warning

- issue#46: Graphs not rendering on Cacti 1.2.15

- issue: Internationalization issues on console

- issue: Updating gexport to support Cacti 1.2.15+


--- 1.4.2 ---

- issue#37: Empty site variable may cause errors to appear

- issue#38: Searching for graph exports does not work with some international
  characters


--- 1.4.1 ---

- issue: Corrects column naming issue introduced in 1.4


--- 1.4 ---

- feature: Added option to clear final directory

- feature: Added option to disable thumbnail creation

- feature: Added standard error messages for RSYNC and SCP calls

- feature: Added command arguments as drop done selection for RSYNC and SCP

- feature: Now multi-threaded (when threads > 0)

- issue: output from exec calls not handled properly

- issue: Fix up db checks to correctly identify upgrades required

- issue: Clear system cache before file checks

- issue: Too many logs, alter logging level for some messages


--- 1.3 ---

- issue#21: Remove ftp_delete warning

- issue: resolving issues with site export


--- 1.2 ---

- issue#12: jquery.storageapi.js not found


--- 1.1 ---

- issue#4: undefined index in site export

- issue#5: export user too narrow

- issue#6: export ftp does not function

- issue#7: rmdir warnings when performing cleanup

- issue: update text domains for i18n


--- 1.0 ---

- Initial Release

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
