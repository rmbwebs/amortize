<?php
/*******************************************
	Copyright Rich Bellamy, RMB Webs, 2008
	Contact: rich@rmbwebs.com

	This file is part of Database Magic.

	Database Magic is free software: you can redistribute it and/or modify
	it under the terms of the GNU Lesser General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Database Magic is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Lesser General Public License for more details.

	You should have received a copy of the GNU Lesser General Public License
	along with Database Magic.  If not, see <http://www.gnu.org/licenses/>.
*******************************************/

include_once dirname(__FILE__) . '/../databasemagicconfig.php';

include_once dirname(__FILE__) . '/class_DatabaseMagicObject.php';

function dbm_debug($class, $message) {
	if (DBM_DEBUG) {
		echo "<pre class=\"$class\">\n";
		if (is_string($message)) {
			echo $message;
		} else {
			print_r($message);
		}
		echo "\n</pre>\n";
	}
}

if (DBM_DEBUG) { set_error_handler ("dbm_do_backtrace"); }

function dbm_do_backtrace ($one, $two) {
	echo "<pre>\nError {$one}, {$two}\n";
	debug_print_backtrace();
	echo "</pre>\n\n";
}

define('E_SQL_CANNOT_CONNECT', "
<h2>Cannot connect to SQL Server</h2>
There is an error in your DatabaseMagic configuration.
");


class DatabaseMagicExecution {

  /// An array that determines how the data for this object will be stored in the database
  /// Format is array(tablename => array(collumn1name => array(type, NULL, key, default, extras), column2name => array(...), etc.))
  protected $table_defs = array();

	private $sql_pass  = SQL_PASS;
	private $sql_user  = SQL_USER;
	private $sql_host  = SQL_HOST;
	private $sql_dbase = SQL_DBASE;
	private $sql_prfx  = SQL_TABLE_PREFIX;


	protected setTableDefs($defs) {
		$this->table_defs = $defs;
	}

	/**
	* function getCreationDefinition()
	* Returns the creation definition for a table column, used in add column, modify column, and create table
	*/
	protected function getCreationDefinition($field, $details) {
		if (!is_array($details)) {
			$details = array($details);
		}
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
	protected function getTableCreateQuery($customDefs) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];

		if (! isset($customDefs[$tableName])) {
			return NULL;
		}

		$table_def = $customDefs[$tableName];

		$rm      = "";
		$columns = "";
		$header  = "CREATE TABLE `".$this->sql_prfx.$tableName."` (\n  ";
		$comma   = "";

		$pri = array();

		foreach ($table_def as $field => $details) {
			$creationDefiniton = $this->getCreationDefinition($field, $details);
			$columns .= $comma.$creationDefiniton;
			$comma = ",\n  ";
			if ($details[2] == "PRI") {
				$pri[] = "`{$field}`";
			}
		}

		//$pri = $this->findKey($table_def);

		if (count($pri) > 0) { $columns .= $comma . "PRIMARY KEY (".implode(",", $pri).")"; }

		$footer = "\n) ENGINE=MyISAM DEFAULT CHARSET=latin1\n";

		$rm .= $header;
		$rm .= $columns;
		$rm .= $footer;

		return $rm;
	}

	/**
	* function findKey()
	* returns the name of the primary key for a particular table definition
	*/
	protected function findKey($def) {
		foreach ($def as $field => $details) {
			if ($details[2] == "PRI")
				return $field;
		}
		return NULL;
	}

	/**
	* function getSQLConnection()
	* Returns a valid SQL connection identifier based on the $SQLInfo setting above
	*/
	protected function getSQLConnection() {
		$sql   = mysql_connect($this->sql_host, $this->sql_user, $this->sql_pass) OR die(SQL_CANNOT_CONNECT);
						mysql_select_db($this->sql_dbase, $sql)             OR die(SQL_CANNOT_CONNECT);
		// Prep connection for strict error handling.
		mysql_query("set sql_mode=strict_all_Tables", $sql);
		return $sql;
	}

	/**
	* function getActualTableDefs()
	* Uses the "DESCRIBE" SQL keyword to get the actual definition of a table as it is in the MYSQL database
	*/
	protected function getActualTableDefs($tableName) {
		$sqlConnection = $this->getSQLConnection();
		$query = "DESCRIBE ".$this->sql_prfx.$tableName;
		if (! $results = mysql_query($query, $sqlConnection) ) {
			return FALSE;
		}
		$definition = array();
		while ($row = mysql_fetch_assoc($results)) {
			$definition[$row['Field']] = array ($row['Type'],
																					$row['Null'],
																					$row['Key'],
																					$row['Default'],
																					$row['Extra']);
		}
		return $definition;
	}

	protected function valuesFromSet($truevalues, $def) {
		/* $truevalues can be in either of two formats, OPTION=>true,OPTION2=>false or 0=>OPTION,1=>OPTION2
		* This is to accomodate one of the goals of this software, which is to always allow setAttribs($_POST)
		*/
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

	protected function sqlDataPrep($data, $columnDefs) {
		foreach ($data as $colname => $value) {
			if (is_array($value)) { // We likely have a SET column here
				$value = array_keys($value, true);
				$value = implode(',', $value);
				$data[$colname] = $value;
			}
		}
		return $data;
	}

	protected function sqlDataDePrep($data, $columnDefs) {
		foreach ($columnDefs as $colname => $def) {
			$def = (is_array($def)) ? $def[0] : $def;
// 			dbm_debug("info", strtoupper(substr($def, 0, 3))." for $colname");
			if ((strtoupper(substr($def, 0, 3)) == "SET") && array_key_exists($colname, $data)) {
				$values = explode(',', $data[$colname]);
				$data[$colname] = $this->valuesFromSet($values, $def);
			}
		}
		return $data;
	}

	/**
	* returns if the table exists in the current database
	*/
	protected function table_exists($tableName) {
		$sql = $this->getSQLConnection();
		$result = mysql_query("SHOW TABLES", $sql);
		while ($row = mysql_fetch_row($result)) {
			if ($row[0] == $this->sql_prfx.$tableName)
				return TRUE;
		}
		return FALSE;
	}

	protected function createTable($customDefs) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];
		$query = $this->getTableCreateQuery($customDefs);
		if ($query == NULL) return FALSE;
		dbm_debug("info", "Creating table $tableName");
		dbm_debug("system query", $query);
		$sql = $this->getSQLConnection();
		$result = mysql_query($query, $sql) OR die($query . "\n\n" . mysql_error());
		if ($result) {
			dbm_debug("info", "Success creating table $tableName");
			return TRUE;
		} else {
			dbm_debug("info", "Failed creating table $tableName");
			return FALSE;
		}
	}

	/**
	* updateTable()
	* Bring the table up to the current definition
	*/
	protected function updateTable($customDefs) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];

		if (! isset($customDefs[$tableName])) {
			return FALSE;
		}

		$wanteddef = $customDefs[$tableName];
		$actualdef = $this->getActualTableDefs($tableName);

		$sqlConnection = $this->getSQLConnection();

		// Set the primary keys
		$wantedKey = $this->findKey($wanteddef);
		$actualKey = $this->findKey($actualdef);
		if ($wantedKey != $actualKey) {
			if ($actualKey) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  DROP PRIMARY KEY";
				dbm_debug("server query", $query);
				if (! mysql_query($query, $sqlConnection) ) return FALSE;
			}
			if ($wantedKey) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  ADD PRIMARY KEY (".$wantedKey.")";
				dbm_debug("server query", $query);
				if (! mysql_query($query, $sqlConnection) ) return FALSE;
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
				if (! mysql_query($query, $sqlConnection) ) return FALSE;
			}
			// Find a column that needs modifying
			else if ($wanteddef[$name] != $actualdef[$name]) {
				$query  = "ALTER TABLE ".$this->sql_prfx.$tableName."\n";
				$query .= "  MODIFY COLUMN " . $creationDef . " " . $location;
				dbm_debug("server query", $query);
				if (! mysql_query($query, $sqlConnection) ) return FALSE;

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
				if (! mysql_query($query, $sqlConnection) ) return FALSE;
			}
		}

		return TRUE;
	}

	protected function makeQueryHappen($customDefs, $query) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];
		dbm_debug("regular query", $query);
		$sql = $this->getSQLConnection();
		$result = mysql_query($query, $sql);
		if (! $result) {
			// We have a problem here
			if (! $this->table_exists($tableName)) {
				dbm_debug("error", "Query Failed . . . table $tableName doesn't exist.");
				$this->createTable($customDefs);
			} else {
				if ($customDefs[$tableName] != $this->getActualTableDefs($tableName)) {
					dbm_debug("error", "Query Failed . . . table $tableName needs updating.");
					$this->updateTable($customDefs);
				}
			}
			dbm_debug("regular query", $query);
			$result = mysql_query($query, $sql);
			if (! $result) {
				// We tried :(
				dbm_debug("error", "Query Retry Failed . . . table $tableName could not be fixed.");
				return FALSE;
			}
		}
		// If we got to here, that means we have got a valid result!
		$queryArray = split(' ', $query);
		$command = strtoupper($queryArray[0]);
		switch ($command) {
		case 'SELECT':
			$returnVal = array();
			while ($row = mysql_fetch_assoc($result)) {
				$returnVal[] = $this->sqlDataDePrep($row, $customDefs[$tableName]);
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

/**
 * Helper for DatabaseMagicObject
 */
class DatabaseMagicPreparation extends DatabaseMagicExecution {

	/**
	* function sqlFilter()
	* Takes an array of data and returns the same array only all the data has been
	* cleaned up to prevent SQL Injection Attacks
	*/
	protected function sqlFilter($data) {
		// FIXME - This function needs to be written!
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

	protected function getTableColumnDefs($customDefs) {
		$tableName = $this->getTableName($customDefs);
		return (isset($customDefs[$tableName])) ? $customDefs[$tableName] : array();
	}

	/**
	* function getTableColumns(table definition) {
	* takes a table name and returns an array of table column names
	*/
	protected function getTableColumns($customDefs) {
		return array_keys($this->getTableColumnDefs($customDefs));
	}

	protected function findActualTableKey($tableName) {
		return $this->findKey($this->getActualTableDefs($tableName));
	}

	/**
	* function findTableKey(table definition) {
	* takes a table definition and returns the primary key for that table
	*/
	protected function findTableKey($tableDefs) {
		$tableNames = array_keys($tableDefs);
		$tableName = $tableNames[0];
		return $this->findKey($tableDefs[$tableName]);
	}

	protected function sqlMagicYank($customDefs, $params) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];

		$whereClause = $this->buildWhereClause($params);
		$query = "DELETE FROM ".$this->sql_prfx.$tableName." ".$whereClause;
		$data = $this->makeQueryHappen($customDefs, $query);

		if ($data) return TRUE;
		else       return FALSE;
	}

	protected function sqlMagicPut($customDefs, $data) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];

		$data = $this->sqlFilter($data);
		$data = $this->sqlDataPrep($data, $customDefs[$tableName]);
		$key = $this->findTableKey($customDefs);
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
		$query .= "INTO ".$this->sql_prfx.$tableName."\n  ".$columnList."\n  VALUES\n  ".$valueList;
		return $this->makeQueryHappen($customDefs, $query);
	}

	protected function sqlMagicGet($customDefs, $params) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];

		$whereClause = $this->buildWhereClause($params);

		$query = "SELECT * FROM ".$this->sql_prfx.$tableName." ".$whereClause;
		$data = $this->makeQueryHappen($customDefs, $query);

		if ($data) {
			// We have a successful Query!
			return $data;
		} else {
			// we didn't get valid data.
			return null;
		}
	}

	protected function sqlMagicSet($customDefs, $set, $where) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];

		$whereClause = $this->buildWhereClause($where);

		$setClause = " ";
		$setClauseLinker = "SET ";
		foreach ($set as $key => $value) {
			$setClause .= $setClauseLinker.$key.'="'.$value.'"';
			$setClauseLinker = " , ";
		}
		$query = "UPDATE ".$this->sql_prfx.$tableName.$setClause.$whereClause;
		$result = $this->makeQueryHappen($customDefs, $query);
		return $result;
	}

	protected function buildWhereClause($params=null) {
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

		$whereClause = "WHERE ";
		foreach ($params as $field => $target) {
			foreach ($target as $comparator => $value) {
				$whereClause .= "`{$field}` {$comparator} '{$value}' AND ";
			}
		}
		// Pull the final " AND" from the whereclause
		$whereClause = substr($whereClause, 0, -4);
		// check that we had at least ONE results
		if (strlen($whereClause) < 9) {
			return "";
		} else {
			return $whereClause;
		}
	}

	protected function getAllSomething($customDefs, $column, $limit=NULL, $offset=NULL, $params=NULL) {
		$tableNames = array_keys($customDefs);
		$tableName = $tableNames[0];
		$key = $this->findTableKey($customDefs);
		$column = (is_string($column)) ? $column : "*";

		$whereClause = $this->buildWhereClause($params);

		$query = "SELECT {$column} FROM ".$this->sql_prfx.$tableName." ".$whereClause." ORDER BY {$key}";

		if ($limit && is_numeric($limit)) {
			$query .= " LIMIT {$limit}";
		}
		if ($offset && is_numeric($offset)) {
			$query .= " OFFSET {$offset}";
		}

		$data = $this->makeQueryHappen($customDefs, $query);
		return $data;
	}

	protected function getAllIDs($customDefs, $limit=NULL, $offset=NULL, $params=NULL) {
		$key = $this->findTableKey($customDefs);
		$data = $this->getAllSomething($customDefs, $key, $limit, $offset, $params);
		if ($data) {
			$returnVal = array();
			foreach ($data as $row) {
				$returnVal[] = $row[$key];
			}
			return $returnVal;
		} else {
			return null;
		}
	}

	protected function getMapName($table1, $table2) {
		return "map_{$table1}_to_{$table2}";
	}

	protected function getMapDefs($parentDefs, $childDefs) {
		$parentTableName   = $this->getTableName($parentDefs);
		$parentTableDefs   = $parentDefs[$parentTableName];
		$parentTableKey    = $this->findKey($parentTableDefs);
		$parentTableKeyDef = $parentTableDefs[$parentTableKey];

		$childTableName   = $this->getTableName($childDefs);
		$childTableDefs   = $childDefs[$childTableName];
		$childTableKey    = $this->findKey($childTableDefs);
		$childTableKeyDef = $childTableDefs[$childTableKey];

		// We really only need the data type
		$parentTableKeyDef = array($parentTableKeyDef[0], "NO", "PRI");
		$childTableKeyDef = array($childTableKeyDef[0], "NO", "PRI");

		return array(
			'parentID' => $parentTableKeyDef,
			'childID'  => $childTableKeyDef,
			'relation' => array("varchar(20)",         "YES", "PRI"),
			'ordering' => array("int(11) unsigned",    "NO",  "",    "0",  "")
		);
	}

	protected function getChildrenList($parentTableDefs, $parentID, $childTableDefs, $params=NULL, $relation=NULL) {
		return $this->getMappedInnerJoin ($parentTableDefs, $parentID, $childTableDefs, $params, false, $relation);
	}

	protected function getParentsList($parentTableDefs, $parentID, $childTableDefs, $params=NULL, $relation=NULL) {
		return $this->getMappedInnerJoin ($parentTableDefs, $parentID, $childTableDefs, $params, true, $relation);
	}

	protected function getMappedInnerJoin ($parentTableDefs, $parentID, $childTableDefs, $params=NULL, $reverse=false, $relation=NULL) {
		$parentTableName = $this->getTableName($parentTableDefs);
		$childTableName  = $this->getTableName($childTableDefs);
		$childTableKey   = $this->findKey($childTableDefs[$childTableName]);

		if ($reverse) {
			$tableName = $this->getMapName($childTableName, $parentTableName);
			$childMapName = "parentID";
			$parentMapName = "childID";
		} else {
			$tableName = $this->getMapName($parentTableName, $childTableName);
			$childMapName = "childID";
			$parentMapName = "parentID";
		}

		$extendedWhere = "";
		if ($relation !== true) {  // True means match all, so exclude this test
			$extendedWhere .= "\n    AND ".$this->sql_prfx.$tableName.".relation='".mysql_real_escape_string($relation)."'";
		}
		if ($params != NULL) {
			foreach($params as $key => $value) {
				$extendedWhere .= "\n    AND ".$this->sql_prfx.$childTableName.".".$key."='".mysql_real_escape_string($value)."'";
			}
		}
		$query = "SELECT DISTINCT ".$this->sql_prfx.$childTableName.".*\n".
						"  FROM ".$this->sql_prfx.$childTableName."\n".
						"  INNER JOIN ".$this->sql_prfx.$tableName."\n".
						"    ON ".$this->sql_prfx.$childTableName.".".$childTableKey."=".$this->sql_prfx.$tableName.".".$childMapName."\n".
						"  WHERE ".$this->sql_prfx.$tableName.".".$parentMapName."='".$parentID."'".$extendedWhere."\n".
						"  ORDER BY ".$this->sql_prfx.$tableName.".ordering";

		$data = $this->makeQueryHappen($childTableDefs, $query);
		if ($data) {
			$returnVal = array();
			foreach ($data as $row) {
				$returnVal[] = $row;
			}
			return $returnVal;
		} else {
			return NULL;
		}
	}

	protected function reorderChildren ($parentTableDefs, $parentID, $childTableDefs, $childOrdering) {
		$mapName = $this->getMapName($this->getTableName($parentTableDefs), $this->getTableName($childTableDefs));
		$mapDefs  = $this->getMapDefs($parentTableDefs, $childTableDefs);
		foreach ($childOrdering as $child => $order) {
			$this->sqlMagicSet(array($mapName => $mapDefs), array('ordering' => $order), array('parentID' => $parentID, 'childID' => $child));
		}
		// That should do it
	}

	protected function doAdoption($parentTableDefs, $parentID, $childTableDefs, $childID, $relation=NULL) {
		$mapName = $this->getMapName($this->getTableName($parentTableDefs), $this->getTableName($childTableDefs));
		$mapDefs = $this->getMapDefs($parentTableDefs, $childTableDefs);
		$values = array('parentID' => $parentID, 'childID' => $childID);
		if ($relation != NULL) { $values['relation'] = $relation; }
		return $this->sqlMagicPut(array($mapName => $mapDefs), $values);
	}

	protected function doEmancipation($parentTableDefs, $parentID, $childTableDefs, $childID=NULL, $relation=NULL) {
		$mapName = $this->getMapName($this->getTableName($parentTableDefs), $this->getTableName($childTableDefs));
		$mapDefs = $this->getMapDefs($parentTableDefs, $childTableDefs);
		$values = array('parentID' => $parentID);
		if (!is_null($childID))  { $values['childID']  = $childID;  }
		if (!is_null($relation)) { $values['relation'] = $relation; }
		return $this->sqlMagicYank(array($mapName => $mapDefs), $values);
	}


	protected function getTableName($defs) {
		$tableNames = array_keys($defs);
		return $tableNames[0];
	}

	protected function getInitial($columnDef) {
		if (is_array($columnDef)) {
			$columnDef = $columnDef[0];
		}
		if (strtoupper(substr($columnDef, 0, 3)) == "SET") {
			return valuesFromSet(array(), $columnDef);
		} else {
			return null;
		}
	}

}

/// Backend for the DatabaseMagicObject
class DatabaseMagicFeatures extends DatabaseMagicPreparation {

  /// Object status.
  /// Possible statuses are "needs saving", etc.
  protected $status = array();

  /// Object attributes are the data that is stored in the object and is saved to the database.
  /// Every instance of a DatabaseMagicObject has an array of attributes.  Each attribute corresponds
  /// to a column in the database table, and each Object corresponds to a row in the table.
  /// Through member functions, attributes can be read and set to and from an object.
  protected $attributes = array();



	/// Calls initialize() and calls load($id) if $id != null
	/// Also marks the object for saving in the event of an unloadable $id
  function __construct($id = NULL) {
		$this->extendTableDefs();
    $this->initialize();
    if ($id != NULL) {
      $loadResult = $this->load($id);
      if (!$loadResult) {
        // The load failed. . . a never-before-seen primary ID is being explicitly set by the constructor.
        // Mark it dirty so we are sure that it saves.
        dbm_debug("failedload", "load failed");
        $this->setAttribs(array($this->findTableKey($this->getTableDefs()) => $id), true);
      }
    }
  }

	/// Sets all the attributes to blank and the table key to null.
	/// used for initializing new blank objects.
	function initialize() {
		if ((!is_array($this->table_def_extensions)) && (is_string($this->table_def_extensions))) {
			$tablename = $this->table_def_extensions;
			$this->table_def_extensions = array($tablename => $this->getActualTableDefs($tablename));
		}
		$defs = $this->getTableDefs();
		if (is_array($defs)) {
			$cols = $this->getTableColumnDefs($defs);
			foreach ($cols as $col => $coldef) {
				$this->attributes[$col] = $this->getInitial($coldef);
				$this->status[$col] = "clean";
			}
		}
	}

	/** Loads an object from the database.
	 *  This function loads the attributes for itself from the database, where the table primary key = $id
	 *  Normally called from the constructor, it *could* also be used to change the row that an existing object
	 *  is working on, but just making a new object is probably preferable, unless you really know what you are doing.
	 */
	function load($id) {
		dbm_debug("load", "Loading a " . get_class($this) . " with ID = " . $id);
		$key = $this->findTableKey($this->getTableDefs());
		$query = array($key => $id);
		$info = $this->sqlMagicGet($this->getTableDefs(), $query);
		if ($info && is_array($info) && count($info) > 0) {
			$this->setAttribs($info[0], true); // $info[0] because sqlMagicget always returns an array, even with one result.
			foreach ($info[0] as $col => $value) {
				$this->status[$col] = "clean";
			}
			return true;
		} else {
			return false;
		}
	}

  /// Returns the array of attributes for the object.
  function getAttribs() {
		$returnMe = $this->attributes;

		$key = $this->findTableKey($this->getTableDefs());
		if ($returnMe[$key] == NULL) {
			// Unsaved Object, don't return the key attribute with the results
			unset($returnMe[$key]);
		}

    return $returnMe;
  }

  /// Sets attribute (row) data for this object.
  /// $clobberID is a bool that must be true to allow you to overwrite a primary key
  function setAttribs($info, $clobberID = false) {
		dbm_debug("setattribs", $info);
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

	function __get($name) {
		$a = $this->getAttribs();
		return (isset($a[$name])) ? $a[$name] : null;
	}

	function __set($name, $value) {
		$this->setAttribs(array($name => $value));
	}

	/**
	 * Does the actual work for getLinks and getBackLinks
	 */
	function doGetLinks($example, $parameters = NULL, $relation = NULL, $backLinks=false) {
		if (is_object($example)) {
			$prototype = clone $example;
			$prototype->initialize();
		} else if (is_string($example) && class_exists($example)) {
			$prototype = new $example;
		} else {
			return NULL;
		}

		$parentTableDefs = $this->getTableDefs();
		$parentID        = $this->getPrimary();
		$childTableDefs  = $prototype->getTableDefs();

		if ($backLinks) {
			$list =  $this->getParentsList($parentTableDefs, $parentID, $childTableDefs, $parameters, $relation);
		} else {
			$list = $this->getChildrenList($parentTableDefs, $parentID, $childTableDefs, $parameters, $relation);
		}

		$children = array();
		if (is_array($list)) {
			foreach($list as $childid => $attribs) {
				$temp = clone $prototype;
				$temp->setAttribs($attribs, true);
				$children[] = $temp;
			}
		}
		return $children;
	}

	/// Tells you the column name that holds the primary
	function getPrimaryKey() {
    return $this->findTableKey($this->getTableDefs());
	}

	/** Returns the value of this object's primary key.
	 * Primary key is the unique id for each object, used in the constructor and the load function
	 * for example:
	 *   $obj = new DatabaseMagicObject($key);
	 *   $key2 = $obj->getPrimary();
	 *   $key2 == $key
	 */
	function getPrimary() {
    $key = $this->getPrimaryKey();
    return $this->attributes[$key];
	}

	/// Retrieve an array of all the known IDs for all saved instances of this class
	/// If you plan on foreach = new Blah(each), I suggest using getAllLikeMe instead, your database will thank you
	function getAllPrimaries($limit=NULL, $offset=NULL, $params=NULL) {
		$list = $this->getAllIDs($this->getTableDefs(), $limit, $offset, $params);
		return $list;
	}

	/// Retrieve an array of pre-loaded objects
	function getAllLikeMe($limit=NULL, $offset=NULL, $params=NULL) {
		$myDefs = $this->getTableDefs();
		$list = $this->getAllSomething($myDefs, "*", $limit, $offset, $params);
		$key = $this->findTableKey($myDefs);
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

	/// Returns the table definitions for this object
	function getTableDefs() {
		return $this->table_defs;
	}

	/// Recursively merges in any table definitions from extended classes
	function extendTableDefs() {
		if (get_class($this)==__CLASS__) {
			// We are a DatabaseMagicFeatures
			return true;
		}else {
			// We are something that extends DatabaseMagicObject, and don't know the actual table defs
			$parentClass = get_parent_class($this);
			$parent = new $parentClass;
			$parentTableDefs = $parent->getTableDefs();
				// Bail out if we don't get an array for the extended class table def
				if (!is_array($parentTableDefs)) { return false; }
			$parentTableName = $parent->getMyTableName();
			$parentDefs      = $parentTableDefs[$parentTableName];
			$parentPrimary   = $this->findKey($parentDefs);
			$myTableDefs = $this->table_def_extensions;
			$myTableName = $this->getMyTableName();
			$myDefs      = $myTableDefs[$myTableName];
			$myPrimary   = $this->findKey($myDefs);

			// Build the merged table
			$mergedDefs = array();
			// If we have two primary keys, drop the parent key
			if ($myPrimary && $parentPrimary) {
				unset($parentDefs[$parentPrimary]);
			}
			// Do parentdefs first so they are first in the list, and so myDefs can overwrite a collision
			foreach ($parentDefs as $key => $value) {
					$mergedDefs[$key] = $value;
			}
			// Follow with myDefs
			foreach ($myDefs as $key => $value) {
				$mergedDefs[$key] = $value;
			}

			$result = array($myTableName => $mergedDefs);
			// Cache the result
			$this->setTableDefs($result);
			return true
		}
	}

  /// Returns the name of the table that this object saves and loads under.
  /// Pretty easy function really.
  function getMyTableName() {
		return $this->getTableName($this->table_defs);
  }

  /// An alias for the getPrimary() method.  \deprecated
  function getID() {
		return $this->getPrimary();
  }

	/// Dumps the contents of attribs via print_r()
	/// Useful for debugging, but that's about it
	function dumpview($pre=false) {
		if ($pre) echo "<pre style=\"color: blue\">\n";
		echo "Attributes for this ".get_class($this).":\n";;
		print_r($this->attributes);
		if ($pre) echo "</pre>\n";
	}

}


/**
 * This object makes it easy for a developer to create abstract objects which can save themselves
 * into and load themselves from an SQL database.  Objects are defined by setting a large array which
 * describes the way the data is stored in the database
 */
class DatabaseMagicInterface extends DatabaseMagicFeatures {

	/**
	 * Used to set or get the info for this object.
	 * Filters bad info or unknown data that won't go into our database table.
	 */
	function attribs($info=null) {
		if (!is_null($info)) {
			$this->setAttribs($info);
		}
		return $this->getAttribs();
	}

	/// Can be used to set the order that a call for links will return as.
	function orderLinks($example, $ordering) {
		$childTableDefs  = $example->getTableDefs();
		$parentTableDefs = $this->getTableDefs();
		$parentID    = $this->getID();

		$this->reorderChildren($parentTableDefs, $parentID, $childTableDefs, $ordering);
	}

	/**
	 * Creates a link to another instance or extension of DatabaseMagicObject.
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

		$subjectTableDefs  = $subject->getTableDefs();
		$subjectID         = $subject->getID();
		$parentTableDefs = $this->getTableDefs();
		$parentID        = $this->getID();

		return $this->doAdoption($parentTableDefs, $parentID, $subjectTableDefs, $subjectID, $relation);
	}

	/** Breaks a link previously created by link()
	 * B will no longer be returned as part of A->getLinks() after A->deLink(B) is called.
	 */
	function deLink($subject, $relation=NULL) {
		$subjectTableDefs  = $subject->getTableDefs();
		$subjectID     = $subject->getID();
		$parentTableDefs = $this->getTableDefs();
		$parentID    = $this->getID();

		return $this->doEmancipation($parentTableDefs, $parentID, $subjectTableDefs, $subjectID, $relation);
	}

	/** Breaks links to all previously linked $example.
	 * $example can be either a string of the classname, or an instance of the class itself
	 */
	function deLinkAll($example, $relation=NULL) {
		if (is_string($example)) {
			$subject = new $example;
		} else {
			$subject = $example;
		}
		$subjectTableDefs  = $subject->getTableDefs();
		$subjectID     = $subject->getID();
		$parentTableDefs = $this->getTableDefs();
		$parentID    = $this->getID();

		return $this->doEmancipation($parentTableDefs, $parentID, $subjectTableDefs, NULL, $relation);
	}

	/**
	 * Retrieve a list of this object's previously linked objects of a specific type.
	 * Use this function to retrieve a list of objects previously linked  by this object
	 * using the link() method.
	 * $example can be the name of the class you want to retrieve, or an example object of the same type as those
	 * children you want to retrieve.
	 *
	 * Example: \n
	 * $fido = new Dog("Fido"); \n
	 * $fam = new Family("Smith"); \n
	 * $bob = new Person("Bob"); \n
	 * $fam->link($bob); \n
	 * $fam->link($fido); \n
	 * $fam->getLinks("Dog");  // Returns an Array that contains Fido and any other Dogs linked in to the Smith Family \n
	 * $fam->getLinks("Person");  // Returns an Array that contains Bob and any other Persons linked in to the Smith Family \n
	 */
	function getLinks($example, $parameters = NULL, $relation=NULL) {
		return $this->doGetLinks($example, $parameters, $relation, false);
	}
	/**
	 * Works in reverse to getLinks().
	 * A->link(B); \n
	 * C = B->getBackLinks("classname of A"); \n
	 * C is an array that contains A \n
	 */
	function getBackLinks($example, $parameters=NULL, $relation=NULL) {
		return $this->doGetLinks($example, $parameters, $relation, true);
	}

	/// Saves the object data to the database.
	/// This function records the attributes of the object into a row in the database.
	function save($force = false) {
		$defs = $this->getTableDefs();
		$columns = $this->getTableColumns($defs);
		$allclean = array();
		$savedata = array();
		$key = $this->findTableKey($defs);
		$a = $this->getAttribs();
		if (!isset($a[$key]) || ($a[$key] == null)) {
			// This object has never been saved, force save regardless of status
			// It's very probable that this object is being linked to or is linking another object and needs an ID
			$force = true;
			// Exclude the ID in the sql query.  This will trigger an auto_increment ID to be generated
			$excludeID = true;
			dbm_debug("info", "This ".get_class($this)." is new, and we are saving all attributes regardless of status");
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
			$id = $this->sqlMagicPut($defs, $savedata);

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

}


?>