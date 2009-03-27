<?php

class Person extends AmortizeInterface {
	protected $table_name = 'people';
	protected $table_columns = array(
		'firstname' => 'varchar(20)',
		'lastname'  => 'varchar(20)'
	);
	protected $autoprimary = true;

	/*
	 * The row removal functions live on the Features level, and are marked protected.
	 * The Interface level has a public function delete() that calls removeMyRow(), but that is the only
	 * public row removal option.  If you want to use removeSomeRows() or removeAllRows() you'll have to
	 * create public functions for them in your own classes, like this:
	 */
	public function deleteByLastName($name) {
		return $this->removeSomeRows(array('lastname'=>$name));
	}

	public function deleteAll() {
		return $this->removeAllRows();
	}

}

function displayEveryone() {
	$person = new Person;
	$people = $person->getAllLikeMe();
	dbm_debug('info', 'Found ' . count($people) . ' entries.');
	foreach($people as $person) {
		dbm_debug("info", "Person {$person->ID}: {$person->firstname} {$person->lastname}");
	}
	return $people;
}

dbm_debug('Heading', 'Dropping Testing Table');
$p = new Person();
$p->dropTable();

dbm_debug('Heading', 'Creating entries');
dbm_debug('info', '(the first one will cause a table creation)');
$p = new Person();
$p->firstname = "Jim";
$p->lastname  = "Smith";
$p->save();

$p = new Person();
$p->firstname = "Joe";
$p->lastname  = "Smith";
$p->save();

$p = new Person();
$p->firstname = "John";
$p->lastname  = "Smith";
$p->save();

$p = new Person();
$p->firstname = "Jane";
$p->lastname  = "Doe";
$p->save();

$p = new Person();
$p->firstname = "Barney";
$p->lastname  = "Riff";
$p->save();

$p = new Person();
$p->firstname = "Jerry";
$p->lastname  = "Calabria";
$p->save();


dbm_debug('Heading', 'Done creating entries');
displayEveryone();


dbm_debug('Heading', 'Deleting the entry with ID=4');
$p = new Person(4);
$p->delete();
displayEveryone();


dbm_debug('Heading', 'Deleting the Smiths');
$p = new Person;
$p->deleteByLastName("Smith");
displayEveryone();

dbm_debug('Heading', 'Deleting all entries');
$p = new Person;
$p->deleteAll();
displayEveryone();



?>