<?php
	header("Content-type: text/html");
?>
<html>
	<head>
		<title>DatabaseMagic Testing Script</title>
		<style>
			#file_output div { margin: 1em 0em 1em 0em; }
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
