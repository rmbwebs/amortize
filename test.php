<?php

header("Content-type: text/html");

include_once 'databasemagic.php';

?>
<html>
	<head>
		<title>DatabaseMagic Testing Script</title>
		<style>
			pre.info    {font-size: 1.2em; color: green;}
			pre.query   {border: 2px solid;}
			pre.regular {border-color: blue; margin-left: 1em;}
			pre.system  {border-color: red;  margin-left: 2em;}
			pre.error   {font-weight: bold; color: red;}
			pre.heading {font-size: 2em; color: orange;}
		</style>
	</head>
	<body>
<?php

class Collection extends PrimaryDatabaseMagicObject {
	protected $table_defs = array(
		'collections' => array(
			'name'   => "tinytext"
		)
	);
}

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

dbm_debug("heading", "Testing object creation, saving and loading. . .");

dbm_debug("info", "Creating the Book \"Monster Hunter International\"");
$aBook = new Book();
$aBook->setAttribs(
	array(
		'isbn'   => "0-7414-4456-9",
		'author' => "Larry Correia",
		'title'  => "Monster Hunter International",
		'pubyear' => "2007"
	)
);
$aBook->dumpview(true);
dbm_debug("info", "Saving book . . . ");
$aBook->save();
dbm_debug("info", "done.  ");
$aBook->dumpview(true);
$id = $aBook->getPrimary();
dbm_debug("info", "The database gave this book a key value of {$id}.  Loading Book({$id}). . .");
$newBook = new Book($id);
dbm_debug("info", "Comparing the two:");
$aBook->dumpview(true);
$newBook->dumpview(true);


dbm_debug("info", "Creating the Book \"The Art of the Rifle\"");
$aBook = new Book();
$aBook->setAttribs(
	array(
		'isbn'   => "9781581603071",
		'author' => "Jeff Cooper",
		'title'  => "The Art of the Rifle",
		'pubyear' => "2002"
	)
);
$aBook->save();
$aBook->dumpview(true);


dbm_debug("info", "Creating the Book \"The Revolution: A Manifesto\"");
$aBook = new Book();
$aBook->setAttribs(
	array(
		'title'   => "The Revolution: A Manifesto",
		'isbn'    => "0-446-53751-9",
		'author'  => "Ron Paul",
		'pubyear' => "2008"
	)
);
$aBook->save();
$aBook->dumpview(true);

dbm_debug("info", "Creating the Collection \"Rich's Favorite Books\"");
$pub = new collection();
$pub->setAttribs(array('name' => "Rich's Favorite Books"));
$pub->save();
$pub->dumpview(true);


dbm_debug("heading", "Testing linking functions. . .");

dbm_debug("info", "Loading book 2 . . . ");
$greatBook = new Book(2);  // Loads Cooper's book from the database
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
$greatBook->link($myReview);
dbm_debug("info", "done.");

$greatBook->dumpview(true);

$myReview->dumpview(true);


dbm_debug("info", "Creating a new Book, telling it to load Book 2");

$book = new Book(2);  // Load AotR
dbm_debug("info", "Getting all reviews for Book 2. . .");
$bookReviews = $book->getLinks("Review");
dbm_debug("info", "Done");

foreach ( $bookReviews as $review ) {
	$info = $review->getAttribs();
 dbm_debug("info", "Book Review by ".$info['reviewer']."");
 dbm_debug("info", "\"".$info['reviewtext']."\"");
}


dbm_debug("info", "Loading a Review by itself, Review 1");
$review = new Review(1);
$review->dumpview(true);

dbm_debug("info", "Determining which review this book is for. . .");
$books = $review->getBackLinks("Book");
foreach ($books as $book) {
	$info = $book->getAttribs();
	dbm_debug("info", "written about {$info['title']} by {$info['author']}.");
}


dbm_debug("heading", "Beginning testing for relational linking.");

$collection = new Collection(1);
$mhi  = new Book(1);
$aotr = new Book(2);
$tram = new Book(3);

dbm_debug("info", "Loaded a collection and three books:");
$collection->dumpview(true);
$mhi->dumpview(true);
$aotr->dumpview(true);
$tram->dumpview(true);

dbm_debug("info", "Creating a relational link \"Sci-Fi\" from collection 1 to book 1");
$collection->link($mhi, "Sci-Fi");

dbm_debug("info", "Creating a relational link \"guns\" from collection 1 to book 1");
$collection->link($mhi, "guns");

dbm_debug("info", "Creating a relational link \"guns\" from collection 1 to book 2");
$collection->link($aotr, "guns");

dbm_debug("info", "Creating a blank link from collection 1 to book 3");
$collection->link($tram);


dbm_debug("info", "Attempting to find all books labeled \"Sci-Fi\" (should just be Monster Hunter International)");
$books = $collection->getLinks("Book", "Sci-Fi");
foreach ($books as $book) {
	$book->dumpview(true);
}

dbm_debug("info", "Attempting to find all books labeled \"guns\" (should just be Monster Hunter International and Art of the Rifle)");
$books = $collection->getLinks("Book", "guns");
foreach ($books as $book) {
	$book->dumpview(true);
}

dbm_debug("info", "Attempting to find all blank-linked books (should just be The Revolution)");
$books = $collection->getLinks("Book");
foreach ($books as $book) {
	$book->dumpview(true);
}

dbm_debug("info", "Attempting to find all linked books (should be all three books with no repeats)");
$books = $collection->getLinks("Book", true);
foreach ($books as $book) {
	$book->dumpview(true);
}



?>
	</body>
</html>