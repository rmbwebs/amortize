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
			$parcols = $par->getTableColumnDefs();
			$this->table_columns = array_merge($parcols, $this->table_columns);
			return true;
		}
	}

	/**
	 * Used to set or get the info for this object.
	 * Filters out bad info or unknown data that won't go into our database table.
	 * \param $info Optional array of data to set our attribs to
	 * \param $clobber Optional boolean: set to true if you need to overwrite the primary key(s) of this object (default: false)
	 */
	function attribs($info=null, $clobber=false) {
		if (!is_null($info)) {
			DatabaseMagicFeatures::setAttribs($info, $clobber);
		}
		return DatabaseMagicFeatures::getAttribs();
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


	public function __get($name) {
		$a = $this->getAttribs();
		return (isset($a[$name])) ? $a[$name] : null;
	}

	public function __set($name, $value) {
		$this->setAttribs(array($name => $value));
	}

}

?>