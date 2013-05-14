<?php
	session_start();
	header("Content-type: text/html");
	$curTest  = (isset($_GET['test'])) ? $_GET['test'] : 'generic';
	$testFile = dirname(__FILE__)."/test_{$curTest}.php";
	$testFile = (file_exists($testFile)) ? $testFile : dirname(__FILE)."/test_generic.php";
	$known_tests = array(
		'generic'            => "Object creation, saving, loading",
		'deleting'           => "Deleting",
		'formatting'         => "Automatic Data Formatting",
		'externals'          => "A demonstration on the external tables features.",
		'ring'               => "Using externals to build a DB-based singularly-linked list in a ring",
		'ring_walk'          => ". . . Walk that ring (keep refreshing the page)",
		'column_inheritance' => "An illustration of Amortize objects inheriting the column definitions of their ancestors",
		'learntabledefs'     => "Amortize can learn how to use a table you haven't defined",
		'custom_attribs'     => "Overwriting the attribs function to get custom attributes",
		'advanced_links'     => "Test out some advanced link retrieval",
		'set_get_callbacks'  => "How to write callbacks that get called automatically for certain attributes."
	);
	define('DBM_DEBUG', true);
	define('DBM_DROP_TABLES', true);
	define('SQL_TABLE_PREFIX', "dbm_test_");
	include_once '../amortize.php';

?>
<html>
	<head>
		<title>Amortize Testing Script</title>
		<style>
			div { margin: 1em 0em 1em 0em; }
			.info     {font-size: 1.2em; color: green;}
			.query    {border: 2px solid; white-space: pre;}
			.regular  {border-color: blue; margin-left: 1em;}
			.system   {border-color: red;  margin-left: 2em;}
			.error    {font-weight: bold; color: red;}
			.heading  {font-size: 2em; color: orange;}
			.tabledef {font-size: 0.8em; border: 2px solid yellow; margin-left: 3em; width: 30em;}

			.data        {margin-left: 2em; border: 1px black solid; max-height: 20em; overflow: auto; }
			.data:before {content: "data"; display: block; border-bottom: inherit; text-align: center;}

			.setattribs { border-color: blue;  display: none;}
			.deep { display: none; }
			.setattribs:before {content: "setAttribs() data"; color: blue;}

			#test_index {
				margin:        0px;
				padding:       0px;
			}

			#test_index h2 {float: left; margin-right: 2em;}
			#test_list {margin-left: 15em;}

			/* Reset */
			#test_index,
			#self_source,
			#file_output,
			#results {
				margin:        0px;
				padding:       0px;
			}
			h2, ul { margin-top: 0px;}
			h3, p  { margin: 0px; }

			/* Layout */
			#file_output,
			#self_source {
				float:    left;
				width:    50%;
				overflow: auto;
			}

			/* Adjust widths */
			#self_source { width: 50%; }
			#file_output { width: 50%; }

			/* Adjust heights. Add up to 97% to allow 3% for spacing */
			#test_index  { height: 20%; }
			#self_source,
			#file_output { height: 57%; }
			#results     { height: 20%; }

			/* Vertical Spacing */
			#test_index {
				margin-bottom: 0.5%;
				border-bottom: solid 2px #ccc;
			}

			/* Use scrollbars */
			#test_index,
			#results {
				overflow: auto;
			}

			#results { clear: left; }
			#results p {width: 50%; text-align: right;}

		</style>
	</head>
	<body>
		<div id="test_index">
			<h2>Available tests:</h2>
			<ul id="test_list">
			<?php foreach($known_tests as $testBase => $description): ?>
				<li>
					<?php if ($curTest == $testBase): ?><span style="color:red; font-weight:bold;"> Currently Viewing --&gt; </span><?php endif; ?>
					<a href="?test=<?php echo $testBase ?>"><?php echo $description ?></a>
				</li>
			<?php endforeach ?>
			</ul>
		</div>
		<div id="self_source">
			<h2>Source code of <?php echo $testFile ?></h2>
			<?php show_source($testFile); ?>
		</div>
		<div id="file_output">
			<h2>Output of <?php echo $testFile; ?></h2>
			<?php
				// Start the output buffer
				ob_start();
				// Get starting time to compare later
				$starttime = microtime(true);
				// Run the test file
				include($testFile);
				// Compute execution time
				$scriptTime = microtime(true) - $starttime;
				// Flush the output buffer
				ob_flush();
				// Set Times

				//Database Time
				$dbTime = $_SERVER['amtz_query_time'];
				$log = &$_SESSION['dbTimes'][$_GET['test']];
				$log[]=$dbTime;
				while (count($log) > 20) {
					array_shift($log);
				}
				$dbSamples = count($log);
				$dbAverage = array_sum($log) / $dbSamples;

				// Execution Time
				$log = &$_SESSION['exTimes'][$_GET['test']];
				$exTime = $scriptTime - $dbTime;
				$log[]=$exTime;
				while (count($log) > 20) {
					array_shift($log);
				}
				$exSamples = count($log);
				$exAverage = array_sum($log) / $exSamples;
			?>
		</div>
		<div id="results">
			<h3>Results</h3>
			<p>Script ran in                  <?php echo round($scriptTime, 4) ?> seconds.</p>
			<p>Total time in the database was <?php echo round($dbTime,     4) ?> seconds.</p>
			<p>Out-of-database execution was  <?php echo round($exTime,     4) ?> seconds.</p>
			<p>Average database time (over <?php echo $dbSamples ?> samples) is <?php echo round($dbAverage,4) ?> seconds.</p>
			<p>Average execution time (over <?php echo $exSamples ?> samples) is <?php echo round($exAverage,4) ?> seconds.</p>
			<h4>Times for <?php echo count($_SERVER['amtz_queries']) ?> queries</h4>
			<table class="query_report">
				<tr><td>Time</td><td>Query</td></tr>
				<?php foreach ($_SERVER['amtz_queries'] as $queryReport) : ?>
				<tr>
					<td class="time"><?php echo round($queryReport['elapsedTime'],4) ?> seconds</td>
					<td class="query"><?php echo $queryReport['query'] ?></td>
				</tr>
				<?php endforeach ?>
			</table>
			<p>No Errors</p>
		</div>
	</body>
</html>
