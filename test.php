<?php

header("Content-type: text/html");

include_once 'databasemagic.php';
?>
<html>
	<head>
		<title>DatabaseMagic Testing Script</title>
		<style>
			pre.info {font-variant: small-caps; color: green;}
			pre.query {border: 2px solid; margin-left: 1em;}
			pre.regular {border-color: blue;}
			pre.system {border-color: red; margin-left: 2em;}
			pre.error { font-weight: bold; color: red;}
		</style>
	</head>
	<body>
<?php
class Book extends PrimaryDatabaseMagicObject {
	protected $table_defs = array(
		'myBooks' => array(
			'isbn'    => "varchar(20)",
			'author'  => "tinytext",
			'title'   => "tinytext",
			'pubyear' => "year"
		)
	);
}

class Review extends PrimaryDatabaseMagicObject {
	protected $table_defs = array(
		'bookReviews' => array(
			'reviewtext' => "text",
			'reviewer'   => "tinytext",
			'revdate'    => "datetime"
		)
	);
}


dbm_debug("info", "Creating a fresh book");
$aBook = new Book();
$aBook->dumpview(true);

$bookAttributes = array(
	'isbn'   => "0-7414-4456-9",
	'author' => "Larry Correia",
	'title'  => "Monster Hunter International",
	'pubyear' => "2007"
);
dbm_debug("info", "Setting book attributes . . . ");
$aBook->setAttribs($bookAttributes);
dbm_debug("info", "done.");
$aBook->dumpview(true);
dbm_debug("info", "Saving book . . . ");
$aBook->save();
dbm_debug("info", "done.");
$aBook->dumpview(true);


dbm_debug("info", "Creating a fresh book");
$aBook = new Book();
$aBook->dumpview(true);

$bookAttributes = array(
	'isbn'   => "9781581603071",
	'author' => "Jeff Cooper",
	'title'  => "The Art of the Rifle",
	'pubyear' => "2002"
);
dbm_debug("info", "Setting book attributes . . . ");
$aBook->setAttribs($bookAttributes);
dbm_debug("info", "done.");
$aBook->dumpview(true);
dbm_debug("info", "Saving book . . . ");
$aBook->save();
dbm_debug("info", "done.");
$aBook->dumpview(true);



dbm_debug("info", "Loading book 2 . . . ");
$greatBook = new Book("2");  // Loads Cooper's book from the database
dbm_debug("info", "done.");
$greatBook->dumpview(true);
dbm_debug("info", "Creating a fresh Review . . . ");
$myReview = new Review;    // Create a new review
dbm_debug("info", "Done.");
$myReview->dumpview(true);
dbm_debug("info", "Setting attributes on the Review . . . ");
$myReview->setAttribs(
	array(
		'reviewtext' => "RIP, Colonel.  Thanks for the great book.",
		'reviewer'   => "A guy",
		'revdate'    => "2008-02-03"
	)
);
dbm_debug("info", "done.");
$myReview->dumpview(true);


dbm_debug("info", "Linking the Review. . .");

// Saves the review and uses the relational database to link
// this review with this book (creating the relational databases as needed)
$greatBook->link($myReview);
dbm_debug("info", "done.");
$greatBook->dumpview(true);
$myReview->dumpview(true);



$book = new Book(2);  // Load AotR
$bookReviews = $book->getLinks("Review");
foreach ( $bookReviews as $review ) {
	$info = $review->getAttribs();
 dbm_debug("info", "Book Review by ".$info['reviewer']."");
 dbm_debug("info", "\"".$info['reviewtext']."\"");
}



?>
	</body>
</html>