<?php
	// Fill me in
	$sqlHost     = "localhost";     // Hostname of your SQL server, or "localhost"
	$sqlUser     = "fastuser";     // SQL user name
	$sqlPass     = "fastpass";     // SQL password
	$sqlDatabase = "sporttrader";     // Database name on SQL server
	$sqlPrefix   = "";     // A prefix to use on all table names, optional but it help you sort your tables
	$dbmDebug    = false;  // Set to true if you want to enable debugging output
	$dbmAutoDrop = false;  // Set to true if you want DBM to automatically drop columns that you remove from your table_defs (SCARY)
	$dbmTableDrop = false; // Set to true if you want to enable the Execution::dropTable() function.

	// Allow config consts defined before this file. Needed to run test.php.  Possible security risk.
	$dbmAllowConfOverrides = true;

?>
