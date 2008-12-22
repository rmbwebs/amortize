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
	/// Every instance of a DatabaseMagicFeatures has an array of attributes.  Each attribute corresponds
	/// to a column in the database table, and each instance of this class corresponds to a row in the table.
	/// Through member functions, attributes can be read and set to and from an object.
	protected $attributes = array();

 protected $needs_loading = false;

	protected $table_defs = null;

	/// Calls initialize() and calls load($id) if $id != null
	/// Also marks the object for saving in the event of an unloadable $id
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
	public function getPrimaryKey() {
    return $this->findTableKey();
	}

	/** Returns the value of this object's primary key.
	 * Primary key is the unique id for each object, used in the constructor and the load function
	 * for example:
	 *   $obj = new DatabaseMagicObject($key);
	 *   $key2 = $obj->getPrimary();
	 *   $key2 == $key
	 */
	public function getPrimary() {
    $key = $this->getPrimaryKey();
    return $this->attributes[$key];
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

?>