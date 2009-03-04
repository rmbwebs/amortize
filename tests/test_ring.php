<?php

class RingNode extends AmortizeInterface {
	protected $table_name = "ringnodes";
	protected $table_columns = array('payload' => 'tinytext');
	protected $externals = array('next' => 'RingNode');
	protected $autoprimary = true;
}

class RingHandle extends AmortizeInterface {
	protected $table_name = "rings";
	protected $table_columns = array('name' => 'tinytext');
	protected $externals = array('currentnode' => 'RingNode');
	protected $autoprimary = true;
}

foreach(array('RingNode','RingHandle') as $class) {
	$obj = new $class;
	$obj->dropTable();
}


$lyrics = array(
	'I fell in to a burning ring of fire',
	'I went down down down, and the flames went higher',
	'And it burns burns burns, the ring of fire',
	'The ring of fire',
	'The ring of fire',
	'because. . . '
);

$firstNode = $node = new RingNode;
foreach ($lyrics as $line) {
	$node->payload = $line;
	$node->save();
	if (isset($previousNode)) {
		$previousNode->next = $node;
		$previousNode->save();
	}
	$previousNode = $node;
	$node = new RingNode;
}
$previousNode->next = $firstNode;
$previousNode->save();

$handle = new RingHandle();
$handle->name = "Burning Ring Of Fire";
$handle->currentnode = $firstNode;
$handle->save();

?>