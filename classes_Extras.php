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


	include_once 'class_DatabaseMagicObject.php';

/**
 * This is an extension of DatabaseMagicObject that provides form processing through a DOMDocument
 *
 */
class DatabaseMagicObjectDomForms extends DatabaseMagicObject {

	protected $input_restrictions = NULL;

	/**
	 * Returns a DOMDocument Node that can be used to display the object
	 * $dom is a domdocument used to create the element
	 * $which is an array that tells which fields to display
	 * $hidden will hide fields
	 */
	function displayFull($dom, $which=null, $hidden=null) {
		$which     = ($which)  ? $which  : getTableColumns($this->getTableDefs());
		$hidden    = ($hidden) ? $hidden : array();
		$primary   = $this->getPrimary();
		$classname = get_class($this);

		$returnMe  = $dom->createElement('div');
		$returnMe->setAttribute('id',    "{$classname}_{$primary}_display_container");
		$returnMe->setAttribute('class', "{$classname}_display_container display_container");

		foreach ($which as $field) {
			if (!isset($hidden[$field])) {
				$returnMe->appendChild($this->displayField($dom, $field));
			}
		}

		return $returnMe;
	}

	/**
	 * Returns a DOMDocument node that can be used to display the details of a specific field.
	 * It is called by displayFull() for every field that is not on an exclude list
	 */
	function displayField($dom, $field) {
		$classname = get_class($this);
		$primary   = $this->getPrimary();
		$attribs   = $this->getAttribs();
		$value     = (isset($attribs[$field])) ? $attribs[$field] : NULL;

		$returnMe = $dom->createElement('div');
		$returnMe->setAttribute('id',    "{$classname}_{$field}_field_container");
		$returnMe->setAttribute('class', "{$classname}_field_container field_container");

		$labelDisplay = $dom->createElement('span', $field);
		$labelDisplay->setAttribute('id',    "{$classname}_{$primary}_{$field}_label");
		$labelDisplay->setAttribute('class', "{$field}_label field_label");

		$valueDisplay = $dom->createElement('span', $value);
		$valueDisplay->setAttribute('id',    "{$classname}_{$primary}_{$field}_value");
		$valueDisplay->setAttribute('class', "{$field}_value field_value");

		$returnMe->appendChild($labelDisplay);
		$returnMe->appendChild($valueDisplay);

		return $returnMe;
	}

	/**
	 * Creates an input form from the object columns
	 * $which is an array that tells what columns to show
	 * $action sets the action for the form
	 * $hidden is an array that can be used to hide columns in the form (true), omit the column from the form (false)
	 * or pass some hidden values into the form (non-boolean)
	 */
	function inputForm($dom, $which = NULL, $action = NULL, $hidden=NULL) {
		$classname    = get_class($this);
		$primary      = $this->getPrimary();
		$which = ($which) ? $which : getTableColumns($this->getTableDefs());
		$attribs = $this->getAttribs();

		$form = $dom->createElement('form');
			$form->setAttribute('id', "{$classname}{$primary}");
			$form->setAttribute('class', $classname);
			$form->setAttribute('method', "post");
		if ($action != null) {
			$form->setAttribute('action', $action);
		}

		foreach ($which as $field) {
			if (!isset($hidden[$field])) {
				$form->appendChild($this->domInputField($dom, $field));
			}
		}

		if (is_array($hidden)) {
			foreach ($hidden as $key => $value) {
				$attrib = $attribs[$key];
				$input = $dom->createElement('input');
					$input->setAttribute('type', "hidden");
					$input->setAttribute('name', $key);
				if ($value === true) {
					$input->setAttribute('value', $attrib);
				}
				if ($value !== true && $value !== false) {
					$input->setAttribute('value', $value);
				}
				if ($value === false) {
					$input = null;
				}
				if ($input != null) {
					$form->appendChild($input);
				}
			}
		}
		return $form;
	}

	/**
	 * returns a DOM node of a specific input for an object-altering form
	 * It is called by inputForm() for every field that is not on an exclude list.
	 */
	function inputField($dom, $field) {
		$classname = get_class($this);
		$primary   = $this->getPrimary();
		$attribs   = $this->getAttribs();
		$value     = (isset($attribs[$field])) ? $attribs[$field] : NULL;

		$container = $dom->createElement('div');
			$container->setAttribute('id', "{$classname}_{$field}_input_container");
			$container->setAttribute('class', "{$classname}_input_container");
		$label = $dom->createElement('span', $field);
			$label->setAttribute('id', "{$classname}_{$primary}_{$field}_label");
			$label->setAttribute('class', "{$classname}_{$field}_label");

		$restrictions = (isset($this->input_restrictions[$field])) ? $this->input_restrictions[$field] : "input";
		if (is_array($restrictions)) {
			// Dropdown box
			$input = $dom->createElement('select');
			if (count($restrictions) < 5) {
				$input->setAttribute('size', count($restrictions));
			}
			foreach ($restrictions as $option => $text) {
				$option = $dom->createElement('option', $text);
					$option->setAttribute('value', $option);
				if ($option == $value) {
					$option->setAttribute('selected', "true");
				}
				$input->appendChild($option);
			}
		} else if ($restrictions == "textarea") {
				//Textarea
		  $input = $dom->createElement('textarea', $value);
		} else {
		  $input = $dom->createElement('input');
				$input->setAttribute('value', $value);
		}

		$input->setAttribute('id',    "{$classname}_{$primary}_{$field}_input");
		$input->setAttribute('class', "{$classname}_{$field}_input");
		$input->setAttribute('name',  "{$field}");

		$container->appendChild($label);
		$container->appendChild($input);

		return $container;
	}

}


/**
 * This is an extension of DatabaseMagicObjectDomForms that merely provides a default table Primary key
 */
class PrimaryDatabaseMagicObjectDomForms extends DatabaseMagicObjectDomForms {
	protected $table_defs = array("databasemagic" => array('ID'=> array("bigint(20) unsigned", "NO",  "PRI", "", "auto_increment") ) );
}




/**
 * This is an extension of DatabaseMagicObject that provides form processing
 *
 */
class DatabaseMagicObjectForms extends DatabaseMagicObject {

	protected $input_restrictions = NULL;


	/**
	 * Generates fully-styleable markup for displaying this object
	 * $which is an array that tells which fields to display
	 * $hidden will hide fields
	 */
	function displayFull($which=null, $hidden=null) {
		$which     = ($which)  ? $which  : getTableColumns($this->getTableDefs());
		$hidden    = ($hidden) ? $hidden : array();
		$primary   = $this->getPrimary();
		$classname = get_class($this);

		echo
<<<startdisplay
<div id="{$classname}_{$primary}_display_container" class="{$classname}_display_container display_container">
startdisplay;
		foreach ($which as $field) {
			if (!isset($hidden[$field])) {
				$this->displayField($field);
			}
		}
		echo
<<<enddisplay
</div>
enddisplay;
	}


	/**
	 *
	 *
	 */
	function displayField($field) {
		$classname = get_class($this);
		$primary   = $this->getPrimary();
		$attribs   = $this->getAttribs();
		$value     = (isset($attribs[$field])) ? $attribs[$field] : NULL;

		echo
<<<display
	<div id="{$classname}_{$field}_field_container" class="{$classname}_field_container field_container">
		<span id="{$classname}_{$primary}_{$field}_label" class="{$field}_label field_label">{$field}</span>
		<span id="{$classname}_{$primary}_{$field}_value" class="{$field}_value field_value">{$value}</span>
	</div>\n
display;
	}




	/**
	 * Creates an input form from the object columns
	 * $which is an array that tells what columns to show
	 * $action sets the action for the form
	 * $hidden is an array that can be used to hide columns in the form (true), omit the column from the form (false)
	 * or pass some hidden values into the form (non-boolean)
	 */
	function inputForm($which = NULL, $action = NULL, $hidden=NULL) {
		$actionString = ($action) ? "action=\"$action\"" : "";
		$classname    = get_class($this);
		$primary      = $this->getPrimary();
		$which = ($which) ? $which : getTableColumns($this->getTableDefs());
		$attribs = $this->getAttribs();

		echo <<<FORMOPEN
<form id="{$classname}{$primary}" class="{$classname}" {$actionString} method="POST">\n
FORMOPEN;

		foreach ($which as $field) {
			if (!isset($hidden[$field])) {
				$this->inputField($field);
			}
		}
		if (is_array($hidden)) {
			foreach ($hidden as $key => $value) {
				if ($value === true) {
					$attrib = $attribs[$key];
					echo
<<<HIDDENVALUE
	<input type="hidden" name="{$key}" value="{$attrib}" />\n
HIDDENVALUE;
				} else if ($value !== false) {
					echo
<<<HIDDENVALUE
	<input type="hidden" name="{$key}" value="{$value}" />\n
HIDDENVALUE;
				} else if ($value === false) {
					//Do nothing, omit this field
				}
			}
		}

		echo <<<FORMCLOSE
	<input type="submit" name="submit" value="Go"/>
</form>

FORMCLOSE;

	}



	function inputField($field) {
		$classname    = get_class($this);
		$primary      = $this->getPrimary();
		$attribs      = $this->getAttribs();
		$value        = (isset($attribs[$field])) ? $attribs[$field] : NULL;
		$restrictions = (isset($this->input_restrictions[$field])) ? $this->input_restrictions[$field] : "input";
		$label        = (isset($this->friendly_label[$field])) ? $this->friendly_label[$field] : $field;

		// FIXME Need to eventually customize this per the data type
		echo <<<start
	<div id="{$classname}_{$field}_input_container" class="{$classname}_input_container">
		<span class="{$classname}_{$field}_label" id="{$classname}_{$primary}_{$field}_label">{$label}</span>\n
start;

		if (is_array($restrictions)) {
			// Dropdown box
			$size = (count($restrictions) <= 4) ? " size=\"".count($restrictions)."\"" : "";
			echo <<<open
		<select class="{$classname}_{$field}_input" id="{$classname}_{$primary}_{$field}_input" name="{$field}"{$size}>\n
open;
			foreach ($restrictions as $option => $text) {
				$selected = ($option == $value) ? " selected" : "";
				echo <<<option
			<option value="{$option}"{$selected}>{$text}</option>\n
option;
			}
			echo <<<close
		</select>\n
close;
		} else if ($restrictions == "textarea") {
			echo <<<textarea
		<textarea name="{$field}" class="{$classname}_{$field}_input" id="{$classname}_{$primary}_{$field}_input">{$value}</textarea>\n
textarea;
		} else {
		echo <<<input
		<input name="{$field}" class="{$classname}_{$field}_input" id="{$classname}_{$primary}_{$field}_input" value="{$value}">\n
input;
			}

echo <<<end
	</div>\n
end;
	}

}


/**
 * This is an extension of DbMOForms that merely provides a default table Primary key
 */
class PrimaryDatabaseMagicObjectForms extends DatabaseMagicObjectForms {
	protected $table_defs = array("databasemagic" => array('ID'=> array("bigint(20) unsigned", "NO",  "PRI", "", "auto_increment") ) );
}


	/**
 * The User class is a generic class that allows for logging in and out of
 * a custom app.
 */
class DbMExtras_User extends DatabaseMagicObjectForms {
	protected $table_defs = array(
		'Users' => array(
			'login'    => array("varchar(10)", "NO",  "PRI"),
			'password' => "tinytext",
			'type'     => "varchar(10)"
		)
	);

	protected $omegaMan = false;

	/// Most DMB Objects do not get their IDs set by the constructor, but by the load function.
	/// Users have to be an exception in the event that you build a user-driven website and can't login
	function __construct($id = NULL) {
		// Call the default constructor
		parent::__construct($id);
		// Check for proper loading
		if ($this->status[$this->getPrimaryKey()] == "dirty") {
			// The load didn't occur properly, check if there are any users in the DB
			if (!$this->getAllPrimaries(1)) {
				// I am the Omega Man!
				$this->omegaMan = true;
				$this->setAttribs(array('login' => $id, 'type' => "superadmin"));
			}
		}
	}

	function save($force=false) {
		$a = $this->getAttribs();
		if ($a['type'] != "admin" && $a['type'] != "superadmin") {
			$this->setAttribs(array('type' => "regular"));
		}
		parent::save($force);
	}

	function checkPass($pass) {
		$attribs = $this->getAttribs();

		if (strlen($this->getPrimary()) != 0) {
			// Not a blank user
			if ($pass == $attribs['password']) {
				// password matches what we have on record
				return true;
			} else {
				// Password didn't match, but lets check if that's because there are no users
				if ($this->omegaMan) {
					// Apparently, we discovered that there were no users in the DB, so now that we have a password, let's save this user
					$this->setattribs(array('password' => $pass));
					$this->save();
					// Indicate that this was the proper password.
					return true;
				}
			}
		} else {
			// Blank user primary
			return false;
		}
	}

	function welcomeMessage($which=null) {
		$pri = $this->getPrimary();
		$attribs=$this->getattribs();
		$sal = ($which != null) ? $attribs[$which] : $pri;
		$class = get_class($this);
		echo
<<<welcome
	<span id="{$class}_{$pri}_welcome" class="{$class}_welcome">Welcome, {$sal}.</span>\n
welcome;
	}

	function logoutForm($action=null) {
		$action = (!is_null($action)) ? " action=\"{$action}\"" : "";
		$class = get_class($this);
		echo
<<<logout
	<form{$action} method="POST" class="${class}_logout">
		<input type="submit" name="loginaction" value="logout" />
	</form>\n
logout;
	}

	function loginForm($action=null) {
		$this->inputForm(array("login", "password"), $action, $hidden=array('loginaction'=>"login"));
	}

}


?>