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

require_once dirname(__FILE__).'/class_DatabaseMagicPreparation.php';
require_once dirname(__FILE__).'/class_DatabaseMagicLink.php';

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

	protected $table_def_extensions = null;

	/// Calls initialize() and calls load($id) if $id != null
	/// Also marks the object for saving in the event of an unloadable $id
  function __construct($id = NULL) {
		parent::__construct();
		$this->extendTableDefs();
    $this->initialize();
    if ($id != NULL) {
      $loadResult = $this->load($id);
      if (!$loadResult) {
        // The load failed. . . a never-before-seen primary ID is being explicitly set by the constructor.
        // Mark it dirty so we are sure that it saves.
        dbm_debug("failedload", "load failed");
        $this->setAttribs(array($this->findTableKey() => $id), true);
      }
    }
  }

	/// Sets all the attributes to blank and the table key to null.
	/// used for initializing new blank objects.
	function initialize() {
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
		$key = $this->findTableKey();
		$query = array($key => $id);
		$info = $this->sqlMagicGet($query);
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

		$key = $this->findTableKey();
		if ($returnMe[$key] == NULL) {
			// Unsaved Object, don't return the key attribute with the results
			unset($returnMe[$key]);
		}

    return $returnMe;
  }

  /// Sets attribute (row) data for this object.
  /// $clobberID is a bool that must be true to allow you to overwrite a primary key
  function setAttribs($info, $clobberID = false) {
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

	function __get($name) {
		$a = $this->getAttribs();
		return (isset($a[$name])) ? $a[$name] : null;
	}

	function __set($name, $value) {
		$this->setAttribs(array($name => $value));
	}


	protected function getLinkedObjects($example, $params=null, $relation=null, $backLinks=false) {
		$example = (is_object($example)) ? get_class($example) : $example;
		$prototype = new $example;

		$id = $this->getPrimary();

		if (!$backLinks) {
			$linkObject = new DatabaseMagicLink($this, $prototype);
			$data = $linkObject->getLinksFromID($id, $params, $relation);
		} else {
			$linkObject = new DatabaseMagicLink($prototype, $this);
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
	function getPrimaryKey() {
    return $this->findTableKey();
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

	/// Recursively merges in any table definitions from extended classes
	function extendTableDefs() {
		if (get_class($this)==__CLASS__) {
			// We are a DatabaseMagicFeatures
			return true;
		} else if (is_null($this->table_def_extensions)) {
			$parentClass = get_parent_class($this);
			$parent = new $parentClass;
			$parentTableDefs = $parent->getTableDefs();
			$this->setTableDefs($parentTableDefs);
			return true;
		} else {
			// We are something that extends DatabaseMagicFeatures, and don't know the actual table defs
			$parentClass = get_parent_class($this);
			$parent = new $parentClass;
			$parentTableDefs = $parent->getTableDefs();
			$parentTableName = first_key($parentTableDefs);
			$parentDefs      = first_val($parentTableDefs);
			$parentPrimary   = $this->findKey($parentDefs);
			$myTableDefs     = $this->table_def_extensions;
			$myTableName     = first_key($myTableDefs);
			$myDefs          = first_val($myTableDefs);
			$myPrimary       = $this->findKey($myDefs);

			// Purify iffy data
			$parentDefs = (is_array($parentDefs)) ? $parentDefs : array();
			$myDefs     = (is_array($myDefs))    ? $myDefs     : array();

			// If we have two primary keys, drop the parent key
			if ($myPrimary && $parentPrimary) {
				unset($parentDefs[$parentPrimary]);
			}

			$mergedDefs = array_merge($parentDefs, $myDefs);

			$result = array($myTableName => $mergedDefs);
			$this->setTableDefs($result);
			return true;
		}
	}

  /// Returns the name of the table that this object saves and loads under.
  /// Pretty easy function really.
  function getMyTableName() {
		return $this->getTableName();
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

?>