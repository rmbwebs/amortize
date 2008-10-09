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

	protected function getTableColumnDefs() {
		return first_val($this->getTableDefs());
	}

	/**
	* returns an array of table column names
	*/
	protected function getTableColumns() {
		return array_keys($this->getTableColumnDefs());
	}

	protected function sqlMagicYank($params) {

		$whereClause = $this->buildWhereClause($params);
		$query = "DELETE FROM ".$this->sql_prfx.$this->getTableName()." ".$whereClause;
		$success = $this->makeQueryHappen($query);

		return ($success) ? true : false;
	}

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

	protected function sqlMagicGet($params) {

		$whereClause = $this->buildWhereClause($params);

		$query = "SELECT * FROM ".$this->sql_prfx.$this->getTableName()." ".$whereClause;
		$data = $this->makeQueryHappen($query);

		if ($data) {
			// We have a successful Query!
			$return = array();
			foreach($data as $row) {
				$return[] = $this->sqlDataDePrep($row);
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
		$query = "UPDATE ".$this->sql_prfx.$tableName.$setClause." ".$whereClause;
		$result = $this->makeQueryHappen($query);
		return $result;
	}

	private function buildConditionalClause($params=null, $q=true) {
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
		return implode(" AND ", $clause);
	}

	protected function buildWhereClause($params=null) {
		$clause = $this->buildConditionalClause($params);
		return (strlen($clause) > 0) ? "WHERE {$clause}" : "";
	}

	protected function buildOnClause($params=null) {
		$clause = $this->buildConditionalClause($params, false);
		return (strlen($clause) > 0) ? "ON {$clause}" : "";
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

		$data = $this->makeQueryHappen($query);
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
			// Convert to an array of arrays to an array of values
			$returnVal = array();
			foreach ($data as $row) {
				$returnVal[] = $row[$key];
			}
			return $returnVal;
		} else {
			return null;
		}
	}

	// A new method for running an inner join.
	protected function getInnerJoin($that, $on, $thisWhere=null, $thatWhere=null) {
		$thatTableFullName = $that->getFullTableName();
		$thisTableFullName = $this->getFullTableName();
		$on = (is_array($on)) ? $on : array();
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
				$returnVal[] = $row;   // This makes no sense.
			}
			return $returnVal;
		} else {
			return NULL;
		}
	}

	/// Preps data for insertion into the database.  As of right now it only converts true valued arrays into csv strings
	protected function sqlDataPrep($data) {
		foreach ($data as $colname => $value) {
			if (is_array($value)) { // We likely have a SET column here
				// Collect all the array keys whose value is true
				$value = array_keys($value, true);
				// Convert to text-based representation
				$value = implode(',', $value);
				// Replace old with new
				$data[$colname] = $value;
			}
		}
		return $data;
	}

	/**
	 * Translates data from database format to easily useable arrays of data.
	 * Currently it is used to check for SET data csv strings and convert it to true valued arrays
	 */
	protected function sqlDataDePrep($data) {
		// Walk through the column definitions searching for "SET" Columns
		foreach ($this->getTableColumnDefs() as $colname => $def) {
			$def = (is_array($def)) ? $def[0] : $def;
			if ((strtoupper(substr($def, 0, 3)) == "SET") && array_key_exists($colname, $data)) {
				$values = explode(',', $data[$colname]);
				$data[$colname] = $this->valuesFromSet($values, $def);
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
			return valuesFromSet(array(), $columnDef);
		} else {
			return null;
		}
	}

}

?>