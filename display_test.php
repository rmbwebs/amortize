<?php
	header("Content-type: text/html");
	$testFile = (isset($_GET['test'])) ? "test_{$_GET['test']}.php" : null;
	$testFile = (file_exists($testFile)) ? $testFile : 'test_generic.php';
	$known_tests = array(
		'generic' => "Object creation, saving, loading"
	);
?>
<html>
	<head>
		<title>DatabaseMagic Testing Script</title>
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
			.setattribs:before {content: "setAttribs() data"; color: blue;}

			#test_index {
				height: 18%;
				margin: 0px;
				padding: 0px;
				margin-bottom: 1%;
				border-bottom: solid 2px #ccc;
			}

			#test_index h2 {float: left; margin-right: 2em;}

			h2, ul { margin-top: 0px;}

			#file_output,
			#self_source {
				margin:   0px;
				float:    left;
				width:    50%;
				overflow: scroll;
				height:   80%
			}
			#file_output { width: 55%; }
			#self_source { width: 45%; }
		</style>
	</head>
	<body>
		<div id="test_index">
			<h2>Available tests:</h2>
			<ul>
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
			<?php include($testFile); ?>
		</div>
	</body>
</html>
