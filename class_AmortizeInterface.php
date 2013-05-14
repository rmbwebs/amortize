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

require_once dirname(__FILE__).'/class_AmortizeFeatures.php';

/**
 * This object makes it easy for a developer to create abstract objects which can save themselves
 * into and load themselves from an SQL database.
 * This class is meant to be used as a base class for custom objects.  When a group of classes extend this class,
 * each class represents a table in the database, with each instance of that class representing a row in the table.
 * Table name and column definitions are hard-coded into the class.
 * Descendents of this class can themselves be extended and pass their column definitions on to their descendants.
 * For Example:
 * @code
 * class Book extends AmortizeInterface {
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
class AmortizeInterface extends AmortizeFeatures {

	/**
	 * Name of the table that this object saves and loads under.
	 * If a table name prefix is defined in the DBM config file, it will be prepended to this value to make the table name.
	 */
	protected $table_name = NULL;


	///Definitions for the columns in this object's table.
	protected $table_columns = array();

	/**
	 * If this is defined in your class, table definitions are not extended further;
	 * Use like this to declare your class as the baseclass as far as table column defs and table name are concerned:
	 * @code
	 * protected $baseclass = __CLASS__;
	 * @endcode
	 */
	 protected $baseclass = NULL;

	/// Set to true if you want an automatic primary key to be added to your class
	protected $autoprimary = NULL;

	/**
	 * Allows you to define attributes of your class which are actually themselves instances of DbM classes.
	 * The format is similar to the table_columns array: an associative array where the key is the name of the attribute
	 * and the value describes the type of data stored in that attribute.  In this case, the value is simply the name of the
	 * class that the attribute will be an instance of.
	 * @code
	 * class Person extends AmortizeInterface {
	 *   protected $autoprimary=true;
	 *   protected $table_columns = array('firstname' => 'varchar(20)', 'lastname' => 'varchar(20)');
	 * }
	 * class Restaurant extends AmortizeInterface {
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



	static private $constructor_block = array();
	private function blockConstructor() {
		AmortizeInterface::$constructor_block[get_class($this)] = TRUE;
	}
	private function unBlockConstructor() {
		AmortizeInterface::$constructor_block[get_class($this)] = FALSE;
	}
	private function is_constructor_blocked() {
		return (
			isset(AmortizeInterface::$constructor_block[get_class($this)]) &&
			      AmortizeInterface::$constructor_block[get_class($this)]
		);
	}

	// Something to keep track of the original table_coluns of the top-level class.
	private $local_table_columns = array();

	/**
	 * Class Constructor
	 * Kicks off the table merging process for objects that extend other objects.
	 */
	public function __construct($data=NULL) {
		$this->local_table_columns = $this->table_columns;
		$this->mergeColumns();
		if ($this->autoprimary) {
			// Add the ID index to the front of the table_columns array
			$ID_array = array('ID' => array("bigint(20) unsigned", "NO",  "PRI", "", "auto_increment"));
			$this->table_columns = array_merge($ID_array, $this->table_columns);
		}
		$this->table_defs = (empty($this->table_columns)) ? $this->table_name : array($this->table_name => $this->table_columns);

		if (count($this->externals) > 0 && !$this->is_constructor_blocked()) {
			$this->blockConstructor();
			$this->external_columns = $this->buildExternalColumns();
			$this->table_columns    = array_merge($this->table_columns, $this->external_columns);
			$this->table_defs = (is_null($this->table_columns)) ? $this->table_name : array($this->table_name => $this->table_columns);
			$this->unBlockConstructor();
		}
		
		// Finish defining the table columns and such in lower levels of the library
		parent::__construct($data);
	}

	/// Merges the column definitions for ancestral objects into your object.
	private function mergeColumns() {
		if (
			( get_class($this)        == __CLASS__        ) ||
			( get_parent_class($this) == __CLASS__        ) ||
			( $this->baseclass        == get_class($this) )
		) { return TRUE; }
		else {
			$par = get_parent_class($this);
			$par = new $par;
			$parcols = $par->getFilteredTableColumnDefs();
			$parcols = (is_array($parcols)) ? $parcols : array();
			$this->table_columns    = array_merge($parcols, $this->table_columns);
			$parexts = $par->getExternals();
			$this->externals        = array_merge($parexts, $this->externals);
			return TRUE;
		}
	}

	private function buildExternalColumns() {
		$returnArray = array();
		if (is_array($this->externals)) {
			foreach ($this->externals as $name => $class) {
				$obj = new $class;
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

	/// Used by attribs() to prevent setter and getter callback loops
	private $set_callback_locks = array();
	/// Used by attribs() to prevent setter and getter callback loops
	private $get_callback_locks = array();

	/**
	 * Used to invoke any pre-set info callbacks.  If they are defined.
	 */
	private function run_preset_callbacks($info) {
		// call any defined pre-set callback functions
		foreach (array_keys($info) as $key) {
			// normalize the mutex flag
			if (!isset($this->set_callback_locks[$key])) {
				$this->set_callback_locks[$key] = FALSE;
			}
			// determine callback name
			$callback_name = "preset_callback_{$key}";
			// if unlocked and callback exists, run it.
			if (!$this->set_callback_locks[$key] && method_exists($this, $callback_name)) {
				$this->set_callback_locks[$key] = TRUE;
				try {
					$result     = $this->$callback_name($info[$key]);
					$info[$key] = $result;
				} catch (Exception $e) {
					unset($info[$key]);
				}
				$this->set_callback_locks[$key] = FALSE;
			}
		}
		return $info;
	}

	/**
	 * Used to invoke any pre-get info callbacks.  If they are defined.
	 */
	private function run_preget_callbacks($info) {
		// call any defined pre-get callback functions
		foreach (array_keys($info) as $key) {
			// normalize the mutex flag
			if (!isset($this->get_callback_locks[$key])) {
				$this->get_callback_locks[$key] = FALSE;
			}
			// determine callback name
			$callback_name = "preget_callback_{$key}";
			// if unlocked and callback exists, run it.
			if (!$this->get_callback_locks[$key] && method_exists($this, $callback_name)) {
				$this->get_callback_locks[$key] = TRUE;
				try {
					$result     = $this->$callback_name($info[$key]);
					$info[$key] = $result;
				} catch (Exception $e) {
					// We don't unset the info here, because attribs would not return a value
				}
				$this->get_callback_locks[$key] = FALSE;
			}
		}
		return $info;
	}

	/**
	 * Used to set or get the info for this object.
	 * Filters out bad info or unknown data that won't go into our database table.
	 * \param $info Optional array of data to set our attribs to
	 * \param $clobber Optional boolean: set to true if you need to overwrite the primary key(s) of this object (default: false)
	 */
	public function attribs($info=NULL, $clobber=FALSE) {
		if (!is_null($info)) {
			// Filter-out external columns (which should only be modded by modding the external obj itself
			foreach(array_keys($this->external_columns) as $key) {
				unset($info[$key]);
			}

			// Run pre-set callbacks
			$info = $this->run_preset_callbacks($info);

			// set the new info
			$this->setExternalObjects($info);
			$this->setAttribs($info, $clobber);
		}

		// get the new info
		$newAttribs = $this->getAttribs();
		$newExterns = $this->getExternalObjects();

		// Filter-out external columns (which should be hidden from normal use
		foreach(array_keys($this->external_columns) as $key) {
			unset($newAttribs[$key]);
		}

		// Merge attribs and externals together
		$newInfo = array_merge($newAttribs, $newExterns);

		// run the pre-get callbacks
		$returnVal = $this->run_preget_callbacks($newInfo);

		return $returnVal;
	}
	
	/**
	 * Makes setting values from an API call to an external app a little bit safer.
	 * Disallows changing values for the table_columns defined by your class, while allowing inherited table_columns to be set.
	 * The idea of this function is that you have an external app which you routinely get data from and then pass that data into attribs(),
	 * having setup your $table_columns array to match the API data set.
	 * If you want to grow your local data set, but don't want to worry about the API eventually clobbering your local data if the spec changes,
	 * Then just extend your class witha new class that has your local data in its table_columns, and use this function inplace of attribs()
	 * when passing in data from the external API.
	 */
	public function apiSafeAttribs($info=NULL, $clobber=FALSE) {
		$keys = array_keys($this->local_table_columns);
		foreach($keys as $key) {
			unset($info[$key]);
		}
			return $this->attribs($info, $clobber);
	}

	private function setExternalObjects($info=NULL) {
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
	 * Creates a link to another instance or extension of AmortizeObject.
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

		$link = new AmortizeLink($this, $subject);
		return $link->createLink($this->getPrimary(), $subject->getPrimary(),  $relation);

	}

	/** Breaks a link previously created by link()
	 * B will no longer be returned as part of A->getLinks() after A->deLink(B) is called.
	 * If $relation is provided, only matched relational links will be delinked
	 * Without $relation, all links between the two objects will be delinked.
	 * To break non-relational links and leave relational link intact, provide an empty string ("") as a relation here.
	 */
	function deLink($subject, $relation=NULL) {
		$link = new AmortizeLink($this, $subject);
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
		$link = new AmortizeLink($this, $subject);
		return $link->breakLink($this->getPrimary(), NULL,  $relation);
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
		$parameters = NULL;
		$relation = NULL;
		$wherelike = NULL;
		$ordering = NULL;
		foreach (array_slice(func_get_args(),1) as $arg) {
			if (is_array($arg))  { $parameters = $arg; }
			if (is_string($arg)) {
				if     (strtolower(substr($arg,0,8)) == "order by") { $ordering  = $arg; }
				elseif (substr($arg,0,1) != '(')                    { $relation  = $arg; }
				else                                                { $wherelike = $arg; }
			}
		}
		return $this->getLinkedObjects($example, $parameters, $relation, FALSE, $wherelike, $ordering);
	}
	/**
	 * Works in reverse to getLinks().
	 * A->link(B); \n
	 * C = B->getBackLinks("classname of A"); \n
	 * C is an array that contains A \n
	 */
	function getBackLinks($example) {
		$parameters = NULL;
		$relation = NULL;
		foreach (array_slice(func_get_args(),1) as $arg) {
			if (is_array($arg))  { $parameters = $arg; }
			if (is_string($arg)) { $relation   = $arg; }
		}
		return $this->getLinkedObjects($example, $parameters, $relation, TRUE, $wherelike, $ordering);
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
				$keys = $obj->getPrimary(TRUE);
				$keys = (is_array($keys)) ? $keys : array($obj->getPrimaryKey() => $keys); // Convert to future format
				foreach($keys as $column => $keyval) {
					$externalAttribs["{$name}_{$column}"] = $keyval;
				}
			}
		}
		$this->setAttribs($externalAttribs);
	}

	/// A front-end for the save function at the Features level, handles externals.
	public function save($force = FALSE) {
		$this->setExternalColumns();
		parent::save($force);
	}

	/// A front-end for the load function, handles externals
	public function load($info = NULL) {
		parent::load($info);
		$this->setExternalObjects();
	}

	/// Removes the row for this object
	public function delete() {
		return parent::removeMyRow();
	}

	public function __get($name) {
		$a = $this->attribs();
		return (isset($a[$name])) ? $a[$name] : NULL;
	}

	public function __set($name, $value) {
		$this->attribs(array($name => $value));
	}

}

?>