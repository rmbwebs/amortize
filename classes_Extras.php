<?php
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
				// No, there's aren't, freak out
				echo "There are no users!";
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
				if (!$temp->getAllPrimaries(1)) {
					echo "There are no users!";
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
	<span id="{$class}_{$pri}_{$welcome}" class="{$class}_{$welcome}">\n
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