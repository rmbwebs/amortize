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

dbm_debug('heading', 'Dropping testing tables');
$objects = array(
	new FormattingTest,
);
foreach ($objects as $class) {
	$class->dropTable();
}
unset($class);
dbm_debug('heading', 'Done dropping testing tables');


$test = new FormattingTest();
$test->someDate = "Last Tuesday";
$test->pointInTime = "Saturday, 5:00 PM";
$test->aTime = "noon";
$test->save();

?>