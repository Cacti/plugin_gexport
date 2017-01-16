# gexport

The Cacti gexport plugin replaces Cacti's built-in Graph Export functionality with a plugin.  For the Cacti 1.0 release, the Cacti Developers decided to make Cacti's legacy Graph Export into a plugin as this functionality is not used by all Cacti users.  Therefore, the main Cacti package no longer includes Graph Export functionality.

However, this new version of Graph Export in Cacti adds several new features, and will be enhanced over time to add more planned functionality.

The new features include:

* Use of Cacti's new Tree structure, 
* Support for multiple graph exports with different effective users and timing,
* Redesigned graph file creation algorithm to optimize the creation of graphs, 
* A new 'Site' export option that allows the export of Sites in a Tree fashion allowing the exporter to simply publish sites instead of having to create Cacti Tree's to represent Tree content,
* Support of ajax page rendering including features such as pagination, thumbnail view, and columns per row
* RSYNC and SCP remote site export opions including support for custom private keys for user impersonation.

All these features make the gexport plugin an essential add-on for Cacti.

## Installation

The gexport plugin installs just like any other Cacti plugin.  Simply download this package, untar it to Cacti's plugins directory, and rename the direcotry to simply 'gexport'.  Once this is done, you finish the install from Cacti's Plugin Management interface.  You may be required to add your users or groups to the Graph Export realm in Cacti before creating your first export.
  
## Bugs or Feature Requests
   
If you figure out this problem with gexport, or would like new features, open an issue in GitHub, but remember, search the Cacti forums before opening a defect.  You solution may simply be a usage tip.

## Changelog

--- 1.0 ---
* Initial Release
