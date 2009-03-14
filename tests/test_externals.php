<?php

class Person extends AmortizeInterface {
	protected $table_name = 'people';
	protected $table_columns = array(
		'firstname' => 'varchar(20)',
		'lastname'  => 'varchar(20)'
	);
	protected $autoprimary = true;
}

class Restaurant extends AmortizeInterface {
	protected $table_name = 'restaurants';
	protected $table_columns = array(
		'name'   => 'varchar(20)',
		'rating' => 'tinyint'
	);
	protected $autoprimary = true;
	protected $externals = array(
		'owner'   => 'Person',
		'manager' => 'Person'
	);
}

dbm_debug('info', 'Creating and saving two example Persons and a Restaurant');
$p = new Person();
$p->firstname = "Joe";
$p->lastname  = "Smith";
$p->save();
$pid = $p->getPrimary();
dbm_debug('info', "When saving the first example Person, it received a primary key value of {$pid}");

$m = new Person();
$m->firstname = "Jane";
$m->lastname  = "Doe";
$m->save();
$mid = $m->getPrimary();
dbm_debug('info', "When saving the second example Person, it received a primary key value of {$mid}");



$r = new Restaurant();
$r->name = "Joe's Place";
$r->rating = 5;
$r->owner = $p;
$r->manager = $m;
$r->save();
dbm_debug('info', 'Done saving examples.');

$rid = $r->getPrimary();
dbm_debug('info', "When saving the example restaurant, it received a primary key value of {$rid}, which can be used later to load the Restaurant from the database.");

dbm_debug('info', "Like this:");
$joes = new Restaurant($rid);
dbm_debug('info', 'The restaurant object has been created, but there won\'t be any database traffic until you try to access its attributes:');

// This next line will trigger the load from the database
echo "{$joes->name} is a {$joes->rating}-star restaurant.";

dbm_debug('info', 'Similarly, the external table objects, in this case the owner and manager, have been created, but they won\'t load themselves from the database until you try to access their attributes.');

// This next line will trigger the owner object to load itself from the database
echo "{$joes->name} is owned by {$joes->owner->firstname} {$joes->owner->lastname}.";

// This next line will trigger the manager object to load itself from the database
echo "{$joes->name} is run by {$joes->manager->firstname} {$joes->manager->lastname}.";


dbm_debug('info', "Notice that the returned attribs include a Person object as the 'owner' and 'manager' attributes.  You get the actual objects, not just their primary keys");
dbm_debug('data', $joes->attribs());
dbm_debug('data', $joes->owner->attribs());
dbm_debug('data', $joes->manager->attribs());

dbm_debug('info', "The dumpview command runs on the AmortizeFeatures level, and is ignorant of externals.  It shows you how the external data will be saved to the database.");
$joes->dumpview(true);
$joes->owner->dumpview(true);
$joes->manager->dumpview(true);

?>