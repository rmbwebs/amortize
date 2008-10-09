<?php
	header("Content-type: text/html");
?>
<html>
	<head>
		<title>DatabaseMagic Testing Script</title>
		<style>
			pre.info     {font-size: 1.2em; color: green;}
			pre.query    {border: 2px solid;}
			pre.regular  {border-color: blue; margin-left: 1em;}
			pre.system   {border-color: red;  margin-left: 2em;}
			pre.error    {font-weight: bold; color: red;}
			pre.heading  {font-size: 2em; color: orange;}
			pre.tabledef {font-size: 0.8em; border: 2px solid yellow; margin-left: 3em; width: 30em;}

			pre.data        {margin-left: 2em; border: 1px black solid; max-height: 20em; overflow: auto; }
			pre.data:before {content: "data"; display: block; border-bottom: inherit; text-align: center;}

			pre.setattribs { border-color: blue; }
			pre.setattribs:before {content: "setAttribs() data"; color: blue;}


			#file_output,
			#self_source {
				float: left;
				width: 50%;
				overflow: scroll;
				height: 100%
			}
		</style>
	</head>
	<body>

		<div id="self_source">
			<h2>Source code of test.php</h2>
			<?php show_source("test.php"); ?>
		</div>
		<div id="file_output">
			<h2>Output of test.php</h2>
			<?php include("test.php"); ?>
		</div>
	</body>
</html>
