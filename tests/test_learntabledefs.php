<?php

class Person extends AmortizeInterface {
	protected $table_name = 'people';
	protected $table_columns = array(
		'firstname' => 'varchar(20)',
		'lastname'  => 'varchar(20)'
	);
	protected $autoprimary = true;

	// Full name generator
	public function attribs($info=null, $force=null) {
		$info = parent::attribs($info, $force);
		$info['fullname'] = "{$info['firstname']} {$info['lastname']}";
		return $info;
	}
}

class Restaurant extends AmortizeInterface {
	protected $autoprimary = true;
	protected $table_name = 'restaurants';
	protected $table_columns = array(
		'name'   => 'varchar(20)',
		'rating' => 'tinyint'
	);
	protected $externals = array(
		'owner'   => 'Person',
		'manager' => 'Person'
	);
}

class Dining extends AmortizeInterface {
	protected $table_name = 'restaurants';
}


$foo = new Dining;

dbm_debug('data', $foo->getTableDefs());

?>