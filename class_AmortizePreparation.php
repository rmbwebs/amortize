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

define('SQL_DATE_FORMAT', 'Y-m-d');
define('SQL_TIME_FORMAT', 'H:i:s');
define('SQL_DATETIME_FORMAT', 'Y-m-d H:i:s');


require_once dirname(__FILE__) . '/class_AmortizeExecution.php';

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

?>