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


	include_once 'databasemagic.php';
	/**
 * The User class is a generic class that allows for logging in and out of
 * a custom app.
 */
class DbMExtras_User extends PrimaryDatabaseMagicObjectForms {
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
		if ($this->getPrimary() != $id) {
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