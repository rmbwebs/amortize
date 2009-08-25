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

	date_default_timezone_set('America/New_York');

	// Fill me in
	$sqlHost     = "";     // Hostname of your SQL server, or "localhost"
	$sqlUser     = "";     // SQL user name
	$sqlPass     = "";     // SQL password
	$sqlDatabase = "";     // Database name on SQL server
	$sqlPrefix   = "";     // A prefix to use on all table names, optional but it help you sort your tables
	$dbmDebug    = false;  // Set to true if you want to enable debugging output
	$dbmAutoDrop = false;  // Set to true if you want DBM to automatically drop columns that you remove from your table_defs (SCARY)
	$dbmTableDrop = false; // Set to true if you want to enable the Execution::dropTable() function.

	// Allow config consts defined before this file. Needed to run test.php.  Possible security risk.
	$dbmAllowConfOverrides = true;

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

function first_val($arr = array()) {
	if (is_array($arr) && count($arr) > 0) {
		$vals = array_values($arr);
		return $vals[0];
	} else {
		return null;
	}
}

function first_key($arr = array()) {
	if (is_array($arr) && count($arr) > 0) {
		$keys = array_keys($arr);
		return $keys[0];
	} else {
		return null;
	}
}

function dbm_debug($class, $message) {
	if (DBM_DEBUG) {
		if (is_string($message)) {
			echo "<div class=\"$class\">";
				echo $message;
			echo "\n</div>\n";
		} else {
			echo "<pre class=\"$class\">\n";
// 				var_dump($message);
// 				print_r($message);
				var_export($message);
			echo "\n</pre>\n";
		}
	}
}


if (DBM_DEBUG) { set_error_handler ("dbm_do_backtrace"); }

function dbm_do_backtrace ($one, $two) {
	echo "<pre>\nError {$one}, {$two}\n";
	debug_print_backtrace();
	echo "</pre>\n\n";
}

$_SERVER['amtz_query_time'] = 0;
$_SERVER['amtz_queries']    = array();

function amtz_query($query, $connection=null) {
	$startTime   = microtime(true);
	$result      = mysql_query($query, $connection);
	$endTime     = microtime(true);
	$elapsedTime = $endTime - $startTime;
	$_SERVER['amtz_queries'][] = array(
		'startTime'   => $startTime,
		'endTime'     => $endTime,
		'elapsedTime' => $elapsedTime,
		'query'       => $query
	);
	$_SERVER['amtz_query_time'] += $elapsedTime;
	return $result;
}

define('E_SQL_CANNOT_CONNECT', "
<h2>Cannot connect to SQL Server</h2>
There is an error in your Amortize configuration.
");


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
		$defs = (is_null($defs)) ? $this->table_defs : $defs;
		if (is_string($defs)) { // Allow use of existing tables
			$this->table_defs = $this->getActualTableDefs();
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
		$defs = (is_null($defs)) ? $this->table_defs : $defs;
		return first_key($defs);
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
			") ENGINE=MyISAM DEFAULT CHARSET=latin1\n";
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
		$query = "DESCRIBE ".$this->sql_prfx.$this->table_name;
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

	protected function __get($name) {
		switch ($name) {
			case 'table_name':
				return $this->getTableName();
			default:
				trigger_error("Unknown property {$name} in Class ".__CLASS__);
		}
	}

	/**
	* returns true if the table exists in the current database, false otherwise.
	*/
	protected function table_exists() {
		$sql = $this->getSQLConnection();
		$result = amtz_query("SHOW TABLES", $sql);
		while ($row = mysql_fetch_row($result)) {
			if ($row[0] == $this->sql_prfx.$this->table_name)
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
		$tableName  = $this->table_name;
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
		switch (strtoupper(first_val(split(' ', $query)))) {
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


define('SQL_DATE_FORMAT', 'Y-m-d');
define('SQL_TIME_FORMAT', 'H:i:s');
define('SQL_DATETIME_FORMAT', 'Y-m-d H:i:s');

/**
 * Helper for AmortizeFeatures
 * A translation layer for database data and DBMFeatures data
 */
class AmortizePreparation extends AmortizeExecution {

	/**
	 * Pulls one or more rows out of a table.
	 * Potentially pulls all rows out a table of you pass $params = null
	 * @param $params a whereClause-like array of data to determine what rows we are going to yank
	 * @param $yankAll (optional, default=false) must be set to true to enable yanking all rows (protection against you passing a value in $params that doesn't generate a valid WHERE clause)
	 */
	protected function sqlMagicYank($params, $yankAll=false) {

		$whereClause = $this->buildWhereClause($params);
		if ($whereClause == null && $yankAll == false) { return false; } // yankAll protection
		$query = "DELETE FROM ".$this->sql_prfx.$this->getTableName()." ".$whereClause;
		$success = $this->makeQueryHappen($query);

		return ($success) ? true : false;
	}

	/**
	 * Inserts or replaces data into a row in the table
	 * @param $data the data to insert, in associative array form, column names as indeces and data as values
	 */
	protected function sqlMagicPut($data) {
		$data = $this->sqlFilter($data);
		$data = $this->sqlDataPrep($data);
		$key = $this->findTableKey();
		if ( ($key) && (isset($data[$key])) && (((is_numeric($data[$key]))&&($data[$key] == 0))  || ($data[$key] == NULL)) ) {
			$query = "INSERT ";
		} else {
			$query = "REPLACE ";
		}
		$columnList = "(";
		$valueList  = "(";
		$comma      = "";
		foreach ($data as $column => $value) {
			$columnList .= $comma."`".$column."`";
			$valueList  .= $comma.'"'.$value.'"';
			$comma = ", ";
		}
		$columnList .= ")";
		$valueList  .= ")";
		$query .= "INTO ".$this->sql_prfx.$this->getTableName()."\n  ".$columnList."\n  VALUES\n  ".$valueList;
		return $this->makeQueryHappen($query);
	}

	/**
	 * Returns the data from one and only one row.
	 * Most common use is the load function.  In the future this should be rewritten to use the getallsomething function
	 */
	protected function sqlMagicGetOne($params) {

		$whereClause = $this->buildWhereClause($params);

		$query = "SELECT * FROM ".$this->sql_prfx.$this->getTableName()." ".$whereClause." LIMIT 1";
		$data = $this->makeQueryHappen($query);

		if ($data) {
			// Successful Query
			return $this->sqlDataDePrep($data[0]);
		} else {
			// we didn't get valid data.
			return null;
		}
	}

	/**
	 * Can be used as an interface to the SQL SET command.
	 * @param $set An array of which columns to set with their values
	 * @param $where A whereClause-like array that dictates WHERE the updating will take place
	 * @param $setAll (optional, defaults to false) A safety that prevents you from setting all rows if your $where value doesn't generate a proper where clause. Pass a true to this parameter to override the safety.
	 */
	protected function sqlMagicSet($set, $where, $setAll=false) {
		$whereClause = $this->buildWhereClause($where);
		$setClause = $this->buildSetClause($set);
		// setAll protection
		if ($whereClause == null && $setAll == false) { return false; }
		// generate query
		$query = "UPDATE ".$this->sql_prfx.$this->getTableName." ".$setClause." ".$whereClause;
		return $this->makeQueryHappen($query);
	}

	/**
	 * Gets specific (or all) columns from a table.
	 * Optionally can match a param list, and also supports limit and offset.
	 * @param $column A string equal to the column name you want, or "*"
	 * @param $limit Optional limit value to put into the query
	 * @param $offset Optional offset value for the query
	 * @param $params Optional whereClause-like list of parameters to search for
	 * @returns An DBM-formatted array of data or null on error
	 */
	protected function getAllSomething($column, $limit=NULL, $offset=NULL, $params=NULL) {
		$key = $this->findTableKey();
		$column = (is_string($column)) ? $column : "*";

		$whereClause = $this->buildWhereClause($params);

		$query = "SELECT {$column} FROM ".$this->sql_prfx.$this->getTableName()." ".$whereClause." ORDER BY {$key}";

		if ($limit && is_numeric($limit)) {
			$query .= " LIMIT {$limit}";
		}
		if ($offset && is_numeric($offset)) {
			$query .= " OFFSET {$offset}";
		}

		$data = $this->makeQueryHappen($query);
		if ($data) {
			// We have a successful Query!
			$return = array();
			foreach($data as $row) {
				$return[] = $this->sqlDataDePrep($row);
			}
			return $return;
		} else {
			return null;
		}
	}

	/** @deprecated This function will not scale well to multi-column tables and may be removed in the future.
	 * Returns an array of all known primary keys from this table
	 */
	protected function getAllIDs($limit=NULL, $offset=NULL, $params=NULL) {
		$key = $this->findTableKey();
		$data = $this->getAllSomething($key, $limit, $offset, $params);
		if ($data) {
			// Convert from an array of arrays to an array of values
			$returnVal = array();
			foreach ($data as $row) {
				$returnVal[] = $row[$key];
			}
			return $returnVal;
		} else {
			return null;
		}
	}

	/**
	 * Creates an inner join to another table.
	 * Gets all values from the other table that match your inner join.
	 * @param $that      An instance of this class (or extension) that uses a different table
	 * @param $on        What we are going to join on: array(thisColumnName => thatColumnName [, . . .])
	 * @param $thisWhere A whereClause-format array of optional search parameters for this table
	 * @param $thatWhere A whereClause-format array of optional search parameters for the joining table
	 */
	protected function getInnerJoin($that, $on, $thisWhere=null, $thatWhere=null) {
		$thatTableFullName = $that->getFullTableName();
		$thisTableFullName = $this->getFullTableName();

		$on        = (is_array($on))        ? $on        : array();
		$thisWhere = (is_array($thisWhere)) ? $thisWhere : array();
		$thatWhere = (is_array($thatWhere)) ? $thatWhere : array();

		foreach($on as $param => $value) {$onTranslated[$thisTableFullName.'.'.$param] = $thatTableFullName.'.'.$value; }
		foreach($thisWhere as $param => $value) { $where[$thisTableFullName.'.'.$param] = $value; }
		foreach($thatWhere as $param => $value) { $where[$thatTableFullName.'.'.$param] = $value; }

		$query =
			"SELECT DISTINCT {$thatTableFullName}.*\n".
			"  FROM {$thatTableFullName} INNER JOIN {$thisTableFullName}\n".
			"    ".$this->buildOnClause($onTranslated)."\n".
			"  ".$this->buildWhereClause($where)."\n".
			"  ORDER BY {$thisTableFullName}.ordering";

		$data = $this->makeQueryHappen($query);
		if ($data) {
			$returnVal = array();
			foreach ($data as $row) {
				$returnVal[] = $that->sqlDataDePrep($row);
			}
			return $returnVal;
		} else {
			return NULL;
		}
	}


  /// @cond UTILITIES
	protected function getTableColumnDefs() {
		return first_val($this->getTableDefs());
	}

	/**
	* returns an array of table column names
	*/
	protected function getTableColumns() {
		return array_keys($this->getTableColumnDefs());
	}

	private function buildConditionalClause($params=null, $q=true, $and="AND") {
		if (is_string($params)) {
			return $params;
		} else if (is_array($params)) {
			foreach ($params as $field => $target) {
				if (!is_array($target)) {
					$params[$field] = array('=' => $target);
				}
			}
		} else {
			return "";
		}

		$clause = array();
		foreach ($params as $field => $target) {
			foreach ($target as $comparator => $value) {
				$value = ($q) ? "'{$value}'" : $value;
				$clause[] = "{$field} {$comparator} {$value}";
			}
		}
		return implode(" {$and} ", $clause);
	}

	protected function buildWhereClause($params=null) {
		$clause = $this->buildConditionalClause($params);
		return (strlen($clause) > 0) ? "WHERE {$clause}" : "";
	}

	protected function buildOnClause($params=null) {
		$clause = $this->buildConditionalClause($params, false);
		return (strlen($clause) > 0) ? "ON {$clause}" : "";
	}

	protected function buildSetClause($params=null) {
		$clause = $this->buildConditionalClause($params, true, ',');
		return (strlen($clause) > 0) ? "SET {$clause}" : "";
	}
	/// @endcond

	/**
	* Takes an array of data and returns the same array only all the data has been
	* cleaned up to prevent SQL Injection Attacks
	*/
	protected function sqlFilter($data) {
		$sql = $this->getSQLConnection();
		$retVal = array();
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				if (is_array($value)) {
					$retVal[$key] = $this->sqlFilter($value);  // OMG Scary Recursion! :)
				} else {
					$retVal[$key] = mysql_real_escape_string($value, $sql);
				}
			}
		}
		return $retVal;
	}

	/**
	 * Preps special data for insertion into the database.
	 * Converts SET arrays to comma-delineated string
	 * Converts DATE columns to an SQL-compatible date representation from any date expression that strtotime() can translate
	 * Converts TIME columns to an SQL-compatible time representation from any time expression that strtotime() can translate
	 * Converts DATETIME columns to an SQL-compatible date and time representation from any expression that strtotime() can translate
	 * Converts BOOL and BOOLEAN columns to 1 or 0
	 */
	protected function sqlDataPrep($data) {
		$defs = $this->getTableColumnDefs();
		foreach ($data as $colname => &$value) {  // PHP4 porters: remove the & and change all $value='blah' to $data[$colname]='blah';
			$rawDef = $defs[$colname][0];                                    // Get the column Definition
			$pos = strpos($rawDef, '(');                                     // Check for existance of () in the definition
			$trimDef = ($pos===false) ? $rawDef : substr($rawDef, 0, $pos);  // If no (), just use rawDef, otherwise, use what comes before ()
			switch (strtoupper($trimDef)) {
				case "SET":
					// Convert to text-based representation
					$value = implode(',',
						array_keys($value, true) // Collect all the array keys whose value is true
					);
				break;
				case "DATE":
					$value = date(SQL_DATE_FORMAT, strtotime($value));
				break;
				case "TIME":
					$value = date(SQL_TIME_FORMAT, strtotime($value));
				break;
				case "DATETIME":
					$value = date(SQL_DATETIME_FORMAT, strtotime($value));
				break;
				case "BOOL":
				case "BOOLEAN":
					$value = ($value) ? "1" : "0";
				break;
			}
		}
		return $data;
	}

	/**
	 * Translates data from database format to easily useable arrays of data.
	 * Converts SET() strings to true/false arrays
	 * Converts BOOL and BOOLEAN values to PHP boolean true and false
	 */
	public function sqlDataDePrep($data) {
		$defs = $this->getTableColumnDefs();
		foreach ($data as $colname => &$value) {  // PHP4 porters: remove the & and change all $value='blah' to $data[$colname]='blah';
			$rawDef = $defs[$colname][0];                                    // Get the column Definition
			$pos = strpos($rawDef, '(');                                     // Check for existance of () in the definition
			$trimDef = ($pos===false) ? $rawDef : substr($rawDef, 0, $pos);  // If no (), just use rawDef, otherwise, use what comes before ()
			switch (strtoupper($trimDef)) {
				case "SET":
					$troofs = explode(',', $data[$colname]);
					$value = $this->valuesFromSet($troofs, $rawDef);
				break;
				case "BOOL":
				case "BOOLEAN":
					$value = ($value) ? true : false;
				break;
			}
		}
		return $data;
	}

	/**
	 * Takes an array of values that represent data taken from a SET column in a database and converts it to a specific format.
	 * Explanation for this function is best done through examples:
	 * Here is an example database column definition: "SET('foo','bar','boo','baz')" that might be passed into this function as $def
	 * Possible Input: $truevalues = array('foo','boo', 'zoo')
	 * Output:  array('foo' => true, 'bar' => false, 'boo' => true, 'baz' => false);   <-- 'zoo' is not in $def, so is ommitted;
	 * $truevalues can also be in the same format as the output, an array with indeces from the SET definition and true or false values.
	 * In that case, indeces from the set are preserved, missing indeces are filled in as false, and indeces not part of the set are removed
	 * \param $truevalues the values to be used as indeces with true values in the output array: one of two formats listed above
	 * \param $def the SET definition, in actual MySQL format SET('option1','option2[,. . .])
	 */
	protected function valuesFromSet($truevalues, $def) {
		// Check format of $truevalues
		if ((count(array_keys($truevalues, true, true)) + count(array_keys($truevalues, false, true))) == count($truevalues)) {
			// Truevalues is an array composed entirely of true and false values. Convert!
			$truevalues = array_keys($truevalues, true, true);
			// Why not simply return $truevalues here?  Because we want to ensure that we include all the possibles in the return
		}
		$returnMe = array();
		preg_match("/\((.*)\)/", $def, $match); // Strip the list of possibles from the column def
		preg_match_all("/[\"']([^'\"]+)[\"'],*/", $match[1], $possibles); // Split the possibles into an array
		$possibles = (isset($possibles[1])) ? $possibles[1] : array();  // The array we want is stored in position 1
		foreach ($possibles as $possible) {
			$returnMe[$possible] = false; // set default values
		}
		foreach ($truevalues as $truevalue) {
			if (isset($returnMe[$truevalue])) { // Filter junk from $truevalues
				$returnMe[$truevalue] = true; // set true for the values we want
			}
		}
		return $returnMe;
	}

	protected function getInitial($columnDef) {
		if (is_array($columnDef)) {
			$columnDef = $columnDef[0];
		}
		if (strtoupper(substr($columnDef, 0, 3)) == "SET") {
			return $this->valuesFromSet(array(), $columnDef);
		} else {
			return null;
		}
	}

	public function dropTable() {
		$table = $this->getFullTableName();
		if ($this->can_drop_table) {
			$this->makeQueryHappen("DROP TABLE IF EXISTS {$table}");
		} else {
			trigger_error(__CLASS__."::dropTable() called but table dropping is disabled per local configuration");
		}
	}



}


define('MAP_FROM_COL', "parentID");
define('MAP_TO_COL',   "childID");

/// Linking object to join two DBM Objects.
class AmortizeLink extends AmortizePreparation {

	private $from    = null;
	private $to      = null;

	function __construct($fromDef, $toDef) {
		parent::__construct();
		$parentClass = get_parent_class($this);
		$from = new $parentClass;
		$to   = new $parentClass;

		if (is_object($fromDef)) {
			$from->setTableDefs($fromDef->getTableDefs());
		} else {
			$from->setTableDefs($fromDef);
		}
		if (is_object($toDef)) {
			$to->setTableDefs($toDef->getTableDefs());
		} else {
			$to->setTableDefs($toDef);
		}

		$this->from = $from;
		$this->to   = $to;

		$this->setLinkDefs();
	}

	public function createLink($fromID, $toID, $relation=null) {
		$params = array(
			MAP_FROM_COL => $fromID,
			MAP_TO_COL   => $toID
		);
		if (!is_null($relation)) { $params['relation'] = $relation; }
		return $this->sqlMagicPut($params);
	}

	public function breakLink($fromID, $toID=null, $relation=null) {
		$params = array(MAP_FROM_COL => $fromID);
		if (!is_null($toID))     { $params[MAP_TO_COL]  = $toID; }
		if (!is_null($relation)) { $params['relation'] = $relation; }
		return $this->sqlMagicYank($params);
	}

	public function getLinksFromID($id, $params=null, $relation=null) {
		$joinOn = array(MAP_TO_COL => $this->to->findTableKey());
		$thisWhere = array(MAP_FROM_COL => $id);
		if (!is_null($relation)) { $thisWhere['relation'] = $relation; };
		return $this->getInnerJoin($this->to, $joinOn, $thisWhere, $params);
	}

	public function getBackLinksFromID($id, $params=null, $relation=null) {
		$joinOn = array(MAP_FROM_COL => $this->from->findTableKey());
		$thisWhere = array(MAP_TO_COL => $id);
		if (!is_null($relation)) { $thisWhere['relation'] = $relation; };
		return $this->getInnerJoin($this->from, $joinOn, $thisWhere, $params);
	}



	/*********************** Protected Support Functions below **************************/

	private $createTriggers = false;

	// A hook for the table creation routine in AmortizeExecution
	// This function will be called once the first time that a link is created between two object types
	protected function createTable($foo=null) {
		// First: take care of the obligations to this function call.
		parent::createTable($foo);
		// Next: attempt to make a delete trigger if our configuration allows it.
		if ($this->createTriggers) {
			$mapName = $this->getFullTableName();
			$fromTableName = $this->from->getFullTableName();
			$fromPrimary = $this->from->findTableKey();
			$toTableName = $this->to->getFullTableName();
			$toPrimary = $this->to->findTableKey();
			$sql = $this->getSQLConnection();

			$mapFromCol = MAP_FROM_COL;
			$mapToCol   = MAP_TO_COL;


			$fromQuery = <<<QUERY
				CREATE TRIGGER {$mapName}_FromDeleteTrigger
				  AFTER DELETE ON {$fromTableName}
				  FOR EACH ROW
				    DELETE FROM {$mapName} WHERE {$mapFromCol}=OLD.{$fromPrimary}
QUERY;

			$toQuery = <<<QUERY
				CREATE TRIGGER {$mapName}_ToDeleteTrigger
				  AFTER DELETE ON {$toTableName}
				  FOR EACH ROW
				    DELETE FROM {$mapName} WHERE {$mapToCol}=OLD.{$toPrimary}
QUERY;

			dbm_debug("system query", $fromQuery);
			mysql_query($fromQuery, $sql) OR die($fromQuery . "\n\n" . mysql_error());
			dbm_debug("system query", $toQuery);
			mysql_query($toQuery, $sql) OR die($toQuery . "\n\n" . mysql_error());
		}
	}

	/*********************** Private Support Functions below **************************/

	private function setLinkDefs() {
		$mapDefs = $this->createMapDefs($this->from, $this->to);
		$mapName = $this->createMapName($this->from, $this->to);
		$this->setTableDefs(array($mapName => $mapDefs));
	}

	private function createMapName($parent, $child) {
		$parentTableName = $parent->getTableName();
		$childTableName  = $child->getTableName();
		return "map_{$parentTableName}_to_{$childTableName}";
	}

	private function createMapDefs($parent, $child) {
		$parentTableKeyDef = $parent->findTableKeyDef();
		$childTableKeyDef  = $child->findTableKeyDef();
		// We really only need the data type
		$parentTableKeyDef = array($parentTableKeyDef[0], "NO", "PRI");
		$childTableKeyDef = array($childTableKeyDef[0], "NO", "PRI");

		return array(
			MAP_FROM_COL => $parentTableKeyDef,
			MAP_TO_COL   => $childTableKeyDef,
			'relation'   => array("varchar(20)",         "YES", "PRI"),
			'ordering'   => array("int(11) unsigned",    "NO",  "",    "0",  "")
		);
	}

}

/// Backend for the AmortizeObject
class AmortizeFeatures extends AmortizePreparation {

	/// Object status.
	/// Possible statuses are "needs saving", etc.
	protected $status = array();

	/// Object attributes are the data that is stored in the object and is saved to the database.
	/// Every instance of a AmortizeFeatures has an array of attributes.  Each attribute corresponds
	/// to a column in the database table, and each instance of this class corresponds to a row in the table.
	/// Through member functions, attributes can be read and set to and from an object.
	protected $attributes = array();

 protected $needs_loading = false;

	protected $table_defs = null;

	/// Calls initialize() and sets the $needs_loading flag in an ID is passed in
  public function __construct($id = NULL) {
		parent::__construct();
		$this->setTableDefs($this->table_defs);
    $this->initialize($id);
    if ($id != NULL) {
			$this->needs_loading = true;
		}
  }

	/// Sets all the attributes to blank and the table key to null.
	/// used for initializing new blank objects.
	protected function initialize($id=null) {
		$defs = $this->getTableDefs();
		if (is_array($defs)) {
			$cols = $this->getTableColumnDefs($defs);
			foreach ($cols as $col => $coldef) {
				$this->attributes[$col] = $this->getInitial($coldef);
				$this->status[$col] = "clean";
			}
			$this->attributes[$this->findTableKey()] = $id;
		}
	}

	/** Loads an object from the database.
	 *  This function loads the attributes for itself from the database, where the table primary key = $id
	 *  Normally called from the constructor, it *could* also be used to change the row that an existing object
	 *  is working on, but just making a new object is probably preferable, unless you really know what you are doing.
	 */
	protected function load($id) {
		dbm_debug("load", "Loading a " . get_class($this) . " with ID = " . $id);
		$key = $this->findTableKey();
		$query = array($key => $id);
		$info = $this->sqlMagicGetOne($query);
		if ($info && is_array($info)) {
			$this->setAttribs($info, true);
			foreach (array_keys($info) as $col) {
				$this->status[$col] = "clean";
			}
			return true;
		} else {
			return false;
		}
	}

	protected function loadIfNeeded(){
		// Perform delayed load
		if ($this->needs_loading) {
			$this->needs_loading = false;
			$id = $this->getPrimary();
      if ($this->load($id) === false) {
        // The load failed. . . a never-before-seen primary ID is being explicitly set by the constructor.
        // Mark it dirty so we are sure that it saves.
        dbm_debug("failedload", "load failed");
        $this->setAttribs(array($this->findTableKey() => $id), true);
      }
      return true;
    } else {
			return false;
    }
	}

  /// Returns the array of attributes for the object.
  protected function getAttribs() {
		// Perform a delayed load if needed so that we have some info to return!
		$this->loadIfNeeded();
		// Build the return value
		$returnMe = $this->attributes;
		$key = $this->findTableKey();
		if ($returnMe[$key] == NULL) {
			// Unsaved Object, don't return the key attribute with the results
			unset($returnMe[$key]);
		}

    return $returnMe;
  }

	/// Sets attribute (row) data for this object.
	/// $clobberID is a bool that must be true to allow you to overwrite a primary key
	protected function setAttribs($info, $clobberID = false) {
		// Do the delayed load now so that it doesn't happen later and overwrite these values!
		$this->loadIfNeeded();

		// Do the attribute setting
		dbm_debug("setattribs data", $info);
		$defs = $this->getTableDefs();
		$columns = $defs[$this->getTableName($defs)];
		$key = $this->getPrimaryKey();
		if ((!$clobberID) && isset($info[$key])) {
			dbm_debug("clobber", "clobber protected!");
			unset($info[$key]);
		}

		$returnVal = FALSE;
		foreach ($columns as $column => $def) {
			$def = (is_array($def)) ? $def[0] : $def;
			if (isset($info[$column])) {
				if (is_array($info[$column])) { // Filter HTML type arrays to support setAttribs($_POST);
					$info[$column] = $this->valuesFromSet($info[$column], $def);
				}
				$this->attributes[$column] = $info[$column];
				$returnVal = TRUE;
				$this->status[$column] = "dirty";
			}
		}

		return $returnVal;
	}

	/// Saves the object data to the database.
	/// This function records the attributes of the object into a row in the database.
	function save($force = false) {
		$defs = $this->getTableDefs();
		$columns = $this->getTableColumns($defs);
		$allclean = array();
		$savedata = array();
		$key = $this->findTableKey();
		$a = $this->getAttribs();
		if (!isset($a[$key]) || ($a[$key] == null)) {
			// This object has never been saved, force save regardless of status
			// It's very probable that this object is being linked to or is linking another object and needs an ID
			$force = true;
			// Exclude the ID in the sql query.  This will trigger an auto_increment ID to be generated
			$excludeID = true;
			dbm_debug("info deep", "This ".get_class($this)." is new, and we are saving all attributes regardless of status");
		} else {
			// Object has been saved before, OR a new ID was specified by the constructor parameters.
			// either way, we need to include the ID in the SQL statement so that the proper row gets set,
			// or the proper ID is used in the new row
			$excludeID = false;
		}

		$magicput_needs_rewrite = true;

		foreach ($columns as $col) {
			if (($this->status[$col] != "clean") || $force || $magicput_needs_rewrite){
				if (isset($a[$col])) {
					$savedata[$col] = $a[$col];
				}
			}
		}

		if ( count($savedata) >= 1 ) {
			if (!$excludeID) {
				$savedata[$key] = $a[$key];
			}
			$id = $this->sqlMagicPut($savedata);

			if ($id) {
				// Successful auto_increment Save
				$this->attributes[$key] = $id;
				// Set all statuses to clean.
				$this->status = array_fill_keys(array_keys($this->status), "clean");
				return TRUE;
			} else if ($id !== false) {
				// We are not working with an auto_increment ID
				// Set all statuses to clean.
				$this->status = array_fill_keys(array_keys($this->status), "clean");
				return TRUE;
			} else {
				// ID === false, there was an error
				die("Save Failed!\n".mysql_error());
				return FALSE;
			}

		}

	}


	protected function getLinkedObjects($example, $params=null, $relation=null, $backLinks=false) {
		$example = (is_object($example)) ? get_class($example) : $example;
		$prototype = new $example;

		$id = $this->getPrimary();

		if (!$backLinks) {
			$linkObject = new AmortizeLink($this, $prototype);
			$data = $linkObject->getLinksFromID($id, $params, $relation);
		} else {
			$linkObject = new AmortizeLink($prototype, $this);
			$data = $linkObject->getBackLinksFromID($id, $params, $relation);
		}

		$data = (is_array($data)) ? $data : array();

		$results = array();
		foreach ($data as $fields) {
			$temp = clone($prototype);
			$temp->setAttribs($fields, true);
			$results[] = $temp;
		}

		return $results;

	}

	/// Tells you the column name that holds the primary
	public function getPrimaryKey() {
    return $this->findTableKey();
	}

	/** Returns the value of this object's primary key.
	 * Primary key is the unique id for each object, used in the constructor and the load function
	 * for example:
	 *   $obj = new AmortizeObject($key);
	 *   $key2 = $obj->getPrimary();
	 *   $key2 == $key
	 */
	public function getPrimary() {
    $key = $this->getPrimaryKey();
    return $this->attributes[$key];
	}

	/**
	 * Removes this object's row from the table
	 */
	protected function removeMyRow() {
		$keyVal = $this->getPrimary();
		// Check if the object has even been saved
		if (!is_null($keyVal)) {
			// The key value is non-null.  Therefore it stands to reason that the object exists in the database.  Removal is authorized
			//Build the where clause
			$key = $this->getPrimaryKey();
			$where = array($key => $keyVal);
			// Do the remove
			return $this->sqlMagicYank($where, false);
		} else {
			// The key was null, this object probably isn't in the database
			// Don't attempt a remove.
		}
	}

	/**
	 * Removes any number of rows from the table, protects against accidentally removing all.
	 * If your $where parameter does not make sense or is null, this function protects you from accidentally blanking the table
	 * @param $where an array of matched attributes to delete on
	 */
	protected function removeSomeRows($where=null) {
		return $this->sqlMagicYank($where, false);
	}

	/**
	 * Removes any number of rows from the table, removes all rows if $where is null or invalid.
	 * If you truly only want to remove some of the rows, removeSomeRows() is a better choice.
	 * If your $where parameter does not make sense or is null, this function will blank your table.
	 * @param $where An optional where array or where string. If left blank, the table will be blanked.
	 */
	protected function removeAllRows($where=null) {
		return $this->sqlMagicYank($where, true);
	}

	/**
	 * Retrieve an array of all the known IDs for all saved instances of this class
	 * If you plan on foreach = new Blah(each), I suggest using getAllLikeMe instead, your database will thank you
	 * @deprecated This function is not used at any point in this library, and isn't really usefull.  Further, it won't scale well for multi-column primary keys.
	 */
	public function getAllPrimaries($limit=NULL, $offset=NULL, $params=NULL) {
		$list = $this->getAllIDs($limit, $offset, $params);
		return $list;
	}

	/// Retrieve an array of pre-loaded objects
	public function getAllLikeMe($limit=NULL, $offset=NULL, $params=NULL) {
		$list = $this->getAllSomething("*", $limit, $offset, $params);
		$key = $this->findTableKey();
		$returnMe = array();

		if (is_array($list)) {
			foreach ($list as $data) {
// 				print_r($data);
				$temp = clone $this;
				$temp->setAttribs($data, true);
				$returnMe[$data[$key]] = $temp;
			}
		}
		return $returnMe;
	}

  /// Returns the name of the table that this object saves and loads under.
  /// Pretty easy function really.
  public function getMyTableName() {
		return $this->getTableName();
  }


	/// Dumps the contents of attribs via print_r()
	/// Useful for debugging, but that's about it
	public function dumpview($pre=false) {
		if ($pre) echo "<pre style=\"color: blue\">\n";
		echo "Attributes for this ".get_class($this).":\n";;
		print_r($this->attributes);
		if ($pre) echo "</pre>\n";
	}

}


/**
 * This object makes it easy for a developer to create abstract objects which can save themselves
 * into and load themselves from an SQL database.
 * This class is meant to be used as a base class for custom objects.  When a group of classes extend this class,
 * each class represents a table in the database, with each instance of that class representing a row in the table.
 * Table name and column definitions are hard-coded into the class.
 * Descendents of this class can themselves be extended and pass their column definitions on to their descendants.
 * For Example:
 * @code
 * class Book extends AmortizeInterface {
 *   protected $table_name = "books";
 *   protected $table_columns = array('id' => "serial", 'isbn' => "varchar(20)");
 * }
 * class Novel extends Book {
 *   protected $table_name = "novels";
 *   protected $table_columns = array('author' => "tinytext");
 * }
 * $nov = new Novel;
 * $nov->getTableDefs() will return this: array('novels' => array('id' => "serial", 'isbn' => "varchar(20)", 'author' => "tinytext"))
 * @endcode
 */
class AmortizeInterface extends AmortizeFeatures {

	/**
	 * Name of the table that this object saves and loads under.
	 * If a table name prefix is defined in the DBM config file, it will be prepended to this value to make the table name.
	 */
	protected $table_name = null;


	///Definitions for the columns in this object's table.
	protected $table_columns = null;

	/**
	 * If this is defined in your class, table definitions are not extended further;
	 * Use like this to declare your class as the baseclass as far as table column defs and table name are concerned:
	 * @code
	 * protected $baseclass = __CLASS__;
	 * @endcode
	 */
	 protected $baseclass = null;

	/// Set to true if you want an automatic primary key to be added to your class
	protected $autoprimary = null;

	/**
	 * Allows you to define attributes of your class which are actually themselves instances of DbM classes.
	 * The format is similar to the table_columns array: an associative array where the key is the name of the attribute
	 * and the value describes the type of data stored in that attribute.  In this case, the value is simply the name of the
	 * class that the attribute will be an instance of.
	 * @code
	 * class Person extends AmortizeInterface {
	 *   protected $autoprimary=true;
	 *   protected $table_columns = array('firstname' => 'varchar(20)', 'lastname' => 'varchar(20)');
	 * }
	 * class Restaurant extends AmortizeInterface {
	 *   protected $autoprimary=true;
	 *   protected $table_columns = array('name' => 'varchar(50)', address => 'tinytext');
	 *   protected $externals = array('owner' => "Person");
	 * }
	 * @endcode
	 * In the example above, any instance $res of Restaurant will have an instance of Person that can be accessed via
	 * $res->owner or under the 'owner' key of the array returned by $res->attribs().
	 *
	 * You can chain like this: @code echo "{$res->owner->firstname} {$res->owner->lastname} owns {$res->name}"; @endcode
	 *
	 * DbM objects which have externals defined will automatically save the primary key(s) of the external classes into
	 * their own table columns, and therefore are able to recall identical instances of their external objects across save/load
	 * cycles.
	 *
	 * External objects are not saved automatically when the holder is saved.  This is to prevent cascading saves which could be
	 * disasterous if a linked list has been implemented  using externals, especially a ringed list.
	 * Because of this, you need to save your externals manually: @code $res->owner->save(); @endcode will work fine.
	 */
	protected $externals = array();

	// Actual storage of the external objects.
	private $external_objects = array();

	// A list of columns used to store the external definitions.  Used for filtering in the attribs function.
	private $external_columns = array();

	/**
	 * Class Constructor
	 * Kicks off the table merging process for objects that extend other objects.
	 */
	public function __construct($data=null) {
		$this->mergeColumns();
		if ($this->autoprimary) {
			// Add the ID index to the front of the table_columns array
			$ID_array = array('ID' => array("bigint(20) unsigned", "NO",  "PRI", "", "auto_increment"));
			$this->table_columns = array_merge($ID_array, $this->table_columns);
		}
		$this->table_defs = (is_null($this->table_columns)) ? $this->table_name : array($this->table_name => $this->table_columns);
		parent::__construct($data);

		/* Handle the externals.
		 * We need to call parent::__construct() twice to handle the special case where a class has itself listed as an external.
		 * In that case, __construct() and buildExternalColumns will enter an endless loop unless buildExternalColumns can
		 * use $this as the model instead of using new $class as the model (see "prevent endless loop" comment in the
		 * buildExternalColumns function), and $this->getPrimaryKey can't be called before parent::__construct().
		 * Thus, parent::__construct() needs to be called both before and after buildExternalColumns().
		 */
		if (count($this->externals) > 0) {
			$this->external_columns = $this->buildExternalColumns();
			$this->table_columns    = array_merge($this->table_columns, $this->external_columns);
			$this->table_defs = (is_null($this->table_columns)) ? $this->table_name : array($this->table_name => $this->table_columns);
			parent::__construct($data);
		}
	}

	/// Merges the column definitions for ancestral objects into your object.
	private function mergeColumns() {
		if (
			( get_class($this)        == __CLASS__        ) ||
			( get_parent_class($this) == __CLASS__        ) ||
			( $this->baseclass        == get_class($this) )
		) { return true; }
		else {
			$par = get_parent_class($this);
			$par = new $par;
			$parcols = $par->getFilteredTableColumnDefs();
			$parcols = (is_array($parcols)) ? $parcols : array();
			$this->table_columns    = array_merge($parcols, $this->table_columns);
			$parexts = $par->getExternals();
			$this->externals        = array_merge($parexts, $this->externals);
			return true;
		}
	}

	private function buildExternalColumns() {
		$returnArray = array();
		if (is_array($this->externals)) {
			foreach ($this->externals as $name => $class) {
				$obj = ($class == get_class($this)) ? $this : new $class;  // Prevent endless loop
				$keys = $obj->getPrimaryKey(); $keys = (is_array($keys)) ? $keys : array($keys);
				$defs = $obj->getTableColumnDefs();
				foreach ($keys as $key) {
					$def = $defs[$key][0];
					$returnArray["{$name}_{$key}"] = $def;
				}
			}
		}
		return $returnArray;
	}

	/// Returns the external class list
	public function getExternals() { return $this->externals; }

	/// Returns getTableColumnDefs with externals filtered out.
	public function getFilteredTableColumnDefs() {
		$defs = $this->getTableColumnDefs();
		foreach ($this->external_columns as $col => $def) {
			unset($defs[$col]);
		}
		return $defs;
	}

	/**
	 * Used to set or get the info for this object.
	 * Filters out bad info or unknown data that won't go into our database table.
	 * \param $info Optional array of data to set our attribs to
	 * \param $clobber Optional boolean: set to true if you need to overwrite the primary key(s) of this object (default: false)
	 */
	function attribs($info=null, $clobber=false) {
		if (!is_null($info)) {
			// Filter-out external columns (which should only be modded by modding the external obj itself
			foreach(array_keys($this->external_columns) as $key) {
				unset($info[$key]);
			}
			$this->setExternalObjects($info);
			$this->setAttribs($info, $clobber);
		}
			$returnVal = $this->getAttribs();
			foreach(array_keys($this->external_columns) as $key) {
				unset($returnVal[$key]);
			}
			$returnVal = array_merge($returnVal, $this->getExternalObjects());
			return $returnVal;
	}

	private function setExternalObjects($info=null) {
		foreach ($this->externals as $name => $class) {
			if (isset($info[$name]) && is_object($info[$name]) && get_class($info[$name])==$class) {
				$this->external_objects[$name] = $info[$name];
			} else if (isset($this->external_objects[$name]) && is_object($this->external_objects[$name]) && get_class($this->external_objects[$name])==$class) {
				// Everything is good.
			} else {
				$obj = new $class;
				$key = $obj->getPrimaryKey();
				$attribs = $this->getAttribs();
				$this->external_objects[$name] = new $class($attribs["{$name}_{$key}"]);
			}
		}
	}

	private function getExternalObjects() {
		$this->setExternalObjects();
		return $this->external_objects;
	}

	/**
	 * Creates a link to another instance or extension of AmortizeObject.
	 * This means that a relational table is created between this object's table and the
	 * table of the object to be linked to, and an entry is placed in the relational table linking
	 * the two objects.  From this point on, the adopted object can be retrieved as part of a list
	 * by using the method getLinks().
	 *
	 * Example:\n
	 * $fam = new Family("Smiths");\n
	 * $joe = new Person("Joe");\n
	 * $pam = new Person("Pam");\n
	 * $fam->link($joe);  $fam->link($pam);\n
	 * $people = $fam->getLinks("Person");  <--- Returns an array of Pam and Joe Person objects\n
	 */
	function link($subject, $relation=NULL) {
		$this->save();
		$subject->save();

		$link = new AmortizeLink($this, $subject);
		return $link->createLink($this->getPrimary(), $subject->getPrimary(),  $relation);

	}

	/** Breaks a link previously created by link()
	 * B will no longer be returned as part of A->getLinks() after A->deLink(B) is called.
	 * If $relation is provided, only matched relational links will be delinked
	 * Without $relation, all links between the two objects will be delinked.
	 * To break non-relational links and leave relational link intact, provide an empty string ("") as a relation here.
	 */
	function deLink($subject, $relation=NULL) {
		$link = new AmortizeLink($this, $subject);
		return $link->breakLink($this->getPrimary(), $subject->getPrimary(),  $relation);
	}

	/** Breaks links to all previously linked $example.
	 * $example can be either a string of the classname, or an instance of the class itself
	 * If $relation is provided, only matched relations will be delinked
	 */
	function deLinkAll($example, $relation=NULL) {
		if (is_string($example)) {
			$subject = new $example;
		} else {
			$subject = $example;
		}
		$link = new AmortizeLink($this, $subject);
		return $link->breakLink($this->getPrimary(), null,  $relation);
	}

	/**
	 * Retrieve a list of this object's previously linked objects of a specific type.
	 * Use this function to retrieve a list of objects previously linked  by this object
	 * using the link() method.
	 * $example can be the name of the class you want to retrieve, or an example object of the same type as those
	 * children you want to retrieve.
	 *
	 * Example:\n
	 * \code
	 * $fido = new Dog("Fido");
	 * $fam = new Family("Smith");
	 * $bob = new Person("Bob");
	 * $fam->link($bob);
	 * $fam->link($fido);
	 * $fam->getLinks("Dog");  // Returns an Array that contains Fido and any other Dogs linked in to the Smith Family
	 * $fam->getLinks("Person");  // Returns an Array that contains Bob and any other Persons linked in to the Smith Family
	 * \endcode
	 */
	function getLinks($example) {
		$parameters = null;
		$relation = null;
		foreach (array_slice(func_get_args(),1) as $arg) {
			if (is_array($arg))  { $parameters = $arg; }
			if (is_string($arg)) { $relation   = $arg; }
		}
		return $this->getLinkedObjects($example, $parameters, $relation, false);
	}
	/**
	 * Works in reverse to getLinks().
	 * A->link(B); \n
	 * C = B->getBackLinks("classname of A"); \n
	 * C is an array that contains A \n
	 */
	function getBackLinks($example) {
		$parameters = null;
		$relation = null;
		foreach (array_slice(func_get_args(),1) as $arg) {
			if (is_array($arg))  { $parameters = $arg; }
			if (is_string($arg)) { $relation   = $arg; }
		}
		return $this->getLinkedObjects($example, $parameters, $relation, true);
	}

	private function setExternalColumns(){
		$externalAttribs = array();
		foreach($this->externals as $name => $class) {
			if (
				isset($this->external_objects[$name])     &&
				is_object($this->external_objects[$name]) &&
				get_class($this->external_objects[$name]) == $class
			) {
				$obj = $this->external_objects[$name];
				$keys = $obj->getPrimary(true);
				$keys = (is_array($keys)) ? $keys : array($obj->getPrimaryKey() => $keys); // Convert to future format
				foreach($keys as $column => $keyval) {
					$externalAttribs["{$name}_{$column}"] = $keyval;
				}
			}
		}
		$this->setAttribs($externalAttribs);
	}

	/// A front-end for the save function at the Features level, handles externals.
	public function save($force = false) {
		$this->setExternalColumns();
		parent::save($force);
	}

	/// A front-end for the load function, handles externals
	public function load($info = null) {
		parent::load($info);
		$this->setExternalObjects();
	}

	/// Removes the row for this object
	public function delete() {
		return parent::removeMyRow();
	}

	public function __get($name) {
		$a = $this->attribs();
		return (isset($a[$name])) ? $a[$name] : null;
	}

	public function __set($name, $value) {
		$this->attribs(array($name => $value));
	}

}


	class Amortize extends AmortizeInterface {}
?>
