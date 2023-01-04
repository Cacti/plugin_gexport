# gexport

The gexport plugin replaces the Graph Export in legacy Cacti.  This plugin was
removed from the core of Cacti in that several users did not utilize this
functionality.  However, this new version not only exports the Graph and Tree's,
it allows dynamic resizing of graphs to fit your display, and allows users to
switch dynamically between full view and preview modes.

Due to it's new design, it is also much faster and more reliable than previous
versions.  You can still have the Graph Exports content impersonate any login
user, but you can now define as many exports as you wish, each with their own
export schedule and destination.  In addition to the Traditional Tree View
export, there is now a Site View export mode that exports all devices inside of
a site that the impersonated user has access to.  This makes setting up Graph
Exports more convenient.

## Purpose

This plugin allows Cacti Graphs to be Exported and replaces the Graph Export
functionality that was removed from the Core of Cacti.

## Features

Allows you to Export Cacti Graphs, Graph Tree's and Sites.  Multiple Graph
Exports can be performed each impersonating a different users permissions. Graph
Export contents can be transferred to remote sites using FTP, SFTP, RSYNC, and
SCP.

## Installation

Install just like any other plugin, just copy it to the plugins directory,
rename it from 'plugin_gexport' to 'gexport', and then Install and Enable it
from the Plugin Management Interface.

Once this is done, you can configure what Graph Trees or Sites to be exported
and using which user.

## Possible Bugs

If you figure out this problem, see the Cacti forums!

## Future Changes

Got any ideas or complaints, please create an issue in GitHub.

-----------------------------------------------
Copyright (c) 2004-2023 - The Cacti Group, Inc.
