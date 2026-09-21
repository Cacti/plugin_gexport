# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`gexport`, version 2.1) targeting Cacti 1.2.15+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: Compatible with Cacti 1.2.x supported versions
- **Platform**: Cacti Plugin Architecture (Cacti 1.2.15+)
- **Database**: MySQL/MariaDB with InnoDB engine
- **Transfer**: Exports graphs via rsync/scp/sftp to remote destinations

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`, `exec_background()`)
- Optional: `gettext` for internationalization

## Project Structure

```
gexport/               # Repository root (install to plugins/gexport/ in Cacti)
├── locales/             # Internationalization files
├── functions.php          # Export engine: run_export(), exporter(), rsync/scp helpers
├── gexport.php              # Main export definition administration UI
├── poller_export.php         # Background export runner (CLI, spawned from poller_bottom)
├── website.template            # HTML template used for exported site view
├── INFO                          # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                      # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
- **Plugin lifecycle/hook-registration functions** MUST be prefixed `plugin_gexport_`: `plugin_gexport_install()`, `plugin_gexport_upgrade()`, `plugin_gexport_version()`.
- **All other functions** MUST be prefixed `gexport_` (hooks) or use the established `export_*` prefix for the export engine itself (`export_fatal()`, `export_warn()`, `export_log()`, `export_graphs()`, `export_rsync_execute()`, `export_scp_execute()`).
- Match the existing prefix used by the function you are editing; do not introduce a fourth naming scheme.

### Database Tables
This plugin intentionally uses **unprefixed, core-style table names** (not `plugin_gexport_*`): `graph_exports`, `graph_exports_tasks`. Preserve this naming when adding columns/tables; do not rename to a `plugin_gexport_` prefix.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
Use prepared statements for anything involving variable input:

```php
// CORRECT
db_column_exists('graph_exports', 'export_index_key_path');

// WRONG - never do this with request-derived values
db_fetch_row("SELECT * FROM graph_exports WHERE id = $id");
```

### Shell/Transfer Security
`export_rsync_execute()`/`export_scp_execute()` shell out to `rsync`/`scp`. Always pass paths/hosts through `cacti_escapeshellarg()` and never build the command line by concatenating raw export configuration without escaping.

### Input Validation
Use `get_filter_request_var()` / `get_nfilter_request_var()` for request input; never read `$_GET`/`$_POST` directly.

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

## Database Operations

### Upgrade Handling
Version-gate schema changes in `gexport_check_upgrade()` (`setup.php`) using `cacti_version_compare()`, guarded with `db_column_exists()`/`db_index_exists()`:

```php
function gexport_check_upgrade() {
	global $config, $database_default;

	$info    = plugin_gexport_version();
	$current = $info['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='gexport'");

	if (cacti_version_compare($old, $current, '<')) {
		if (api_plugin_is_enabled('gexport')) {
			api_plugin_enable_hooks('gexport');
		}

		if (cacti_version_compare($old, '1.4.1', '<')) {
			if (db_column_exists('graph_exports', 'export_index_key_path')) {
				db_execute('ALTER TABLE graph_exports CHANGE COLUMN `export_index_key_path` `export_private_key_path` varchar(255)');
			}
		}
	}
}
```

## Internationalization

ALL user-facing strings MUST use `__()` with the `'gexport'` text domain.

## Plugin Architecture

### Plugin Hooks
Register all plugin hooks in `plugin_gexport_install()` (`setup.php`):

```php
api_plugin_register_hook('gexport', 'config_arrays',        'gexport_config_arrays',        'setup.php');
api_plugin_register_hook('gexport', 'draw_navigation_text', 'gexport_draw_navigation_text', 'setup.php');
api_plugin_register_hook('gexport', 'poller_bottom',        'gexport_poller_bottom',        'setup.php');

api_plugin_register_realm('gexport', 'gexport.php', __('Export Cacti Graphs Settings', 'gexport'), 1);
```

### Poller Integration
`gexport_poller_bottom()` only fires on `poller_id == 1` and only spawns `poller_export.php` in the background if at least one `graph_exports` row is `enabled='on'`; keep new export-triggering logic behind the same guard to avoid running exports on every poller cycle needlessly.

### Export Engine Conventions
Logging inside the export engine uses a family of small helpers — `export_fatal()`, `export_warn()`, `export_note()`, `export_log()`, `export_debug()` — keep using the matching severity helper rather than calling `cacti_log()` directly from within `functions.php`.

## Best Practices

1. Preserve the unprefixed `graph_exports`/`graph_exports_tasks` table names.
2. Escape all shell arguments passed to `rsync`/`scp` via `cacti_escapeshellarg()`.
3. Use the existing `export_*` logging helper family for consistency.
4. Wrap all user-facing strings with `__('text', 'gexport')`.

## Common Pitfalls to Avoid

```php
// WRONG - building shell commands from unescaped config
exec("scp " . $export['path'] . " " . $export['host'] . ":" . $export['remote_path']);

// CORRECT
exec('scp ' . cacti_escapeshellarg($export['path']) . ' ' . cacti_escapeshellarg($export['host'] . ':' . $export['remote_path']));
```

## Version Control

Document all changes in `CHANGELOG.md`; use descriptive commit messages referencing issue/PR numbers when applicable.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## Internationalization (i18n)

- Translatable strings are managed with GNU gettext via `locales/build_gettext.sh`. `locales/po/cacti.pot` is the source template; Weblate owns syncing the per-language `.po`/`.mo` files from it.
- When a pull request adds or changes a string wrapped in `__()`/`__n()`/`__esc()`/`__x()`/`__xn()`/`__gettext()`, run `locales/build_gettext.sh` before pushing and add the resulting change to `locales/po/cacti.pot` only. Do not commit the regenerated per-language `.po`/`.mo` files in the same PR — Weblate takes care of the rest.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history
