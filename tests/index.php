<?php
	header("Content-type: text/html");
	$testFile = (isset($_GET['test'])) ? "test_{$_GET['test']}.php" : null;
	$testFile = (file_exists($testFile)) ? $testFile : 'test_generic.php';
	$known_tests = array(
		'generic'            => "Object creation, saving, loading",
		'externals'          => "A demonstration on the external tables features.",
		'ring'               => "Using externals to build a DB-based singularly-linked list in a ring",
		'ring_walk'          => ". . . Walk that ring (keep refreshing the page)",
		'column_inheritance' => "An illustration of Amortize objects inheriting the column definitions of their ancestors",
		'custom_attribs'     => "Overwriting the attribs function to get custom attributes"
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

			/* Adjust heights */
			#test_index  { height: 15%; }
			#self_source,
			#file_output { height: 72%; }
			#results     { height: 10%; }

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

		</style>
	</head>
	<body>
		<div id="test_index">
			<h2>Available tests:</h2>
			<ul id="test_list">
			<?php
				foreach($known_tests as $testBase => $description) {
					echo <<<LI
				<li><a href="?test={$testBase}">{$description}</a></li>

LI;
				}
			?>
			</ul>
		</div>
		<div id="self_source">
			<h2>Source code of <?php echo $testFile; ?></h2>
			<?php show_source($testFile); ?>
		</div>
		<div id="file_output">
			<h2>Output of <?php echo $testFile; ?></h2>
			<?php
				// Get starting time to compare later
				$starttime = microtime(true);
				// Run the test file
				include($testFile);
				// Compute execution time
				$scriptTime = microtime(true) - $starttime;
			?>
		</div>
		<div id="results">
			<h3>Results</h3>
			<p>Script ran in <?php echo $scriptTime ?> seconds.</p>
			<p>No Errors</p>
		</div>
	</body>
</html>
