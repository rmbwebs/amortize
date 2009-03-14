<?php
	header("Content-type: text/html");
	$selectedTest = (isset($_GET['test'])) ? $_GET['test'] : 'generic';
	$known_tests = array(
		'generic'            => "Object creation, saving, loading",
		'externals'          => "A demonstration on the external tables features.",
		'ring'               => "Using externals to build a DB-based singularly-linked list in a ring",
		'ring_walk'          => ". . . Walk that ring (keep refreshing the page)",
		'column_inheritance' => "An illustration of Amortize objects inheriting the column definitions of their ancestors",
		'custom_attribs'     => "Overwriting the attribs function to get custom attributes"
	);
	$testFile = (isset($known_tests[$selectedTest])) ? "test_{$selectedTest}.php" : 'test_generic.php';
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
				height:        18%;
				margin:        0px;
				padding:       0px;
				margin-bottom: 1%;
				border-bottom: solid 2px #ccc;
				overflow:      auto;
			}

			#test_index h2 { display:inline; margin-right:2em;}
			#test_index h3 {float: left; margin-right: 2em;}
			#test_list {margin-left: 15em;}

			h2, h3, ul { margin-top: 0px;}

			#file_output,
			#self_source {
				margin:   0px;
				float:    left;
				width:    50%;
				overflow: scroll;
				height:   80%
			}
			#file_output { width: 50%; }
			#self_source { width: 50%; }
		</style>
	</head>
	<body>
		<div id="test_index">
<!-- 			<h2>Current Test:</h2><span class="info"><?php echo $known_tests[$selectedTest] ?></span> -->
			<h3>Available tests:</h3>
			<ul id="test_list">
			<?php
				foreach($known_tests as $testBase => $description) {
					if ($testBase == $selectedTest) {
						echo <<<LI
				<li class="info">{$description}</li>

LI;
					} else {
						echo <<<LI
				<li><a href="?test={$testBase}">{$description}</a></li>

LI;
					}
				}
			?>
			</ul>
		</div>
		<div id="self_source">
			<h3>Source code of <?php echo $testFile; ?></h3>
			<?php show_source($testFile); ?>
		</div>
		<div id="file_output">
			<h3>Output of <?php echo $testFile; ?></h3>
			<?php include($testFile); ?>
		</div>
	</body>
</html>
