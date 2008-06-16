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

set_error_handler ("do_backtrace");
function do_backtrace ($one, $two) {
	echo "<pre>\nError {$one}, {$two}\n";
	debug_print_backtrace();
	echo "</pre>\n\n";
}

define('E_SQL_CANNOT_CONNECT', "
<h2>Cannot connect to SQL Server</h2>
There is an error in your DatabaseMagic configuration.
");


/**
 * getTableCreateQuery()
 * returns the query string that can be used to create a table based on it's definition
 */
function getTableCreateQuery($customDefs) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

  if (! isset($customDefs[$tableName])) {
    return NULL;
  }

  $table_def = $customDefs[$tableName];

  $rm      = "";
  $columns = "";
  $header  = "CREATE TABLE `".SQL_TABLE_PREFIX.$tableName."` (\n  ";
  $comma   = "";

	$pri = array();

  foreach ($table_def as $field => $details) {
    $creationDefiniton = getCreationDefinition($field, $details);
    $columns .= $comma.$creationDefiniton;
    $comma = ",\n  ";
		if ($details[2] == "PRI") {
			$pri[] = "`{$field}`";
		}
  }

  //$pri = findKey($table_def);

  if (count($pri) > 0) { $columns .= $comma . "PRIMARY KEY (".implode(",", $pri).")"; }

  $footer = "\n) ENGINE=MyISAM DEFAULT CHARSET=latin1\n";

  $rm .= $header;
  $rm .= $columns;
  $rm .= $footer;

  return $rm;
}

/**
 * function sqlFilter()
 * Takes an array of data and returns the same array only all the data has been
 * cleaned up to prevent SQL Injection Attacks
 */
function sqlFilter($data) {
  // FIXME - This function needs to be written!
  $sql = getSQLConnection();
  $retVal = array();
  if (is_array($data)) {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $retVal[$key] = sqlFilter($value);  // OMG Scary Recursion! :)
      } else {
        $retVal[$key] = mysql_real_escape_string($value, $sql);
      }
    }
  }
  return $retVal;
}

/**
 * function getSQLConnection()
 * Returns a valid SQL connection identifier based on the $SQLInfo setting above
 */
function getSQLConnection() {
  $sql   = mysql_connect(SQL_HOST, SQL_USER, SQL_PASS)  OR die(SQL_CANNOT_CONNECT);
           mysql_select_db(SQL_DBASE, $sql)             OR die(SQL_CANNOT_CONNECT);
	// Prep connection for strict error handling.
	mysql_query("set sql_mode=strict_all_Tables", $sql);
  return $sql;
}

/**
 * function getActualTableDefs()
 * Uses the "DESCRIBE" SQL keyword to get the actual definition of a table as it is in the MYSQL database
 */
function getActualTableDefs($tableName) {
  $sqlConnection = getSQLConnection();
  $query = "DESCRIBE ".SQL_TABLE_PREFIX.$tableName;
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

/**
 * function getCreationDefinition()
 * Returns the creation definition for a table column, used in add column, modify column, and create table
 */
function getCreationDefinition($field, $details) {
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

function getTableColumnDefs($customDefs) {
	$tableName = getTableName($customDefs);
	return (isset($customDefs[$tableName])) ? $customDefs[$tableName] : array();
}

/**
 * function getTableColumns(table definition) {
 * takes a table name and returns an array of table column names
 */
function getTableColumns($customDefs) {
	return array_keys(getTableColumnDefs($customDefs));
}

function findActualTableKey($tableName) {
	return findKey(getActualTableDefs($tableName));
}

/**
 * function findTableKey(table definition) {
 * takes a table definition and returns the primary key for that table
 */
function findTableKey($tableDefs) {
	$tableNames = array_keys($tableDefs);
	$tableName = $tableNames[0];
	return findKey($tableDefs[$tableName]);
}

/**
 * function findKey()
 * returns the name of the primary key for a particular table definition
 */
function findKey($def) {
	foreach ($def as $field => $details) {
		if ($details[2] == "PRI")
			return $field;
	}
	return NULL;
}

/**
 * updateTable()
 * Bring the table up to the current definition
 */
function updateTable($customDefs) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

  if (! isset($customDefs[$tableName])) {
    return FALSE;
  }

  $wanteddef = $customDefs[$tableName];
  $actualdef = getActualTableDefs($tableName);

  $sqlConnection = getSQLConnection();

  // Set the primary keys
  $wantedKey = findKey($wanteddef);
  $actualKey = findKey($actualdef);
  if ($wantedKey != $actualKey) {
    if ($actualKey) {
      $query  = "ALTER TABLE ".SQL_TABLE_PREFIX.$tableName."\n";
      $query .= "  DROP PRIMARY KEY";
      dbm_debug("server query", $query);
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
    }
    if ($wantedKey) {
      $query  = "ALTER TABLE ".SQL_TABLE_PREFIX.$tableName."\n";
      $query .= "  ADD PRIMARY KEY (".$wantedKey.")";
      dbm_debug("server query", $query);
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
    }
  }

  // Run through the wanted definition for what needs changing
  $location = "FIRST";
  foreach($wanteddef as $name => $options) {
    $creationDef = getCreationDefinition($name, $options);
    // Find a column that needs creating
    if (! isset($actualdef[$name]) ) {
      $query  = "ALTER TABLE ".SQL_TABLE_PREFIX.$tableName."\n";
      $query .= "  ADD COLUMN " . $creationDef . " " . $location;
      dbm_debug("server query", $query);
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
    }
    // Find a column that needs modifying
    else if ($wanteddef[$name] != $actualdef[$name]) {
      $query  = "ALTER TABLE ".SQL_TABLE_PREFIX.$tableName."\n";
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
      $query  = "ALTER TABLE ".SQL_TABLE_PREFIX.$tableName."\n";
      $query .= "  DROP COLUMN " . $name;
      dbm_debug("server query", $query);
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
    }
  }

  return TRUE;
}

function sqlMagicYank($customDefs, $params) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

	$whereClause = buildWhereClause($params);
	$query = "DELETE FROM ".SQL_TABLE_PREFIX.$tableName." ".$whereClause;
	$data = makeQueryHappen($customDefs, $query);

	if ($data) return TRUE;
	else       return FALSE;
}

function sqlDataPrep($data, $columnDefs) {
	foreach ($data as $colname => $value) {
		if (is_array($value)) { // We likely have a SET column here
			$value = array_keys($value, true);
			$value = implode(',', $value);
			$data[$colname] = $value;
		}
	}
	return $data;
}

function sqlDataDePrep($data, $columnDefs) {
	foreach ($columnDefs as $colname => $def) {
		$def = (is_array($def)) ? $def[0] : $def;
		dbm_debug("info", strtoupper(substr($def, 0, 3))." for $colname");
		if ((strtoupper(substr($def, 0, 3)) == "SET") && array_key_exists($colname, $data)) {
			$values = explode(',', $data[$colname]);
			$data[$colname] = valuesFromSet($values, $def);
		}
	}
	return $data;
}

function valuesFromSet($truevalues, $def) {
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

function sqlMagicPut($customDefs, $data) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

  $data = sqlFilter($data);
  $data = sqlDataPrep($data, $customDefs[$tableName]);
  $key = findTableKey($customDefs);
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
  $query .= "INTO ".SQL_TABLE_PREFIX.$tableName."\n  ".$columnList."\n  VALUES\n  ".$valueList;
  return makeQueryHappen($customDefs, $query);
}

function sqlMagicGet($customDefs, $params) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

	$whereClause = buildWhereClause($params);

	$query = "SELECT * FROM ".SQL_TABLE_PREFIX.$tableName." ".$whereClause;
	$data = makeQueryHappen($customDefs, $query);

	if ($data) {
		// We have a successful Query!
		return $data;
	} else {
		// we didn't get valid data.
		return null;
	}
}

function sqlMagicSet($customDefs, $set, $where) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

	$whereClause =buildWhereClause($where);
	
	$setClause = " ";
	$setClauseLinker = "SET ";
	foreach ($set as $key => $value) {
		$setClause .= $setClauseLinker.$key.'="'.$value.'"';
		$setClauseLinker = " , ";
	}
	$query = "UPDATE ".SQL_TABLE_PREFIX.$tableName.$setClause.$whereClause;
	$result = makeQueryHappen($customDefs, $query);
	return $result;
}

function makeQueryHappen($customDefs, $query) {

	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];
	dbm_debug("regular query", $query);
  $sql = getSQLConnection();
  $result = mysql_query($query, $sql);
  if (! $result) {
    // We have a problem here
    if (! table_exists($tableName)) {
			dbm_debug("error", "Query Failed . . . table $tableName doesn't exist.");
      createTable($customDefs);
    } else {
      if ($customDefs[$tableName] != getActualTableDefs($tableName)) {
				dbm_debug("error", "Query Failed . . . table $tableName needs updating.");
        updateTable($customDefs);
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
      $returnVal[] = sqlDataDePrep($row, $customDefs[$tableName]);
    }
    return $returnVal;
    break;
  case 'INSERT':
  case 'REPLACE':
    return mysql_insert_id($sql);
    break;
  }
}

/**
 * returns if the table exists in the current database
 */
function table_exists($tableName) {
  $sql = getSQLConnection();
  $result = mysql_query("SHOW TABLES", $sql);
  while ($row = mysql_fetch_row($result)) {
    if ($row[0] == SQL_TABLE_PREFIX.$tableName)
      return TRUE;
  }
  return FALSE;
}

function createTable($customDefs) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];
	$query = getTableCreateQuery($customDefs);
	if ($query == NULL) return FALSE;
	dbm_debug("info", "Creating table $tableName");
	dbm_debug("system query", $query);
	$sql = getSQLConnection();
	$result = mysql_query($query, $sql) OR die($query . "\n\n" . mysql_error());
	if ($result) {
		dbm_debug("info", "Success creating table $tableName");
		return TRUE;
	} else {
		dbm_debug("info", "Failed creating table $tableName");
		return FALSE;
	}
}

function buildWhereClause($params=null) {
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

function getAllSomething($customDefs, $column, $limit=NULL, $offset=NULL, $params=NULL) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];
	$key = findTableKey($customDefs);
	$column = (is_string($column)) ? $column : "*";

	$whereClause = buildWhereClause($params);

	$query = "SELECT {$column} FROM ".SQL_TABLE_PREFIX.$tableName." ".$whereClause." ORDER BY {$key}";

	if ($limit && is_numeric($limit)) {
		$query .= " LIMIT {$limit}";
	}
	if ($offset && is_numeric($offset)) {
		$query .= " OFFSET {$offset}";
	}

	$data = makeQueryHappen($customDefs, $query);
	return $data;
}

function getAllIDs($customDefs, $limit=NULL, $offset=NULL, $params=NULL) {
	$key = findTableKey($customDefs);
	$data = getAllSomething($customDefs, $key, $limit, $offset, $params);
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

function getMapName($table1, $table2) {
  return "map_{$table1}_to_{$table2}";
}

function getMapDefs($parentDefs, $childDefs) {
	$parentTableName   = getTableName($parentDefs);
	$parentTableDefs   = $parentDefs[$parentTableName];
	$parentTableKey    = findKey($parentTableDefs);
	$parentTableKeyDef = $parentTableDefs[$parentTableKey];

	$childTableName   = getTableName($childDefs);
	$childTableDefs   = $childDefs[$childTableName];
	$childTableKey    = findKey($childTableDefs);
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

function getChildrenList($parentTableDefs, $parentID, $childTableDefs, $params=NULL, $relation=NULL) {
	return getMappedInnerJoin ($parentTableDefs, $parentID, $childTableDefs, $params, false, $relation);
}

function getParentsList($parentTableDefs, $parentID, $childTableDefs, $params=NULL, $relation=NULL) {
	return getMappedInnerJoin ($parentTableDefs, $parentID, $childTableDefs, $params, true, $relation);
}

function getMappedInnerJoin ($parentTableDefs, $parentID, $childTableDefs, $params=NULL, $reverse=false, $relation=NULL) {
  $parentTableName = getTableName($parentTableDefs);
  $childTableName  = getTableName($childTableDefs);
  $childTableKey   = findKey($childTableDefs[$childTableName]);

	if ($reverse) {
		$tableName = getMapName($childTableName, $parentTableName);
		$childMapName = "parentID";
		$parentMapName = "childID";
	} else {
		$tableName = getMapName($parentTableName, $childTableName);
		$childMapName = "childID";
		$parentMapName = "parentID";
	}

  $extendedWhere = "";
	if ($relation !== true) {  // True means match all, so exclude this test
		$extendedWhere .= "\n    AND ".SQL_TABLE_PREFIX.$tableName.".relation='".mysql_real_escape_string($relation)."'";
	}
  if ($params != NULL) {
    foreach($params as $key => $value) {
      $extendedWhere .= "\n    AND ".SQL_TABLE_PREFIX.$childTableName.".".$key."='".mysql_real_escape_string($value)."'";
    }
  }
  $query = "SELECT DISTINCT ".SQL_TABLE_PREFIX.$childTableName.".*\n".
           "  FROM ".SQL_TABLE_PREFIX.$childTableName."\n".
           "  INNER JOIN ".SQL_TABLE_PREFIX.$tableName."\n".
           "    ON ".SQL_TABLE_PREFIX.$childTableName.".".$childTableKey."=".SQL_TABLE_PREFIX.$tableName.".".$childMapName."\n".
           "  WHERE ".SQL_TABLE_PREFIX.$tableName.".".$parentMapName."='".$parentID."'".$extendedWhere."\n".
           "  ORDER BY ".SQL_TABLE_PREFIX.$tableName.".ordering";

  $data = makeQueryHappen($childTableDefs, $query);
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

function reorderChildren ($parentTableDefs, $parentID, $childTableDefs, $childOrdering) {
	$mapName = getMapName(getTableName($parentTableDefs), getTableName($childTableDefs));
	$mapDefs  = getMapDefs($parentTableDefs, $childTableDefs);
	foreach ($childOrdering as $child => $order) {
		sqlMagicSet(array($mapName => $mapDefs), array('ordering' => $order), array('parentID' => $parentID, 'childID' => $child));
  }
  // That should do it
}

function doAdoption($parentTableDefs, $parentID, $childTableDefs, $childID, $relation=NULL) {
	$mapName = getMapName(getTableName($parentTableDefs), getTableName($childTableDefs));
	$mapDefs = getMapDefs($parentTableDefs, $childTableDefs);
	$values = array('parentID' => $parentID, 'childID' => $childID);
	if ($relation != NULL) { $values['relation'] = $relation; }
	return sqlMagicPut(array($mapName => $mapDefs), $values);
}

function doEmancipation($parentTableDefs, $parentID, $childTableDefs, $childID=NULL, $relation=NULL) {
	$mapName = getMapName(getTableName($parentTableDefs), getTableName($childTableDefs));
	$mapDefs = getMapDefs($parentTableDefs, $childTableDefs);
	$values = array('parentID' => $parentID);
	if (!is_null($childID))  { $values['childID']  = $childID;  }
	if (!is_null($relation)) { $values['relation'] = $relation; }
	return sqlMagicYank(array($mapName => $mapDefs), $values);
}


function getTableName($defs) {
	$tableNames = array_keys($defs);
	return $tableNames[0];
}

function getInitial($columnDef) {
	if (is_array($columnDef)) {
		$columnDef = $columnDef[0];
	}
	if (strtoupper(substr($columnDef, 0, 3)) == "SET") {
		return valuesFromSet(array(), $columnDef);
	} else {
	return "";
	}
}

?>