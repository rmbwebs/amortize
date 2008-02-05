<?php
set_error_handler ("do_backtrace");
function do_backtrace ($one, $two) {
	echo "<pre>\nError {$one}, {$two}\n";
	debug_print_backtrace();
	echo "</pre>\n\n";
}

include_once dirname(__FILE__) . '/../databasemagicconfig.php';

include_once dirname(__FILE__) . '/class_DatabaseMagicObject.php';

define('E_SQL_CANNOT_CONNECT', "
<h2>Cannot connect to SQL Server</h2>
There is an error in your DatabaseMagic configuration.
");


/************************************
 * getTableCreateQuery()
 * returns the query string that can be used to create a table based on it's definition
 ***********************************/
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

  foreach ($table_def as $field => $details) {
    $creationDefiniton = getCreationDefinition($field, $details);
    $columns .= $comma.$creationDefiniton;
    $comma = ",\n  ";
  }

  $pri = findKey($table_def);

  if ($pri != NULL) { $columns .= $comma . "PRIMARY KEY (`".$pri."`)"; }

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

/****************************************
 * function getSQLConnection()
 * Returns a valid SQL connection identifier based on the $SQLInfo setting above
 ***************************************/
function getSQLConnection() {
  $sql   = mysql_connect(SQL_HOST, SQL_USER, SQL_PASS)  OR die(SQL_CANNOT_CONNECT);
           mysql_select_db(SQL_DBASE, $sql)             OR die(SQL_CANNOT_CONNECT);
  return $sql;
}

/****************************************
 * function getActualTableDefs()
 * Uses the "DESCRIBE" SQL keyword to get the actual definition of a table as it is in the MYSQL database
 ***************************************/
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

/*******************************************
 * function getCreationDefinition()
 * Returns the creation definition for a table column, used in add column, modify column, and create table
 ******************************************/
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

/*******************************************
 * function getTableColumns(table definition) {
 * takes a table name and returns an array of table column names
 ******************************************/
function getTableColumns($customDefs) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

	if (! isset($customDefs[$tableName])) {
		return FALSE;
	}
	$table_def = $customDefs[$tableName];

	$returnVal = array_keys($table_def);

	return $returnVal;
}

function findActualTableKey($tableName) {
	return findKey(getActualTableDefs($tableName));
}

/*******************************************
 * function findTableKey(table definition) {
 * takes a table definition and returns the primary key for that table
 ******************************************/
function findTableKey($tableDefs) {
	$tableNames = array_keys($tableDefs);
	$tableName = $tableNames[0];
	return findKey($tableDefs[$tableName]);
}

/*******************************************
 * function findKey()
 * returns the name of the primary key for a particular table definition
 ******************************************/
function findKey($def) {
	foreach ($def as $field => $details) {
		if ($details[2] == "PRI")
			return $field;
	}
	return NULL;
}

/*******************************************
 * updateTable()
 * Bring the table up to the current definition
 ******************************************/
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
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
    }
    if ($wantedKey) {
      $query  = "ALTER TABLE ".SQL_TABLE_PREFIX.$tableName."\n";
      $query .= "  ADD PRIMARY KEY (".$wantedKey.")";
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
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
}
    // Find a column that needs modifying
    else if ($wanteddef[$name] != $actualdef[$name]) {
      $query  = "ALTER TABLE ".SQL_TABLE_PREFIX.$tableName."\n";
      $query .= "  MODIFY COLUMN " . $creationDef . " " . $location;
      if (! mysql_query($query, $sqlConnection) ) return FALSE;

    }
    // Change location so it will be set properly for the next iteration
    $location = "AFTER ".$name;
  }

  // SCARY
  // Run through the actual definition for what needs dropping
  foreach($actualdef as $name => $options) {
    // Find a column that needs deleting
    if (! isset($wanteddef[$name]) ) {
      $query  = "ALTER TABLE ".SQL_TABLE_PREFIX.$tableName."\n";
      $query .= "  DROP COLUMN " . $name;
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
}
  }

  return TRUE;
}

function sqlMagicYank($customDefs, $params) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

	$whereClause = " ";
  $whereClauseLinker = "WHERE ";
  foreach ($params as $key => $value) {
    $whereClause .= $whereClauseLinker.$key."='".$value."'";
    // If there is more than one requirement, we need to link the params
    // together with a linker
    $whereClauseLinker = " AND ";
  }
  $query = "DELETE FROM ".SQL_TABLE_PREFIX.$tableName.$whereClause;
  $data = makeQueryHappen($customDefs, $query);

  if ($data) return TRUE;
  else       return FALSE;
}

function sqlMagicPut($customDefs, $data) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

  $data = sqlFilter($data);
  $key = findTableKey($customDefs);
  if ( ($key) && (isset($data[$key])) && ($data[$key] == 0) ) {
    $query = "INSERT ";
  } else {
    $query = "REPLACE ";
  }
  $columnList = "(";
  $valueList  = "(";
  $comma      = "";
  foreach ($data as $column => $value) {
    $columnList .= $comma."`".$column."`";
    $valueList  .= $comma."'".$value."'";
    $comma = ", ";
  }
  $columnList .= ")";
  $valueList  .= ")";
  $query .= "INTO ".SQL_TABLE_PREFIX.$tableName."\n  ".$columnList."\n  VALUES\n  ".$valueList;
  return makeQueryHappen($customDefs, $query);
}

function sqlMagicSet($customDefs, $set, $where) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

	$whereClause = " ";
  $whereClauseLinker = "WHERE ";
  foreach ($where as $key => $value) {
    $whereClause .= $whereClauseLinker.$key."='".$value."'";
    $whereClauseLinker = " AND ";
  }
  $setClause = " ";
  $setClauseLinker = "SET ";
  foreach ($set as $key => $value) {
    $setClause .= $setClauseLinker.$key."='".$value."'";
    $setClauseLinker = " , ";
  }
  $query = "UPDATE ".SQL_TABLE_PREFIX.$tableName.$setClause.$whereClause;
  $result = makeQueryHappen($customDefs, $query);
  return $result;
}

function sqlMagicGet($customDefs, $params) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

	$whereClause = " ";
  $whereClauseLinker = "WHERE ";
  foreach ($params as $key => $value) {
    $whereClause .= $whereClauseLinker.$key."='".$value."'";
    // If there is more than one requirement, we need to link the params
    // together with a linker
    $whereClauseLinker = " AND ";
  }
  $query = "SELECT * FROM ".SQL_TABLE_PREFIX.$tableName.$whereClause;
  $data = makeQueryHappen($customDefs, $query);

  if ($data) {
    // We have a successful Query!
    return $data;
  } else {
    // we didn't get valid data.
//     return $params; WTF?
		return null;
  }
}

function makeQueryHappen($customDefs, $query) {
// echo "<p style='color:red;'>$query</p>\n<pre style='color:red;'>";
// debug_print_backtrace();
// echo "</pre>\n";

	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];
  //echo "Running Query: \n<font color=red>\n". $query . "\n</font><br />\n";
  $sql = getSQLConnection();
  $result = mysql_query($query, $sql);
  if (! $result) {
    // We have a problem here
    if (! table_exists($tableName)) {
      createTable($customDefs);
    } else {
      if ($customDefs[$tableName] != getActualTableDefs($tableName)) {
        updateTable($customDefs);
      }
    }
    $result = mysql_query($query, $sql);
    if (! $result) {
			// We tried :(
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

/**********************************
 * returns if the table exists in the current database
 **********************************/
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
	//echo "Proper table does not exist.  Creating: \n<font color=red>\n". $query . "\n</font><br />\n";
	$sql = getSQLConnection();
	$result = mysql_query($query, $sql) OR die($query . "\n\n" . mysql_error());
	if ($result) {
		return TRUE;
	} else {
		return FALSE;
	}
}

function getAllIDs($customDefs) {
	$tableNames = array_keys($customDefs);
	$tableName = $tableNames[0];

	$key = findTableKey($customDefs);
	$query = "SELECT {$key} FROM ".SQL_TABLE_PREFIX.$tableName;

	$data = makeQueryHappen($customDefs, $query);
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

function getChildrenList($parentTableName, $parentID, $childTableName, $params=NULL) {
  $tableName = getMapName($parentTableName, $childTableName);
  $extendedWhere = "";
  if ($params != NULL) {
    foreach($params as $key => $value) {
      $extendedWhere .= " AND ".SQL_TABLE_PREFIX.$childTableName.".".$key."='".mysql_real_escape_string($value)."'";
    }
  }
  $query  = "SELECT ".SQL_TABLE_PREFIX.$tableName.".childID FROM ".SQL_TABLE_PREFIX.$tableName.", ".SQL_TABLE_PREFIX.$childTableName.
            " WHERE ".SQL_TABLE_PREFIX.$tableName.".parentID='".$parentID."' AND".
            " ".SQL_TABLE_PREFIX.$childTableName.".".findActualTableKey($childTableName)."=".SQL_TABLE_PREFIX.$tableName.".childID ".$extendedWhere." ".
            "ORDER BY ".SQL_TABLE_PREFIX.$tableName.".ordering";
  $data = makeQueryHappen(array($tableName => null), $query);
  if ($data) {
    $returnVal = array();
    foreach ($data as $row) {
      $returnVal[] = $row['childID'];
    }
    return $returnVal;
  } else {
    return NULL;
  }
}

function reorderChildren ($parentTable, $parentID, $childTable, $childOrdering) {
  $mapName = getMapName($parentTable, $childTable);
	$mapDef = array('parentID' => array("bigint(20) unsigned", "NO", "",    "",  ""),
									'childID'  => array("bigint(20) unsigned", "NO", "",    "",  ""),
																			'ordering' => array("int(11) unsigned",    "NO", "",    "",  "")
								 );
	foreach ($childOrdering as $child => $order) {
		sqlMagicSet(array($mapName => $mapDef), array('ordering' => $order), array('parentID' => $parentID, 'childID' => $child));
  }
  // That should do it
}

function doAdoption($parentTable, $parentID, $childTable, $childID) {
	$mapName = getMapName($parentTable, $childTable);
	$mapDef = array('parentID' => array("bigint(20) unsigned", "NO", "",    "",  ""),
	                'childID'  => array("bigint(20) unsigned", "NO", "",    "",  ""),
	                'ordering' => array("int(11) unsigned",    "NO", "",    "",  "")
	               );
	// Prevent adopting the same object twice
	$family = getChildrenList($parentTable, $parentID, $childTable);
	if ( (!is_array($family)) || ( array_search($childID, $family) === FALSE )) {
		// Welcome to the family.
		return sqlMagicPut(array($mapName => $mapDef), array('parentID' => $parentID, 'childID' => $childID));
	} else {
		// Already in the family.
		return TRUE;
	}
}

function doEmancipation($parentTable, $parentID, $childTable, $childID) {
	$mapName = getMapName($parentTable, $childTable);
	$mapDef = array('parentID' => array("bigint(20) unsigned", "NO", "",    "",  ""),
	                'childID'  => array("bigint(20) unsigned", "NO", "",    "",  ""),
	                'ordering' => array("int(11) unsigned",    "NO", "",    "",  "")
	               );
	return sqlMagicYank(array($mapName => $mapDef), array('parentID' => $parentID, 'childID' => $childID));
}

?>