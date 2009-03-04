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

$handle = new RingHandle(1);

dbm_debug('heading', $handle->currentnode->payload);
$handle->currentnode = $handle->currentnode->next;
$handle->save();

?>