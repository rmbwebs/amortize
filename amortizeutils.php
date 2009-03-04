<?php

function first_val($arr = array()) {
	if (is_array($arr) && count($arr) > 0) {
		$vals = array_values($arr);
		return $vals[0];
	} else {
		return null;
	}
}

function first_key($arr = array()) {
	if (is_array($arr) && count($arr) > 0) {
		$keys = array_keys($arr);
		return $keys[0];
	} else {
		return null;
	}
}

function dbm_debug($class, $message) {
	if (DBM_DEBUG) {
		if (is_string($message)) {
			echo "<div class=\"$class\">";
				echo $message;
			echo "\n</div>\n";
		} else {
			echo "<pre class=\"$class\">\n";
				print_r($message);
			echo "\n</pre>\n";
		}
	}
}


if (DBM_DEBUG) { set_error_handler ("dbm_do_backtrace"); }

function dbm_do_backtrace ($one, $two) {
	echo "<pre>\nError {$one}, {$two}\n";
	debug_print_backtrace();
	echo "</pre>\n\n";
}


define('E_SQL_CANNOT_CONNECT', "
<h2>Cannot connect to SQL Server</h2>
There is an error in your Amortize configuration.
");

?>