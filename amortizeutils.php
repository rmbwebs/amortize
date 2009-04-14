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
// 				var_dump($message);
// 				print_r($message);
				var_export($message);
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

$_SERVER['amtz_query_time'] = 0;
$_SERVER['amtz_queries']    = array();

function amtz_query($query, $connection=null) {
	$startTime   = microtime(true);
	$result      = mysql_query($query, $connection);
	$endTime     = microtime(true);
	$elapsedTime = $endTime - $startTime;
	$_SERVER['amtz_queries'][] = array(
		'startTime'   => $startTime,
		'endTime'     => $endTime,
		'elapsedTime' => $elapsedTime,
		'query'       => $query
	);
	$_SERVER['amtz_query_time'] += $elapsedTime;
	return $result;
}

define('E_SQL_CANNOT_CONNECT', "
<h2>Cannot connect to SQL Server</h2>
There is an error in your Amortize configuration.
");

?>