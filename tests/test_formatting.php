<?php

class FormattingTest extends Amortize {
	protected $table_name = 'format_testing';
	protected $table_columns = array(
		'someDate'    => 'date',
		'pointInTime' => 'datetime',
		'aTime'       => 'time'
	);
	protected $autoprimary = true;
}

$test = new FormattingTest();

dbm_debug('info', 'Setting the info:');

$test->someDate    = "Last Tuesday";
$test->pointInTime = "Saturday, 5:00 PM";
$test->aTime       = "Noon";

dbm_debug('info', 'Doing the save: The SQL statement should have sql-compatible time and date strings.');

$test->save();

dbm_debug('heading', "End of test.");

// Code below deals with dropping the the testing table.
if (isset($_POST['drop']) && $_POST['drop'] == "Drop Testing Table") {
	$test->dropTable();
}

?>
<form method="POST">
<div class="info">You can drop the test table if you want to see the table recreated on the next run, or if you just want to clean the testing DB</div>
<input name="drop" type="submit" value="Drop Testing Table" />
</form>