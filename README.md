# gexport

The gexport plugin replaces the Graph Export in legacy Cacti.  This plugin was
removed from the core of Cacti in that several users did not utilize this
functionality.  However, this new version not only exports the Graph and Tree's,
it allows dynamic resizing of graphs to fit your display, and allows users to
switch dynamically between full view and preview modes.

Each export has a schedule, destination and Cacti user whose permissions
determine the exported content. Tree and Site views let operators publish a
chosen set of graphs to a separate destination.

## Versions and compatibility

This README describes the `develop` source branch.

| Code | Plugin version | Minimum Cacti declared in `INFO` |
| --- | --- | --- |
| [Source branch](INFO) | 2.1 | 1.2.29 |
| [Published v2.1](https://github.com/Cacti/plugin_gexport/releases/tag/v2.1) | 2.1 | 1.2.15 |

Use the published release’s assets and tagged documentation when installing that
package. Branch code can include later changes, even when its version number
matches the release. Cacti’s own PHP and database requirements also apply.

Install this plugin in **`<cacti-root>/plugins/gexport`**, using the directory
name `gexport` rather than `plugin_gexport`.

See [CHANGELOG.md](CHANGELOG.md) for the version history.

## Purpose

This plugin allows Cacti Graphs to be Exported and replaces the Graph Export
functionality that was removed from the Core of Cacti.

## Features

Allows you to Export Cacti Graphs, Graph Tree's and Sites.  Multiple Graph
Exports can be performed each impersonating a different users permissions. Graph
Export contents can be transferred to remote sites using the Export Method
choices Local, FTP, FTP NC, SFTP, RSYNC, and SCP.

## Requirements

Requirements depend on the Export Method chosen:

* FTP and SFTP use PHP's built-in FTP functions, so the PHP `ftp` extension is
  required.  SFTP additionally needs `ftp_ssl_connect()`, which requires PHP
  built with OpenSSL support
* FTP NC runs `ncftpput` and is not available on Windows
* RSYNC and SCP run the `rsync` and `scp` binaries, which must be installed
  and in the poller's path

## Cacti compatibility

If you are running a version of Cacti below 1.2.31, please add the function
below to the `applySkin()` function in `include/layout.js` to enable the Cancel
buttons on forms to work:

```js
$(document).off('click.cactiReturnTo', '.cactiReturnTo')
    .on('click.cactiReturnTo', '.cactiReturnTo', function(event) {
        event.preventDefault();
        cactiReturnTo($(this).attr('data-url'));
    });
```

## Installation

Install just like any other plugin, just copy it to the plugins directory,
rename it from 'plugin_gexport' to 'gexport', and then Install and Enable it
from the Plugin Management Interface.

Once this is done, you can configure what Graph Trees or Sites to be exported
and using which user.

## Usage

Exports are managed under **Utilities -> Graph Exports**.  Access requires the
**Export Cacti Graphs Settings** realm, which is also granted to the General
Administration role.

Scheduled exports run from the poller's `poller_bottom` hook at the end of each
polling cycle.  To run them by hand, use `poller_export.php`:

```
php plugins/gexport/poller_export.php [--id=N] [--force] [--debug]
```

| Option | Effect |
| --- | --- |
| `--id=N` | Export only the Graph Export with this ID |
| `--force` | Run the export now, regardless of its schedule |
| `-d`, `--debug` | Display verbose output during execution |

The `--thread` option is used internally when the poller starts an export in
the background and is not intended to be passed by hand.

## Possible Bugs

If you figure out this problem, see the Cacti forums!

## Future Changes

Got any ideas or complaints, please create an issue in GitHub.

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
