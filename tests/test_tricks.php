<?php

define('DBM_DEBUG', true);
define('DBM_DROP_TABLES', true);
define('SQL_TABLE_PREFIX', "dbmrw_test_");
include_once '../class_DatabaseMagicInterface.php';

class Person extends DatabaseMagicInterface {
	protected $table_name = 'people';
	protected $table_columns = array(
		'firstname' => 'varchar(20)',
		'lastname'  => 'varchar(20)'
	);
	protected $autoprimary = true;
}

class Restaurant extends DatabaseMagicInterface {
	protected $table_name = 'people';
	protected $table_columns = array(
		'name'   => 'varchar(20)',
		'rating' => 'tinyint'
	);
	protected $autoprimary = true;
	protected $externals = array(
		'owner' => 'Person';
	);
}

$p = new Person();
$p->firstname = "Joe";
$p->lastname  = "Smith";
$p->save();

$r = new Restaurant();
$r->name = "Joe's Place";
$r->rating = 5;
$r->owner = $p;
$r->save();

$rid = $r->getPrimary();

$joes = new Restaurant($rid);
dbm_debug('info', "{$joes->name} is a {$joes->rating}-star restaurant owned by {$joes->owner->firstname} {$joes->owner->lastname}.");

?>