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
 * into and load themselves from an SQL database.  Objects are defined by setting a large array which
 * describes the way the data is stored in the database
 */
class DatabaseMagicInterface extends DatabaseMagicFeatures {

	/**
	 * Used to set or get the info for this object.
	 * Filters bad info or unknown data that won't go into our database table.
	 */
	function attribs($info=null, $clobber=false) {
		if (!is_null($info)) {
			parent::setAttribs($info, $clobber);
		}
		return parent::getAttribs();
	}

	/// Can be used to set the order that a call for links will return as.
	function orderLinks($example, $ordering) {
		$childTableDefs  = $example->getTableDefs();
		$parentTableDefs = $this->getTableDefs();
		$parentID    = $this->getID();

		$this->reorderChildren($parentTableDefs, $parentID, $childTableDefs, $ordering);
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

		$link = new DatabaseMagicLink($parentTableDefs, $parentID, $subjectTableDefs, $subjectID, $relation);
		return $link->createLink();

	}

	/** Breaks a link previously created by link()
	 * B will no longer be returned as part of A->getLinks() after A->deLink(B) is called.
	 * If $relation is provided, only matched relational links will be delinked
	 * Without $relation, all links between the two objects will be delinked.
	 */
	function deLink($subject, $relation=NULL) {
		$subjectTableDefs  = $subject->getTableDefs();
		$subjectID     = $subject->getID();
		$parentTableDefs = $this->getTableDefs();
		$parentID    = $this->getID();

		$link = new DatabaseMagicLink($parentTableDefs, $parentID, $subjectTableDefs, $subjectID, $relation);
		return $link->breakLink();
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
		$subjectTableDefs  = $subject->getTableDefs();
		$subjectID     = $subject->getID();
		$parentTableDefs = $this->getTableDefs();
		$parentID    = $this->getID();

		$link = new DatabaseMagicLink($parentTableDefs, $parentID, $subjectTableDefs, NULL, $relation);
		return $link->breakLink();
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

}

?>