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

require_once dirname(__FILE__).'/class_AmortizePreparation.php';

define('MAP_FROM_COL', "parentID");
define('MAP_TO_COL',   "childID");

/// Linking object to join two DBM Objects.
class AmortizeLink extends AmortizePreparation {

	private $from    = null;
	private $to      = null;

	function __construct($fromDef, $toDef) {
		parent::__construct();
		$parentClass = get_parent_class($this);
		$from = new $parentClass;
		$to   = new $parentClass;

		if (is_object($fromDef)) {
			$from->setTableDefs($fromDef->getTableDefs());
		} else {
			$from->setTableDefs($fromDef);
		}
		if (is_object($toDef)) {
			$to->setTableDefs($toDef->getTableDefs());
		} else {
			$to->setTableDefs($toDef);
		}

		$this->from = $from;
		$this->to   = $to;

		$this->setLinkDefs();
	}

	public function createLink($fromID, $toID, $relation=null) {
		$params = array(
			MAP_FROM_COL => $fromID,
			MAP_TO_COL   => $toID
		);
		if (!is_null($relation)) { $params['relation'] = $relation; }
		return $this->sqlMagicPut($params);
	}

	public function breakLink($fromID, $toID=null, $relation=null) {
		$params = array(MAP_FROM_COL => $fromID);
		if (!is_null($toID))     { $params[MAP_TO_COL]  = $toID; }
		if (!is_null($relation)) { $params['relation'] = $relation; }
		return $this->sqlMagicYank($params);
	}

	public function getLinksFromID($id, $params=null, $relation=null, $wherelike=null) {
		$joinOn = array(MAP_TO_COL => $this->to->findTableKey());
		$thisWhere = array(MAP_FROM_COL => $id);
		if (!is_null($relation)) { $thisWhere['relation'] = $relation; };
		return $this->getInnerJoin($this->to, $joinOn, $thisWhere, $params, $wherelike);
	}

	public function getBackLinksFromID($id, $params=null, $relation=null) {
		$joinOn = array(MAP_FROM_COL => $this->from->findTableKey());
		$thisWhere = array(MAP_TO_COL => $id);
		if (!is_null($relation)) { $thisWhere['relation'] = $relation; };
		return $this->getInnerJoin($this->from, $joinOn, $thisWhere, $params);
	}



	/*********************** Protected Support Functions below **************************/

	private $createTriggers = false;

	// A hook for the table creation routine in AmortizeExecution
	// This function will be called once the first time that a link is created between two object types
	protected function createTable($foo=null) {
		// First: take care of the obligations to this function call.
		parent::createTable($foo);
		// Next: attempt to make a delete trigger if our configuration allows it.
		if ($this->createTriggers) {
			$mapName = $this->getFullTableName();
			$fromTableName = $this->from->getFullTableName();
			$fromPrimary = $this->from->findTableKey();
			$toTableName = $this->to->getFullTableName();
			$toPrimary = $this->to->findTableKey();
			$sql = $this->getSQLConnection();

			$mapFromCol = MAP_FROM_COL;
			$mapToCol   = MAP_TO_COL;


			$fromQuery = <<<QUERY
				CREATE TRIGGER {$mapName}_FromDeleteTrigger
				  AFTER DELETE ON {$fromTableName}
				  FOR EACH ROW
				    DELETE FROM {$mapName} WHERE {$mapFromCol}=OLD.{$fromPrimary}
QUERY;

			$toQuery = <<<QUERY
				CREATE TRIGGER {$mapName}_ToDeleteTrigger
				  AFTER DELETE ON {$toTableName}
				  FOR EACH ROW
				    DELETE FROM {$mapName} WHERE {$mapToCol}=OLD.{$toPrimary}
QUERY;

			dbm_debug("system query", $fromQuery);
			mysql_query($fromQuery, $sql) OR die($fromQuery . "\n\n" . mysql_error());
			dbm_debug("system query", $toQuery);
			mysql_query($toQuery, $sql) OR die($toQuery . "\n\n" . mysql_error());
		}
	}

	/*********************** Private Support Functions below **************************/

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
			MAP_FROM_COL => $parentTableKeyDef,
			MAP_TO_COL   => $childTableKeyDef,
			'relation'   => array("varchar(20)",         "YES", "PRI"),
			'ordering'   => array("int(11) unsigned",    "NO",  "",    "0",  "")
		);
	}

}
?>