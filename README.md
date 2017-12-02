# gexport

The gexport plugin replaces the Graph Export in legacy Cacti.  This plugin was removed from the core of Cacti in that several users did not utilize this functionality.  However, this new version not only exports the Graph and Tree's, it allows dynamic resizing of graphs to fit your display, and allows users to switch dynamically between full view and preview modes.

Due to it's new design, it is also much faster and more reliable than previous versions.  You can still have the Graph Exports content impersonate any login user, but you can now define as many exports as you wish, each with their own export schedule and destination.  In addition to the Traditional Tree View export, there is now a Site View export mode that exports all devices inside of a site that the impersonated user has access to.  This makes setting up Graph Exports more convenient.

## Purpose

This plugin allows Cacti Graphs to be Exported and replaces the Graph Export functionality that was removed from the Core of Cacti.

## Features

Allows you to Export Cacti Graphs, Graph Tree's and Sites.  Multiple Graph Exports can be performed each impersonating a different users permissions.  Graph Export contents can be transferred to remote sites using FTP, SFTP, RSYNC, and SCP.
	
## Installation

Install just like any other plugin, just copy it to the plugins directory, rename it from 'plugin_gexport' to 'gexport', and then Install and Enable it from the Plugin Management Interface.

Once this is done, you can configure what Graph Trees or Sites to be exported and using which user.
    
## Possible Bugs?
   
If you figure out this problem, see the Cacti forums!

## Future Changes
    
Got any ideas or complaints, please create an issue in GitHub.

## Changelog

--- 1.3 ---
* issue#21: Remove ftp_delete warning
* issue: resolving issues with site export

--- 1.2 ---
* issue#12: jquery.storageapi.js not found

--- 1.1 ---
* issue#4: undefined index in site export
* issue#5: export user too narrow
* issue#6: export ftp does not function
* issue#7: rmdir warnings when performing cleanup
* issue: update text domains for i18n

--- 1.0 ---
Initial Release
