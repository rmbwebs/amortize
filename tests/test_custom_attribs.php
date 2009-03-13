<?php

class Person extends Amortize {
	protected $table_name = "people";
	protected $autoprimary = true;
	protected $table_columns = array(
		'firstname' => 'varchar(30)',
		'lastname'  => 'varchar(30)'
	);

	// Custom attribs() function.  Don't forget to passthrough the two parameters from the original attribs($data, $force)
	public function attribs($data=null, $force=null) {
		// Call to Amortize's default attribs().
		$results = parent::attribs($data, $force);
		// Modify your results with useful metadata
		$results['fullname'] = "{$results['firstname']} {$results['lastname']}";
		$results['lastfirst'] = "{$results['lastname']}, {$results['firstname']}";
		// Return the modified results.
		return $results;
	}

}

dbm_debug('heading', "Direct call to attribs()");

$person = new Person();

dbm_debug('info', "Setting the first and last name of the person.");

$info = array('firstname' => 'John', 'lastname' => 'Doe');
dbm_debug('data', $info);

// Make the call to set the attributes from the info.
$person->attribs($info);

// Returns the attributes back into the $attribs variable
$attribs = $person->attribs();

// The above could have been done in one line: // $attribs = $person->attribs($info); // I separated it for illustrative purposes

dbm_debug('info', "First Name: "   . $attribs['firstname'] );
dbm_debug('info', "Last Name: "    . $attribs['lastname']  );
dbm_debug('info', "Full Name: "    . $attribs['fullname']  );
dbm_debug('info', "Reverse Name: " . $attribs['lastfirst'] );


dbm_debug('info', "Showing the data actually stored in the object:");
$person->dumpview(true);
dbm_debug('info', "Showing attributes.");
dbm_debug('data', $person->attribs());





dbm_debug('heading', "With overload operators");
dbm_debug('info', "The overload operators __set() and __get() work with attribs(), so they will automatically work with your custom attribs function:");

$person = new Person(); // $person is now blank, it doesn't have any info from the previous example

dbm_debug('info', "Setting the first and last name of the person.");

$person->firstname = "John";
$person->lastname  = "Doe";

dbm_debug('info', "First Name: "   . $person->firstname );
dbm_debug('info', "Last Name: "    . $person->lastname  );
dbm_debug('info', "Full Name: "    . $person->fullname  );
dbm_debug('info', "Reverse Name: " . $person->lastfirst );


dbm_debug('heading', "Showing the data actually stored in the object.");

$person->dumpview(true);

dbm_debug('heading', "Showing attributes.");

dbm_debug('data', $person->attribs());

?>