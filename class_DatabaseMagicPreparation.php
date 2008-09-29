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

require_once dirname(__FILE__) . '/class_DatabaseMagicExecution.php';

/**
 * Helper for DatabaseMagicFeatures
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
			$return = array();
			foreach($data as $row) {
				$return[] = $this->sqlDataDePrep($row, $customDefs[$tableName]);
			}
			return $return;
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
		if ($data) {
			// We have a successful Query!
			$return = array();
			foreach($data as $row) {
				$return[] = $this->sqlDataDePrep($row, $customDefs[$tableName]);
			}
			return $return;
		} else {
			return null;
		}
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

?>