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

	private $from = null;
	private $to   = null;

	function __construct($from, $to) {
		$this->from = $from;
		$this->to   = $to;
		$this->setLinkDefs();
	}

	private function setLinkDefs() {
		$mapDefs = $this->getMapDefs($this->from, $this->to);
		$mapName = $this->getMapName($this->from, $this->to);
		$this->setTableDefs(array($mapName => $mapDefs));
	}

	private function getMapName($parent, $child) {
		$parentTableName = $parent->getTableName();
		$childTableName  = $child->getTableName();
		return "map_{$parentTableName}_to_{$childTableName}";
	}

	private function getMapDefs($parent, $child) {
		$parentTableKeyDef = $parent->findTableKey();
		$childTableKeyDef  = $child->findTableKey();

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