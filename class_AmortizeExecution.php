<?php

/*******************************************
	Copyright Rich Bellamy, RMB Webs, 2008
	Contact: rich@rmbwebs.com

	This file is part of Amortize.

	Amortize is free software: you can redistribute it and/or modify
	it under the terms of the GNU Lesser General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Amortize is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Lesser General Public License for more details.

	You should have received a copy of the GNU Lesser General Public License
	along with Amortize.  If not, see <http://www.gnu.org/licenses/>.
*******************************************/

$configFile = dirname(__FILE__) . '/../amortizeconfig.php';
require_once $configFile;

	/***************************************   Non-user-servicable parts below   ********************************************/
  // SQL options
  if (!defined('SQL_HOST'))         { define('SQL_HOST',         $sqlHost); }
		else if (!$dbmAllowConfOverrides)  { die('SQL_HOST was previously defined as "'.SQL_HOST.'" and that is not allowed per '.$configFile); }

	if (!defined('SQL_USER'))         { define('SQL_USER',         $sqlUser); }
		else if (!$dbmAllowConfOverrides)  { die('SQL_USER was previously defined as "'.SQL_USER.'" and that is not allowed per '.$configFile); }

	if (!defined('SQL_PASS'))         { define('SQL_PASS',         $sqlPass); }
		else if (!$dbmAllowConfOverrides)  { die('SQL_PASS was previously defined as "'.SQL_PASS.'" and that is not allowed per '.$configFile); }

	if (!defined('SQL_DBASE'))        { define('SQL_DBASE',        $sqlDatabase); }
		else if (!$dbmAllowConfOverrides)  { die('SQL_DBASE was previously defined as "'.SQL_DBASE.'" and that is not allowed per '.$configFile); }

	if (!defined('SQL_TABLE_PREFIX')) { define('SQL_TABLE_PREFIX', $sqlPrefix); }
		else if (!$dbmAllowConfOverrides)  { die('SQL_TABLE_PREFIX was previously defined as "'.SQL_TABLE_PREFIX.'" and that is not allowed per '.$configFile); }

	// General Options
	if (!defined('DBM_DEBUG'))        { define('DBM_DEBUG',        $dbmDebug); }
		else if (!$dbmAllowConfOverrides)  { die('DBM_DEBUG was previously defined as "'.DBM_DEBUG.'" and that is not allowed per '.$configFile); }

	if (!defined('DBM_AUTODROP'))     { define('DBM_AUTODROP',     $dbmAutoDrop); }
		else if (!$dbmAllowConfOverrides)  { die('DBM_AUTODROP was previously defined as "'.DBM_AUTODROP.'" and that is not allowed per '.$configFile); }

	if (!defined('DBM_DROP_TABLES'))     { define('DBM_DROP_TABLES',     $dbmTableDrop); }
		else if (!$dbmAllowConfOverrides)  { die('DBM_DROP_TABLES was previously defined as "'.DBM_DROP_TABLES.'" and that is not allowed per '.$configFile); }

require_once dirname(__FILE__) . '/amortizeutils.php';


/// A class for doing SQL operations automatically on a particular table
/**
 * This class is meant to be extended and a table_defs set in the extended class.  SQL queries can be passed to it with the makeQueryHappen
 * method.  This class will attempt to do everything necessary to make the query happen, including creating the table if it doesn't exist
 * and adding columns to the table if the table is missing a column.
 * The purpose of this object is to provide a vehicle for developers to develop an SQL application without having to maintain their database
 * even when they change their code.  When table_defs are altered in code, the database will be altered as need be.
 */
class AmortizeExecution {

  /// An array that determines how the data for this object will be stored in the database or a string of an existing table name
  /**
   * Possible formats for the array are:
   *   array('tablename' => array('collumn1name' => array('type', NULL, key, default, extras), column2name => array(...), ...))
   *   array('tablename' => array('column1name' => 'type', column2name => 'type', ...)
   *   "tablename"
   * If only a string with tablename is used, the object will use the "DESCRIBE tablename;" query to learn table_defs from an
   * existing table.  This will slow your program but is a very fast way to setup connecting to an existing database table that
   * you don't plan on modifying.
   */
  private $table_defs = null;

	protected $sql_pass  = SQL_PASS;
	protected $sql_user  = SQL_USER;
	protected $sql_host  = SQL_HOST;
	protected $sql_dbase = SQL_DBASE;
	protected $sql_prfx  = SQL_TABLE_PREFIX;
	protected $can_drop_table = DBM_DROP_TABLES;

	/**
	 * Constructor for this class
	 */
	public function __construct() {
		$this->setTableDefs();
	}

	/// Returns the table definitions for this object
	public function getTableDefs() {
		return $this->table_defs;
	}

	/**
		* Returns the Full Table Name (prefix + table name)
		*/
	public function getFullTableName() {
		return $this->sql_prfx.$this->getTableName();
	}

	/// Sets the table definitions for this object
	protected function setTableDefs($defs=null) {
		$defs = (empty($defs)) ? $this->table_defs : $defs;
		if (is_string($defs)) { // Allow use of existing tables
			$this->table_defs = $defs;
			$this->table_defs = array($this->getTableName() => $this->getActualTableDefs());
		} else if (is_array($defs)) {  // Purify table definition
			foreach ($defs as $tableName => $tableDef) {
				foreach ($tableDef as $colName => $colDef) {
					$tableDef[$colName] = (is_array($colDef)) ? $colDef : array($colDef, "YES", "", "", "");
				}
				$defs[$tableName] = $tableDef;
			}
			$this->table_defs = $defs;
		} else {
			// Nothing
		}
	}

	/************************  Protected Member Function Below  **************************/

	/**
	 * returns the name of the primary key for a particular row list
	 */
	protected function findKey($def = null) {
		$def = (is_array($def)) ? $def : array();
		foreach ($def as $field => $details) {
			if ($details[2] == "PRI")
				return $field;
		}
		return null;
	}

	/**
	 * returns the definition of the primary key for a particular row list
	 */
	protected function findKeyDef($def = null) {
		$def = (is_array($def)) ? $def : array();
		foreach ($def as $field => $details) {
			if ($details[2] == "PRI")
				return $details;
		}
		return null;
	}

	/**
	 * Returns the primary key name for this object's table
	 * Optionally takes a table definition as an argument to use instead of this objects table def
	 */
	protected function findTableKey($defs = null) {
		$defs = (is_null($defs)) ? $this->table_defs : $defs;
		return $this->findKey(first_val($defs));
	}

	/**
	 * Returns the primary key definition for this object's table
	 * Optionally takes a table definition as an argument to use instead of this objects table def
	 */
	protected function findTableKeyDef($defs = null) {
		$defs = (is_null($defs)) ? $this->table_defs : $defs;
		return $this->findKeyDef(first_val($defs));
	}

	/**
	 * Returns the name that this object saves under
	 * Optionally takes a table definition as an argument to use instead of this objects table def
	 */
	protected function getTableName($defs = null) {
		$defs = (empty($defs)) ? $this->table_defs : $defs;
		if (is_array($defs)) {
			return first_key($defs);
		} else if (is_string($defs)) {
			return $defs;
		} else {
			return false;
		}
	}

	/**
	* Returns the creation definition for a table column
	*/
	protected function getCreationDefinition($field, $details) {
		if (!is_array($details)) { $details = array($details); }

		$type    = isset($details[0]) ? $details[0] : "tinytext";
		$null    = isset($details[1]) ? $details[1] : "YES";
		$key     = isset($details[2]) ? $details[2] : "";
		$default = isset($details[3]) ? $details[3] : "";
		$extra   = isset($details[4]) ? $details[4] : "";

		if ($null == "NO") { $nullOut = "NOT NULL"; }
		else               { $nullOut = "";         }
		if ($default == "") { $defaultOut = "";                           }
		else                { $defaultOut = "DEFAULT '" . $default . "'"; }

		$return = "`{$field}` {$type} {$nullOut} {$defaultOut} {$extra}";
		return $return;
	}

	/**
	* getTableCreateQuery()
	* returns the query string that can be used to create a table based on it's definition
	*/
	protected function getTableCreateQuery() {
		$defs = $this->table_defs;
		$tableName = first_key($defs);

		if (! isset($defs[$tableName])) {
			return NULL;
		}

		$table_def = $defs[$tableName];

		$columns = array();
		$pri = array();

		foreach ($table_def as $field => $details) {
			$columns[] = $this->getCreationDefinition($field, $details);
			if ($details[2] == "PRI") {
				$pri[] = "`{$field}`";
			}
		}

		if (count($pri) > 0) { $columns[] = "PRIMARY KEY (".implode(",", $pri).")"; }

		return
			"CREATE TABLE `{$this->sql_prfx}{$tableName}` (\n  " .
			implode(",\n  ", $columns)."\n  " .
			") ENGINE=MyISAM\n";
	}

	private static $sqlConnections = array();

	/** @cond UTILITIES
	* Returns a valid SQL connection identifier
	*/
	protected function getSQLConnection() {
// 		$unique = get_class($this);
		$unique = md5("{$this->sql_host};{$this->sql_user};{$this->sql_pass}");
		if (!isset(self::$sqlConnections[$unique])) {
			$sql   = mysql_connect($this->sql_host, $this->sql_user, $this->sql_pass) OR die(SQL_CANNOT_CONNECT);
							mysql_select_db($this->sql_dbase, $sql)             OR die(SQL_CANNOT_CONNECT);
			// Prep connection for strict error handling.
			amtz_query("set sql_mode=strict_all_Tables", $sql);
			self::$sqlConnections[$unique] = $sql;
		}
		return self::$sqlConnections[$unique];
	}
	/// @endcond

	/**
	* Uses the "DESCRIBE" SQL keyword to get the actual definition of a table as it is in the MYSQL database
	*/
	protected function getActualTableDefs() {
		$sqlConnection = $this->getSQLConnection();
		$query = "DESCRIBE ".$this->sql_prfx.$this->getTableName();
		if (! $results = amtz_query($query, $sqlConnection) ) {
			return FALSE;
		}
		$definition = array();
		while ($row = mysql_fetch_assoc($results)) {
			$definition[$row['Field']] = array (
				$row['Type'],
				$row['Null'],
				$row['Key'],
				$row['Default'],
				$row['Extra']
			);
		}
		return $definition;
	}

	/* Apperently this is illegal in PHP (protected __get())
	 * I removed references to $this->table_name from this class
	 * leaving for now in case there's a good reason top keep.
	 * I doubt it though
	 * 
	protected function __get($name) {
		switch ($name) {
			case 'table_name':
				return $this->getTableName();
			default:
				trigger_error("Unknown property {$name} in Class ".__CLASS__);
		}
	}
	/* End of commented-out function */

	/**
	* returns true if the table exists in the current database, false otherwise.
	*/
	protected function table_exists() {
		$sql = $this->getSQLConnection();
		$result = amtz_query("SHOW TABLES", $sql);
		while ($row = mysql_fetch_row($result)) {
			if ($row[0] == $this->sql_prfx.$this->getTableName())
				return TRUE;
		}
		return FALSE;
	}

	protected function createTable() {
		$query = $this->getTableCreateQuery();
		if ($query == NULL) return FALSE;
		dbm_debug("info", "Creating table");
		dbm_debug("system query", $query);
		$sql = $this->getSQLConnection();
		$result = amtz_query($query, $sql) OR die($query . "\n\n" . mysql_error());
		if ($result) {
			dbm_debug("info", "Success creating table");
			return TRUE;
		} else {
			dbm_debug("info", "Failed creating table");
			return FALSE;
		}
	}

	/**
	* Bring the table up to the current definition
	*/
	protected function updateTable() {
		$customDefs = $this->table_defs;
		$tableName = first_key($customDefs);

		if (! isset($customDefs[$tableName])) {
			return FALSE;
		}

		$wanteddef = $customDefs[$tableName];
		$actualdef = $this->getActualTableDefs();

		$sqlConnection = $this->getSQLConnection();

		// Set the primary keys
		$wantedKey = $this->findKey($wanteddef);
		$actualKey = $this->findKey($actualdef);
		if ($wantedKey != $actualKey) {
			if ($actualKey) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  DROP PRIMARY KEY";
				dbm_debug("server query", $query);
				if (! amtz_query($query, $sqlConnection) ) return FALSE;
			}
			if ($wantedKey) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  ADD PRIMARY KEY (".$wantedKey.")";
				dbm_debug("server query", $query);
				if (! amtz_query($query, $sqlConnection) ) return FALSE;
			}
		}

		// Run through the wanted definition for what needs changing
		$location = "FIRST";
		foreach($wanteddef as $name => $options) {
			$creationDef = $this->getCreationDefinition($name, $options);
			// Find a column that needs creating
			if (! isset($actualdef[$name]) ) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  ADD COLUMN " . $creationDef . " " . $location;
				dbm_debug("server query", $query);
				if (! amtz_query($query, $sqlConnection) ) return FALSE;
			}
			// Find a column that needs modifying
			else if ($wanteddef[$name] != $actualdef[$name]) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  MODIFY COLUMN " . $creationDef . " " . $location;
				dbm_debug("server query", $query);
				if (! amtz_query($query, $sqlConnection) ) return FALSE;

			}
			// Change location so it will be set properly for the next iteration
			$location = "AFTER ".$name;
		}

		// SCARY
		// Run through the actual definition for what needs dropping
		foreach($actualdef as $name => $options) {
			// Find a column that needs deleting
			if (!isset($wanteddef[$name]) && DBM_AUTODROP ) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  DROP COLUMN " . $name;
				dbm_debug("server query", $query);
				if (! amtz_query($query, $sqlConnection) ) return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Makes every attempt to succeed at doing the query you ask it to do.
	 * This function will attempt the query and react by modifying the database to match your definitions if the query fails
	 */
	protected function makeQueryHappen($query) {
		$tableDefs = $this->table_defs;
		$tableName  = $this->getTableName();
		dbm_debug("regular query", $query);
		$sql = $this->getSQLConnection();
		$result = amtz_query($query, $sql);
		if (! $result) {
			// We have a problem here
			dbm_debug("system error", mysql_error());
			if (! $this->table_exists()) {
				dbm_debug("error", "Query Failed . . . table $tableName doesn't exist.");
				$this->createTable();
			} else {
				if ($tableDefs[$tableName] != $this->getActualTableDefs()) {
					dbm_debug("error", "Query Failed . . . table $tableName needs updating.");
					$this->updateTable($tableDefs);
				}
			}
			dbm_debug("regular query", $query);
			$result = amtz_query($query, $sql);
			if (! $result) {
				// We tried :(
				dbm_debug("error", "Query Retry Failed . . . table $tableName could not be fixed.");
				return FALSE;
			}
		}
		// If we got to here, that means we have got a valid result!
		switch (strtoupper(first_val(explode(' ', $query)))) {
		case 'SELECT':
			$returnVal = array();
			while ($row = mysql_fetch_assoc($result)) {
				$returnVal[] = $row;
			}
			return $returnVal;
			break;
		case 'INSERT':
		case 'REPLACE':
			return mysql_insert_id($sql);
			break;
		}
	}

}


?>