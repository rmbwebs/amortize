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

require_once dirname(__FILE__).'/class_DatabaseMagicFeatures.php';

/**
 * This object makes it easy for a developer to create abstract objects which can save themselves
 * into and load themselves from an SQL database.
 * This class is meant to be used as a base class for custom objects.  When a group of classes extend this class,
 * each class represents a table in the database, with each instance of that class representing a row in the table.
 * Table name and column definitions are hard-coded into the class.
 * Descendents of this class can themselves be extended and pass their column definitions on to their descendants.
 * For Example:
 * @code
 * class Book extends DatabaseMagicInterface {
 *   protected $table_name = "books";
 *   protected $table_columns = array('id' => "serial", 'isbn' => "varchar(20)");
 * }
 * class Novel extends Book {
 *   protected $table_name = "novels";
 *   protected $table_columns = array('author' => "tinytext");
 * }
 * $nov = new Novel;
 * $nov->getTableDefs() will return this: array('novels' => array('id' => "serial", 'isbn' => "varchar(20)", 'author' => "tinytext"))
 * @endcode
 */
class DatabaseMagicInterface extends DatabaseMagicFeatures {

	/**
	 * Name of the table that this object saves and loads under.
	 * If a table name prefix is defined in the DBM config file, it will be prepended to this value to make the table name.
	 */
	protected $table_name = null;


	///Definitions for the columns in this object's table.
	protected $table_columns = null;

	/**
	 * If this is defined in your class, table definitions are not extended further;
	 * Use like this to declare your class as the baseclass as far as table column defs and table name are concerned:
	 * @code
	 * protected $baseclass = __CLASS__;
	 * @endcode
	 */
	 protected $baseclass = null;

	/// Set to true if you want an automatic primary key to be added to your class
	protected $autoprimary = null;

	/**
	 * Allows you to define attributes of your class which are actually themselves instances of DbM classes.
	 * The format is similar to the table_columns array: an associative array where the key is the name of the attribute
	 * and the value describes the type of data stored in that attribute.  In this case, the value is simply the name of the
	 * class that the attribute will be an instance of.
	 * @code
	 * class Person extends DatabaseMagicInterface {
	 *   protected $autoprimary=true;
	 *   protected $table_columns = array('firstname' => 'varchar(20)', 'lastname' => 'varchar(20)');
	 * }
	 * class Restaurant extends DatabaseMagicInterface {
	 *   protected $autoprimary=true;
	 *   protected $table_columns = array('name' => 'varchar(50)', address => 'tinytext');
	 *   protected $externals = array('owner' => "Person");
	 * }
	 * @endcode
	 * In the example above, any instance $res of Restaurant will have an instance of Person that can be accessed via
	 * $res->owner or under the 'owner' key of the array returned by $res->attribs().
	 *
	 * You can chain like this: @code echo "{$res->owner->firstname} {$res->owner->lastname} owns {$res->name}"; @endcode
	 *
	 * DbM objects which have externals defined will automatically save the primary key(s) of the external classes into
	 * their own table columns, and therefore are able to recall identical instances of their external objects across save/load
	 * cycles.
	 *
	 * External objects are not saved automatically when the holder is saved.  This is to prevent cascading saves which could be
	 * disasterous if a linked list has been implemented  using externals, especially a ringed list.
	 * Because of this, you need to save your externals manually: @code $res->owner->save(); @endcode will work fine.
	 */
	protected $externals = array();

	// Actual storage of the external objects.
	private $external_objects = array();

	// A list of columns used to store the external definitions.  Used for filtering in the attribs function.
	private $external_columns = array();

	/**
	 * Class Constructor
	 * Kicks off the table merging process for objects that extend other objects.
	 */
	public function __construct($data=null) {
		$this->mergeColumns();
		if ($this->autoprimary) {
			$this->table_columns['ID'] = array("bigint(20) unsigned", "NO",  "PRI", "", "auto_increment");
		}
		$this->table_defs = (is_null($this->table_columns)) ? $this->table_name : array($this->table_name => $this->table_columns);
		parent::__construct($data);

		/* Handle the externals.
		 * We need to call parent::__construct() twice to handle the special case where a class has itself listed as an external.
		 * In that case, __construct() and buildExternalColumns will enter an endless loop unless buildExternalColumns can
		 * use $this as the model instead of using new $class as the model (see "prevent endless loop" comment in the
		 * buildExternalColumns function), and $this->getPrimaryKey can't be called before parent::__construct().
		 * Thus, parent::__construct() needs to be called both before and after buildExternalColumns().
		 */
		if (count($this->externals) > 0) {
			$this->external_columns = $this->buildExternalColumns();
			$this->table_columns    = array_merge($this->table_columns, $this->external_columns);
			$this->table_defs = (is_null($this->table_columns)) ? $this->table_name : array($this->table_name => $this->table_columns);
			parent::__construct($data);
		}
	}

	/// Merges the column definitions for ancestral objects into your object.
	private function mergeColumns() {
		if (
			( get_class($this)        == __CLASS__        ) ||
			( get_parent_class($this) == __CLASS__        ) ||
			( $this->baseclass        == get_class($this) )
		) { return true; }
		else {
			$par = get_parent_class($this);
			$par = new $par;
			$parcols = $par->getFilteredTableColumnDefs();
			$this->table_columns    = array_merge($parcols, $this->table_columns);
			$parexts = $par->getExternals();
			$this->externals        = array_merge($parexts, $this->externals);
			return true;
		}
	}

	private function buildExternalColumns() {
		$returnArray = array();
		if (is_array($this->externals)) {
			foreach ($this->externals as $name => $class) {
				$obj = ($class == get_class($this)) ? $this : new $class;  // Prevent endless loop
				$keys = $obj->getPrimaryKey(); $keys = (is_array($keys)) ? $keys : array($keys);
				$defs = $obj->getTableColumnDefs();
				foreach ($keys as $key) {
					$def = $defs[$key][0];
					$returnArray["{$name}_{$key}"] = $def;
				}
			}
		}
		return $returnArray;
	}

	/// Returns the external class list
	public function getExternals() { return $this->externals; }

	/// Returns getTableColumnDefs with externals filtered out.
	public function getFilteredTableColumnDefs() {
		$defs = $this->getTableColumnDefs();
		foreach ($this->external_columns as $col => $def) {
			unset($defs[$col]);
		}
		return $defs;
	}

	/**
	 * Used to set or get the info for this object.
	 * Filters out bad info or unknown data that won't go into our database table.
	 * \param $info Optional array of data to set our attribs to
	 * \param $clobber Optional boolean: set to true if you need to overwrite the primary key(s) of this object (default: false)
	 */
	function attribs($info=null, $clobber=false) {
		if (!is_null($info)) {
			// Filter-out external columns (which should only be modded by modding the external obj itself
			foreach(array_keys($this->external_columns) as $key) {
				unset($info[$key]);
			}
			$this->setExternalObjects($info);
			$this->setAttribs($info, $clobber);
		}
			$returnVal = $this->getAttribs();
			foreach(array_keys($this->external_columns) as $key) {
				unset($returnVal[$key]);
			}
			$returnVal = array_merge($returnVal, $this->getExternalObjects());
			return $returnVal;
	}

	private function setExternalObjects($info=null) {
		foreach ($this->externals as $name => $class) {
			if (isset($info[$name]) && is_object($info[$name]) && get_class($info[$name])==$class) {
				$this->external_objects[$name] = $info[$name];
			} else if (isset($this->external_objects[$name]) && is_object($this->external_objects[$name]) && get_class($this->external_objects[$name])==$class) {
				// Everything is good.
			} else {
				$obj = new $class;
				$key = $obj->getPrimaryKey();
				$attribs = $this->getAttribs();
				$this->external_objects[$name] = new $class($attribs["{$name}_{$key}"]);
			}
		}
	}

	private function getExternalObjects() {
		$this->setExternalObjects();
		return $this->external_objects;
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

		$link = new DatabaseMagicLink($this, $subject);
		return $link->createLink($this->getPrimary(), $subject->getPrimary(),  $relation);

	}

	/** Breaks a link previously created by link()
	 * B will no longer be returned as part of A->getLinks() after A->deLink(B) is called.
	 * If $relation is provided, only matched relational links will be delinked
	 * Without $relation, all links between the two objects will be delinked.
	 * To break non-relational links and leave relational link intact, provide an empty string ("") as a relation here.
	 */
	function deLink($subject, $relation=NULL) {
		$link = new DatabaseMagicLink($this, $subject);
		return $link->breakLink($this->getPrimary(), $subject->getPrimary(),  $relation);
	}

	/** Breaks links to all previously linked $example.
	 * $example can be either a string of the classname, or an instance of the class itself
	 * If $relation is provided, only matched relations will be delinked
	 */
	function deLinkAll($example, $relation=NULL) {
		if (is_string($example)) {
			$subject = new $example;
		} else {
			$subject = $example;
		}
		$link = new DatabaseMagicLink($this, $subject);
		return $link->breakLink($this->getPrimary(), null,  $relation);
	}

	/**
	 * Retrieve a list of this object's previously linked objects of a specific type.
	 * Use this function to retrieve a list of objects previously linked  by this object
	 * using the link() method.
	 * $example can be the name of the class you want to retrieve, or an example object of the same type as those
	 * children you want to retrieve.
	 *
	 * Example:\n
	 * \code
	 * $fido = new Dog("Fido");
	 * $fam = new Family("Smith");
	 * $bob = new Person("Bob");
	 * $fam->link($bob);
	 * $fam->link($fido);
	 * $fam->getLinks("Dog");  // Returns an Array that contains Fido and any other Dogs linked in to the Smith Family
	 * $fam->getLinks("Person");  // Returns an Array that contains Bob and any other Persons linked in to the Smith Family
	 * \endcode
	 */
	function getLinks($example) {
		$parameters = null;
		$relation = null;
		foreach (array_slice(func_get_args(),1) as $arg) {
			if (is_array($arg))  { $parameters = $arg; }
			if (is_string($arg)) { $relation   = $arg; }
		}
		return $this->getLinkedObjects($example, $parameters, $relation, false);
	}
	/**
	 * Works in reverse to getLinks().
	 * A->link(B); \n
	 * C = B->getBackLinks("classname of A"); \n
	 * C is an array that contains A \n
	 */
	function getBackLinks($example) {
		$parameters = null;
		$relation = null;
		foreach (array_slice(func_get_args(),1) as $arg) {
			if (is_array($arg))  { $parameters = $arg; }
			if (is_string($arg)) { $relation   = $arg; }
		}
		return $this->getLinkedObjects($example, $parameters, $relation, true);
	}

	private function setExternalColumns(){
		$externalAttribs = array();
		foreach($this->externals as $name => $class) {
			if (
				isset($this->external_objects[$name])     &&
				is_object($this->external_objects[$name]) &&
				get_class($this->external_objects[$name]) == $class
			) {
				$obj = $this->external_objects[$name];
				$keys = $obj->getPrimary(true);
				$keys = (is_array($keys)) ? $keys : array($obj->getPrimaryKey() => $keys); // Convert to future format
				foreach($keys as $column => $keyval) {
					$externalAttribs["{$name}_{$column}"] = $keyval;
				}
			}
		}
		$this->setAttribs($externalAttribs);
	}

	/// A front-end for the save function at the Features level, handles externals.
	public function save($force = false) {
		$this->setExternalColumns();
		parent::save($force);
	}

	/// A front-end for the load function, handles externals
	public function load($info = null) {
		parent::load($info);
		$this->setExternalObjects();
	}

	public function __get($name) {
		$a = $this->attribs();
		return (isset($a[$name])) ? $a[$name] : null;
	}

	public function __set($name, $value) {
		$this->attribs(array($name => $value));
	}

}

?>