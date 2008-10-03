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

/// Linking object to join two DBM Objects.
class DatabaseMagicLink extends DatabaseMagicPreparation {

	private $from    = null;
	private $to      = null;
	private $params  = null;

	function __construct($fromDef, $fromID, $toDef, $toID=null, $relation=null) {
		$parentClass = get_parent_class($this);
		$from = new $parentClass;
		$to   = new $parentClass;
		$from->setTableDefs($fromDef);
		$to->setTableDefs($toDef);
		$this->from = $from;
		$this->to   = $to;
		$this->params = array('parentID' => $fromID);
		if (!is_null($toID))     { $this->params['childID']  = $toID;  }
		if (!is_null($relation)) { $this->params['relation'] = $relation; }
		$this->setLinkDefs();
	}

	public function createLink() {
		return $this->sqlMagicPut($this->getTableDefs(), $this->params);
	}

	public function breakLink() {
		return $this->sqlMagicYank($this->getTableDefs(), $this->params);
	}

	// A hook for the table creation routine in DatabaseMagicExecution
	protected function createTable($foo=null) {
		// Take care of our functionality obligations
		parent::createTable($foo);
		// This function will be called once the first time that a link is created between two object types
		// Note to self:
		// If you want to create database table delete hooks to avoid hanging links references, this is where you can do it.
	}


	public function getLinksFromID($id, $params=null, $relation=null) {
		$otherTableFullName = $this->to->getFullTableName();
		$myFullName = $this->getFullTableName();
		$on = array($myFullName.'.childID' => $otherTableFullName.'.'.$this->to->findTableKey());
		$thisWhere = array('parentID' => $id);
		if (!is_null($relation)) { $thisWhere['relation'] = $relation; };
		return $this->getInnerJoin($otherTableFullName, $on, $thisWhere, $params);
	}

	private function setLinkDefs() {
		$mapDefs = $this->createMapDefs($this->from, $this->to);
		$mapName = $this->createMapName($this->from, $this->to);
		$this->setTableDefs(array($mapName => $mapDefs));
	}

	private function createMapName($parent, $child) {
		$parentTableName = $parent->getTableName();
		$childTableName  = $child->getTableName();
		return "map_{$parentTableName}_to_{$childTableName}";
	}

	private function createMapDefs($parent, $child) {
		$parentTableKeyDef = $parent->findTableKeyDef();
		$childTableKeyDef  = $child->findTableKeyDef();
		// We really only need the data type
		$parentTableKeyDef = array($parentTableKeyDef[0], "NO", "PRI");
		$childTableKeyDef = array($childTableKeyDef[0], "NO", "PRI");

		return array(
			'parentID' => $parentTableKeyDef,
			'childID'  => $childTableKeyDef,
			'relation' => array("varchar(20)",         "YES", "PRI"),
			'ordering' => array("int(11) unsigned",    "NO",  "",    "0",  "")
		);
	}

}
?>