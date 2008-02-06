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




/**
 * This is an extension of DatabaseMagicObject that merely provides a default table Primary key
 */
class PrimaryDatabaseMagicObject extends DatabaseMagicObject {
	protected $table_defs = array("databasemagic" => array('ID'=> array("bigint(20) unsigned", "NO",  "PRI", "", "auto_increment") ) );
}

/**
 * This object makes it easy for a developer to create abstract objects which can save themselves
 * into and load themselves from an SQL database.  Objects are defined by setting a large array which
 * describes the way the data is stored in the database
 */
class DatabaseMagicObject {

  /// An array that determines how the data for this object will be stored in the database
  /// Format is array(tablename => array(collumn1name => array(type, null, key, default, extras), column2name => array(...), etc.))
  protected $table_defs = null;

  /// Object status.
  /// Possible statuses are "needs saving", etc.
  protected $status = null;

  /// Object attributes are the data that is stored in the object and is saved to the database.
  /// Every instance of a DatabaseMagicObject has an array of attributes.  Each attribute corresponds
  /// to a column in the database table, and each Object corresponds to a row in the table.
  /// Through member functions, attributes can be read and set to and from an object.
  protected $attributes = array();

  /// Constructor.
  /// The constructor initializes the object by setting the name of the table (setting the object type)
  /// and possibly loading the object if an ID is passed to the constructor.
  function __construct($id = NULL, $table = "") {
    $this->initialize();
    if ($id != NULL) {
      $this->load($id);
    }
  }

	/// Initialize.
	/// Sets all the attributes to blank and the table key to 0.
	/// used for initializing new blank objects.
	function initialize() {
		if ((!is_array($this->table_defs)) && (is_string($this->table_defs))) {
			$tablename = $this->table_defs;
			$this->table_defs = array($tablename => getActualTableDefs($tablename));
		}
		$defs = $this->getTableDefs();
		if (is_array($defs)) {
			$cols = getTableColumns($this->getTableDefs());
			foreach ($cols as $col) { $this->attributes[$col] = ""; }
			$key = findTableKey($this->getTableDefs());
			$this->attributes[$key] = null;
		}
		$this->status = "clean";
	}

	/// A replacement for the (deprecated) getID() function
	function getPrimary() {
    $key = findTableKey($this->getTableDefs());
    return $this->attributes[$key];
	}

  /// Returns ID (deprecated)
  /// Returns the value of the Primary Key for this object.
  function getID() {
		return $this->getPrimary();
  }

  /// Loads an object from the database.
  /// This function loads the attributes for itself from the database, where the table primary key = $id
  /// This function has the ability to totally transform an object into a different instance of the same object.
  /// What I mean is, this function will set ALL attributes, including the ID.
  function load($id) {
    $key = findTableKey($this->getTableDefs());
    $query = array($key => $id);
    $info = sqlMagicGet($this->getTableDefs(), $query);
    if ($info) {
			$this->setAttribs($info[0]); // $info[0] because sqlMagicget always returns an array, even with one result.
			$this->status = "clean";
		}
  }

  /// Saves the object data to the database.
  /// This function records the attributes of the object into a row in the database.
  function save($force = FALSE) {
    if ( ($this->status != "clean") || ($force) ) {
      if ($id = sqlMagicPut($this->getTableDefs(), $this->attributes)) {
        // Successful Save
        $this->status = "clean";
        $this->attributes[findTableKey($this->getTableDefs())] = $id;
        return TRUE;
      } else {
        die("Save Failed!\n".mysql_error());
        return FALSE;
      }
    }
  }

  /// Returns the array of attributes for the object.
  /// Pretty self-explainatory.
  function getAttribs() {
		$returnMe = $this->attributes;

		$key = findTableKey($this->getTableDefs());
		if ($returnMe[$key] == null) {
			// Unsaved Object, don't return the key attribute with the results
			unset($returnMe[$key]);
		}

    return $returnMe;
  }

  /// Set Attribs
  /// Sets attribute data for this object.
  function setAttribs($info) {
    $columns = getTableColumns($this->getTableDefs());
    $returnVal = FALSE;
    foreach ($columns as $column) {
      if (isset($info[$column])) {
        $this->attributes[$column] = $info[$column];
        $returnVal = TRUE;
      }
    }
    if ($returnVal) {
      $this->status = "dirty";
    }
    return $returnVal;
  }

	/// Returns the table definitions for this object
	/// Recursively merges in any table definitions from extended classes
	function getTableDefs() {
		if (get_class($this)==__CLASS__) {
			// We are a DatabaseMagicObject
			return $this->table_defs;
		} else {
			// We are something that extends DatabaseMagicObject
			$extensionClass = get_parent_class($this);
			$extension = new $extensionClass;
			$extensionTableDefs = $extension->getTableDefs();
				// Bail out if we don't get an array for the extended class table def
				if (!is_array($extensionTableDefs)) { return $this->table_defs; }
			$extensionTableName = $extension->getMyTableName();
			$extensionDefs      = $extensionTableDefs[$extensionTableName];
			$extensionPrimary   = findKey($extensionDefs);
			$myTableDefs = $this->table_defs;
			$myTableName = $this->getMyTableName();
			$myDefs      = $myTableDefs[$myTableName];
			$myPrimary   = findKey($myDefs);

			// Build the merged table
			$mergedDefs = array();
			foreach ($myDefs as $key => $value) {
				$mergedDefs[$key] = $value;
			}
			foreach ($extensionDefs as $key => $value) {
				// Avoid more than one primary key in the merged table and don't overwrite defs
				if (($key!=$extensionPrimary || !$myPrimary) && !isset($mergedDefs[$key])) {
					$mergedDefs[$key] = $value;
				}
			}
			$returnMe = array($myTableName => $mergedDefs);
			return $returnMe;
		}
	}

  /// Returns the name of the table that this object saves and loads under.
  /// Prety easy function really.
  function getMyTableName() {
		return getTableName($this->table_defs);
  }

  /// "Adopts" another instance or extension of DatabaseMagicObject.
  /// "Adopts" basically means that a relational table is created between this object's table and the
  /// table of the object to be adopted, and an entry is placed in the relational table linking the two objects.
  /// From this point on, the adopted object can be retrieved as part of a list by using the method
  /// getChildren() on the adopting object.  Example:  A Category object "adopts" Product objects.
  function adopt($child) {
    $this->save(TRUE);
    $child->save(TRUE);

    $childTableDefs  = $child->getTableDefs();
    $childID         = $child->getID();
		$parentTableDefs = $this->getTableDefs();
    $parentID        = $this->getID();

    return doAdoption($parentTableDefs, $parentID, $childTableDefs, $childID);
  }

  /// Free an adopted child from this object
  /// This function name is perfect in it's descriptiveness
  function emancipate($child) {
    $childTableDefs  = $child->getTableDefs();
    $childID     = $child->getID();
		$parentTableDefs = $this->getTableDefs();
    $parentID    = $this->getID();

    return doEmancipation($parentTableDefs, $parentID, $childTableDefs, $childID);
  }

  /// Sets the children of this class into proper order
  function orderChildren($example, $ordering) {
    $childTableDefs  = $example->getTableDefs();
    $parentTableDefs = $this->getTableDefs();
    $parentID    = $this->getID();

    reorderChildren($parentTableDefs, $parentID, $childTableDefs, $ordering);
  }

  /// Retrieve a list of this object's "adopted" "children".
  /// Use this function to retrieve a list of objects previously "adopted" by this object using the adopt() method.
  /// $example can be the name of the class you want to retrieve, or an example object of the same type as those
  /// children you want to retrieve.
  /// Example:  $products = $mycategory->getChildren(new Product());
  /// Example:  $products = $mycategory->getChildren("Product");
  function getChildren($example, $parameters = NULL) {
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

    $list = getChildrenList($parentTableDefs, $parentID, $childTableDefs, $parameters);

    $children = array();
    if (is_array($list)) {
      foreach($list as $childid) {
        $temp = clone $prototype;
        $temp->__construct($childid);
        $children[] = $temp;
      }
    }
    return $children;
  }

	/// Retrieve an array of all the known IDs for all saved instances of this class
	function getAll() {
		$list = getAllIDs($this->getTableDefs());
		return $list;
	}

	/// Dumps the contents of attribs via print_r()
	/// Useful for debugging, but that's about it
	function dumpview() {
		print_r($this->attributes);
	}

}

?>