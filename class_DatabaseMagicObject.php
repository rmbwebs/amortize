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


require_once dirname(__FILE__).'/class_DatabaseMagicInterface.php';

/**
 * Provides a compatibility layer for projects that used DbM before the multi-class re-write:
 * Please use DatabaseMagicInterface for new projects.
 * \deprecated
 */
class DatabaseMagicObject extends DatabaseMagicInterface {
  protected $table_defs;
  protected $actual_table_defs;
	function __construct ($data=null) {
		$this->table_def_extensions = $this->table_defs;
		$this->setTableDefs($this->actual_table_defs);
		$this->actual_table_defs = null;
		return parent::__construct($data);
	}
	public function setAttribs($info, $clobber=false) {
		return $this->attribs($info, $clobber);
	}
	public function getAttribs() {
		return $this->attribs();
	}

  /// An alias for the getPrimary() method.  \deprecated
  function getID() {
		return $this->getPrimary();
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