<?php

include_once 'rmbglobals.php';

include 'databasemagicclass.php';


define('TABLE_ORDER_PROD_MAP', SQL_TABLE_PREFIX . 'rmbcart_orderprodmap');
define('TABLE_ORDERS',         SQL_TABLE_PREFIX . 'rmbcart_orders');
define('TABLE_CUSTOMERS',      SQL_TABLE_PREFIX . 'rmbcart_customers');

$table_defs =
  array(TABLE_ORDER_PROD_MAP =>
          array('orderID'   => array("bigint(20)", "NO", "", "", ""),
                'productID' => array("bigint(20)", "NO", "", "", ""),
                'price'     => array("float",      "NO", "", "", ""),
                'quantity'  => array("int(11)",    "NO", "", "", "")),

        TABLE_ORDERS =>
          array('ID'            => array("bigint(20) unsigned", "NO",  "PRI", "",                    "auto_increment"),
                'customerID'    => array("bigint(20) unsigned", "YES", "",    "1",                   ""),
                'orderStatus'   => array("tinytext",            "YES", "",    "",                    ""),
                'transactionID' => array("tinytext",            "YES", "",    "",                    ""),
                'orderTotal'    => array("float",               "YES", "",    "",                    ""),
                'shippingCost'  => array("float",               "YES", "",    "",                    ""),
                'billaddress'   => array("tinytext",            "YES", "",    "",                    ""),
                'shipaddress'   => array("tinytext",            "YES", "",    "",                    ""),
                'orderTime'     => array("timestamp",           "YES", "",    "0000-00-00 00:00:00", ""),
                'taxCost'       => array("float",               "YES", "",    "",                    ""),
                'productCost'   => array("float",               "YES", "",    "",                    "")),

        TABLE_CUSTOMERS =>
          array('ID'            => array("bigint(20)",  "NO ", "PRI", "", "auto_increment"),
                'code'          => array("varchar(10)", "YES", "",    "", ""),
                'billlastName'  => array("tinytext",    "YES", "",    "", ""),
                'billfirstName' => array("tinytext",    "YES", "",    "", ""),
                'paypalID'      => array("tinytext",    "YES", "",    "", ""),
                'email'         => array("tinytext",    "YES", "",    "", ""),
                'siteUpdates'   => array("tinytext",    "YES", "",    "", ""),
                'newProducts'   => array("tinytext",    "YES", "",    "", ""),
                'billaddress1'  => array("tinytext",    "YES", "",    "", ""),
                'billaddress2'  => array("tinytext",    "YES", "",    "", ""),
                'billcity'      => array("tinytext",    "YES", "",    "", ""),
                'billstate'     => array("tinytext",    "YES", "",    "", ""),
                'billzip'       => array("tinytext",    "YES", "",    "", ""),
                'billcountry'   => array("tinytext",    "YES", "",    "", ""),
                'shiplastName'  => array("tinytext",    "YES", "",    "", ""),
                'shipfirstName' => array("tinytext",    "YES", "",    "", ""),
                'shipaddress1'  => array("tinytext",    "YES", "",    "", ""),
                'shipaddress2'  => array("tinytext",    "YES", "",    "", ""),
                'shipcity'      => array("tinytext",    "YES", "",    "", ""),
                'shipstate'     => array("tinytext",    "YES", "",    "", ""),
                'shipzip'       => array("tinytext",    "YES", "",    "", ""),
                'shipcountry'   => array("tinytext",    "YES", "",    "", ""))
       );

define('E_SQL_CANNOT_CONNECT', "
<h2>Cannot connect to SQL Server</h2>
There is an error in your databaseMagic configuration.
");


/************************************
 * getTableCreateQuery()
 * returns the query string that can be used to create a table based on it's definition
 * in TABLEDEFS, or a different definition array.
 ***********************************/
function getTableCreateQuery($tableName, $customDefs=NULL) {
  GLOBAL $table_defs;
  if ($customDefs == NULL) {
    $customDefs = $table_defs;
  }

  if (! isset($customDefs[$tableName])) {
    return NULL;
  }

  $table_def = $customDefs[$tableName];

  $rm      = "";
  $columns = "";
  $header  = "CREATE TABLE `".$tableName."` (\n  ";
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
  $query = "DESCRIBE " . $tableName;
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
  $type    = $details[0];
  $null    = $details[1];
  $key     = $details[2];
  $default = $details[3];
  $extra   = $details[4];

  if ($null == "NO") { $nullOut = "NOT NULL"; }
  else               { $nullOut = "";         }
  if ($default == "") { $defaultOut = "";                           }
  else                { $defaultOut = "DEFAULT '" . $default . "'"; }

  $return = "`".$field."` ".$type." ".$nullOut." ".$defaultOut." ".$extra;
  return $return;
}

/*******************************************
 * function getTableColumns(table name, optional table definition set) {
 * takes a table name and an optional table definition set (defaults to system table defs)
 * returns an array of table column names
 ******************************************/
function getTableColumns($tableName, $customDefs=NULL) {
  GLOBAL $table_defs;
  if ($customDefs == NULL) {
    $customDefs = $table_defs;
  }
  if (! isset($customDefs[$tableName])) {
    return FALSE;
  }
  $table_def = $customDefs[$tableName];

  $returnVal = array_keys($table_def);

  return $returnVal;
}


/*******************************************
 * function findTableKey(table name, optional table definition set) {
 * takes a table name and an optional table definition set (defaults to system table defs)
 * returns the primary key for that table
 ******************************************/
function findTableKey($tableName, $customDefs=NULL) {
  GLOBAL $table_defs;
  if ($customDefs == NULL) {
    $customDefs = $table_defs;
  }

  if (! isset($customDefs[$tableName])) {
    return FALSE;
  }

  $table_def = $customDefs[$tableName];

  return findKey($table_def);
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
function updateTable($tableName, $customDefs=NULL) {
  GLOBAL $table_defs;
  if ($customDefs == NULL) {
    $customDefs = $table_defs;
  }

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
      $query  = "ALTER TABLE ".$tableName."\n";
      $query .= "  DROP PRIMARY KEY";
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
    }
    if ($wantedKey) {
      $query  = "ALTER TABLE ".$tableName."\n";
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
      $query  = "ALTER TABLE ".$tableName."\n";
      $query .= "  ADD COLUMN " . $creationDef . " " . $location;
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
}
    // Find a column that needs modifying
    else if ($wanteddef[$name] != $actualdef[$name]) {
      $query  = "ALTER TABLE ".$tableName."\n";
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
      $query  = "ALTER TABLE ".$tableName."\n";
      $query .= "  DROP COLUMN " . $name;
      if (! mysql_query($query, $sqlConnection) ) return FALSE;
}
  }

  return TRUE;
}


function sqlMagicPut($tableName, $data) {
  $data = sqlFilter($data);
  $key = findTableKey($tableName);
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
  $query .= "INTO ".$tableName."\n  ".$columnList."\n  VALUES\n  ".$valueList;
  return makeQueryHappen($tableName, $query);
}

function sqlMagicGet($tableName, $params) {
  $whereClause = " ";
  $whereClauseLinker = "WHERE ";
  foreach ($params as $key => $value) {
    $whereClause .= $whereClauseLinker.$key."='".$value."'";
    // If there is more than one requirement, we need to link the params
    // together with a linker
    $whereClauseLinker = " AND ";
  }
  $query = "SELECT * FROM ".$tableName.$whereClause;
  $data = makeQueryHappen($tableName, $query);

  if ($data) {
    // We have a successful Query!
    // If the where clause contained a reference to the primary key, we are only expecting one result
    if (isset($params[findTableKey($tableName)])) {
      // primary key was set, caller expecting a row array
      return $data[0];
    } else {
      // primary key not set, return possible multiple-row results
      return $data;
    }
  } else {
    // we didn't get valid data.
    return $params;
  }
}

function makeQueryHappen($tableName, $query) {
  //echo "Running Query: \n<font color=red>\n". $query . "\n</font><br />\n";
  GLOBAL $table_defs;
  $sql = getSQLConnection();
  $result = mysql_query($query, $sql);
  if (! $result) {
    // We have a problem here
    if (! table_exists($tableName)) {
      createTable($tableName);
    } else {
      if ($table_defs[$tableName] != getActualTableDefs($tableName)) {
        updateTable($tableName);
      }
    }
    $result = mysql_query($query, $sql);
    if (! $result) {
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
    if ($row[0] == $tableName)
      return TRUE;
  }
  return FALSE;
}

function createTable($tableName) {
  $query = getTableCreateQuery($tableName);
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

function getMapName($table1, $table2) {
  if ($table1 == $table2) {
    return rtrim($table1, "_")."_map_to_self";
  }
  $i=0; while ((strncmp($table1, $table2, $i) == 0) && ($i <= strlen($table1))) { $i++; }
  $base = rtrim(substr($table1, 0, $i-1), "_");
  $ext1 =  trim(substr($table1, $i-1), "_");
  $ext2 =  trim(substr($table2, $i-1), "_");
  return $base."_map_".$ext1."_to_".$ext2;
}

function getChildrenList($parentTable, $parentID, $childTable, $params=NULL) {
  $tableName = getMapName($parentTable, $childTable);
  $query  = "SELECT ".$tableName.".childID FROM ".$tableName.", ".$childTable.
            " WHERE ".$tableName.".parentID='".$parentID."' AND".
            " ".$childTable.".".findTableKey($childTable)."=".$tableName.".childID";
  if ($params != NULL) {
    $extendedWhere = "";
    foreach($params as $key => $value) {
      $extendedWhere .= " AND ".$childTable.".".$key."='".mysql_real_escape_string($value)."'";
    }
    $query .= $extendedWhere;
  }
  $data = makeQueryHappen($tableName, $query);
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

function doAdoption($parentTable, $parentID, $childTable, $childID) {
  $mapName = getMapName($parentTable, $childTable);
  GLOBAL $table_defs;
  if (! isset($table_defs[$mapName])) {
    $mapDef = array('parentID' => array("bigint(20) unsigned", "NO", "",    "",  ""),
                    'childID'  => array("bigint(20) unsigned", "NO", "",    "",  "")
                  );
    // if ($customDef != NULL) { $mapDef += $customDef; } in case we ever pass custom defs
    $table_defs += array($mapName => $mapDef);
  }

  // Prevent adopting the same object twice
  $family = getChildrenList($parentTable, $parentID, $childTable);
  if ( (!is_array($family)) || ( array_search($childID, $family) === FALSE )) {
    // Welcome to the family.
    return sqlMagicPut($mapName, array('parentID' => $parentID, 'childID' => $childID));
  } else {
    // Already in the family.
    return TRUE;
  }
}


?>