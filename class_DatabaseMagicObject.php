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


require_once dirname(__FILE__) . "/databasemagic.php";

/**
 * This object makes it easy for a developer to create abstract objects which can save themselves
 * into and load themselves from an SQL database.  Objects are defined by setting a large array which
 * describes the way the data is stored in the database
 */
class DatabaseMagicObject extends DatabaseMagicPreparation {

  /// An array that determines how the data for this object will be stored in the database
  /// Format is array(tablename => array(collumn1name => array(type, NULL, key, default, extras), column2name => array(...), etc.))
  protected $table_defs = NULL;

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
		if ((!is_array($this->table_defs)) && (is_string($this->table_defs))) {
			$tablename = $this->table_defs;
			$this->table_defs = array($tablename => $this->getActualTableDefs($tablename));
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

	/// Can be used to set the order that a call for links will return as.
	function orderLinks($example, $ordering) {
		$childTableDefs  = $example->getTableDefs();
		$parentTableDefs = $this->getTableDefs();
		$parentID    = $this->getID();

		$this->reorderChildren($parentTableDefs, $parentID, $childTableDefs, $ordering);
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

	protected $actual_table_defs = array();

	/// Returns the table definitions for this object
	/// Recursively merges in any table definitions from extended classes
	function getTableDefs() {
		if (get_class($this)==__CLASS__) {
			// We are a DatabaseMagicObject
			return $this->table_defs;
		} else if (count($this->actual_table_defs) > 0) {
			// We already calculated the actual table defs.  Use those.
			return $this->actual_table_defs;
		} else {
			// We are something that extends DatabaseMagicObject, and don't know the actual table defs
			$extensionClass = get_parent_class($this);
			$extension = new $extensionClass;
			$extensionTableDefs = $extension->getTableDefs();
				// Bail out if we don't get an array for the extended class table def
				if (!is_array($extensionTableDefs)) { return $this->table_defs; }
			$extensionTableName = $extension->getMyTableName();
			$extensionDefs      = $extensionTableDefs[$extensionTableName];
			$extensionPrimary   = $this->findKey($extensionDefs);
			$myTableDefs = $this->table_defs;
			$myTableName = $this->getMyTableName();
			$myDefs      = $myTableDefs[$myTableName];
			$myPrimary   = $this->findKey($myDefs);

			// Build the merged table
			$mergedDefs = array();
			// If we have two primary keys, drop the extension key
			if ($myPrimary && $extensionPrimary) {
				unset($extensionDefs[$extensionPrimary]);
			}
			// Do extensiondefs first so they are first in the list, and so myDefs can overwrite a collision
			foreach ($extensionDefs as $key => $value) {
					$mergedDefs[$key] = $value;
			}
			// Follow with myDefs
			foreach ($myDefs as $key => $value) {
				$mergedDefs[$key] = $value;
			}

			$returnMe = array($myTableName => $mergedDefs);
			// Cache the result
			$this->actual_table_defs = $returnMe;
			return $returnMe;
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

	/// An alias for the link() method.  \deprecated.
	function adopt($child) {
		return $this->link($child);
	}

	/// Alias for the deLink() method. \deprecated.
	function emancipate($child) {
		return $this->deLink($child);
	}

	/// Alias for the orderLinks() method.  \deprecated.
	function orderChildren($example, $ordering) {
		return $this->orderLinks($example, $ordering);
	}

	/// Alias for the getLinks() method.  \deprecated.
	function getChildren($example, $parameters = NULL) {
		return $this->getLinks($example, NULL, $parameters);
	}

}


/***************************************************************************************************************/



/**
 * This is an extension of DatabaseMagicObject that merely provides a default table Primary key
 */
class PrimaryDatabaseMagicObject extends DatabaseMagicObject {
	protected $table_defs = array("databasemagic" => array('ID'=> array("bigint(20) unsigned", "NO",  "PRI", "", "auto_increment") ) );
}


?>