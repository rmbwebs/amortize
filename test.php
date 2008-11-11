<?php

define('DBM_DEBUG', true);
define('DBM_DROP_TABLES', true);
define('SQL_TABLE_PREFIX', "dbmrw_test_");
include_once 'class_DatabaseMagicInterface.php';

class Collection extends DatabaseMagicInterface {
	protected $table_name = 'collections';
	protected $table_columns = array('name'   => "tinytext");
	protected $autoprimary = true;
}

class Book extends DatabaseMagicInterface {
	protected $table_name = 'myBooks';
	protected $table_columns = array(
		'isbn'    => "varchar(20)",
		'author'  => "tinytext",
		'title'   => "tinytext",
		'pubyear' => "year"
	);
	protected $autoprimary = true;
}

class Review extends DatabaseMagicInterface {
	protected $table_name = 'bookReviews';
	protected $table_columns = array(
		'reviewtext' => "text",
		'reviewer'   => "tinytext",
		'revdate'    => "datetime"
	);
	protected $autoprimary = true;
}

// Get starting time to compare later
$starttime = microtime(true);

/*
 This class is made simply to invoke the table updating feature of DbM.
 Since it uses the same table name  as Book, but a superset of Book's table_column array,
 it simulates the Book class having new columns added to it without the database being manually updated to match
 */
class NewBook extends DatabaseMagicInterface {
	protected $table_name = 'myBooks';
	protected $table_columns = array(
		'isbn'     => "varchar(20)",
		'author'   => "tinytext",
		'title'    => "tinytext",
		'pubyear'  => "year",
		'photoURL' => "varchar(256)"
	);
	protected $autoprimary = true;
}


dbm_debug("heading", "Dropping testing tables");
$classes = array(
	new Collection,
	new Book,
	new Review,
	new DataBaseMagicLink(new Collection, new Book),
	new DataBaseMagicLink(new Book, new Review)
);
foreach ($classes as $dbmclass) {
	$dbmclass->dropTable();
}


dbm_debug("heading", "Testing object creation, saving and loading. . .");

dbm_debug("info", 'Creating the Book "Monster Hunter International"');
$aBook = new Book();

dbm_debug("info", "Table Definitions for \$aBook:");
dbm_debug("data", $aBook->getTableDefs());

dbm_debug("info", "Giving \$aBook some attributes:");
$aBook->attribs(
	array(
		'isbn'   => "0-7414-4456-9",
		'author' => "Larry Correia",
		'title'  => "Monster Hunter International",
		'pubyear' => "2007"
	)
);

dbm_debug("info", "\$aBook now has these attributes:");
$aBook->dumpview(true);

dbm_debug("info", "Saving book . . . ");
$aBook->save();  // This will likely generate a lot of output
dbm_debug("info", "done saving book.  ");

dbm_debug("info", "Notice the a slight change after the save:");
$aBook->dumpview(true);

$id = $aBook->getPrimary();
dbm_debug("info", "Upon saving, the database gave this book a key value of {$id}.  We will load Book({$id}) for comparison to the original. . .");
$newBook = new Book($id);

dbm_debug("info", "Comparing the two, what we saved followed by what we loaded:");
$aBook->dumpview(true);
$newBook->dumpview(true);

if ($aBook->attribs() == $newBook->attribs()) {
	dbm_debug("info", "Loaded data matches saved data.");
} else {
	dbm_debug("error", "The object loaded from the database does not match what was saved to the database.");
}


dbm_debug("info", "Creating the Book \"The Art of the Rifle\"");
$aBook = new Book();
$aBook->attribs(
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
$aBook->attribs(
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
$pub->attribs(array('name' => "Rich's Favorite Books"));
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
$myReview->attribs(
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
	$info = $review->attribs();
 dbm_debug("info", "Book Review by ".$info['reviewer']."");
 dbm_debug("info", "\"".$info['reviewtext']."\"");
}


dbm_debug("info", "Loading a Review by itself, Review 1");
$review = new Review(1);
$review->dumpview(true);

dbm_debug("info", "Determining which review this book is for. . .");
$books = $review->getBackLinks("Book");
foreach ($books as $book) {
	$info = $book->attribs();
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
$books = $collection->getLinks("Book", "");
foreach ($books as $book) {
	$book->dumpview(true);
}

dbm_debug("info", "Attempting to find all linked books (should be all three books with no repeats)");
$books = $collection->getLinks("Book");
foreach ($books as $book) {
	$book->dumpview(true);
}

dbm_debug("heading", "Testing table modification");
dbm_debug("info", "Attempting to create and save a NewBook, this should invoke the table modification feature of DbM");
$poky = new NewBook();
$poky->attribs(
	array(
		'isbn'     => "978-0307021342",
		'author'   => "Janette Sebring Lowrey",
		'title'    => "The Poky Little Puppy",
		'pubyear'   => 1942,
		'photoURL' => "http://www.richbellamy.com/poky.jpg"
	)
);
$poky->save(); /* This should invoke the table modification */
dbm_debug("info", "Save Finished");

dbm_debug("info", "Attempting to load a NewBook from the database, and see if the new data comes out.");

$pokyID = $poky->getPrimary();  // Get the primary ID of the poky book.

$savedBook = new NewBook($pokyID);

if ($poky->photoURL == $savedBook->photoURL) {
	dbm_debug("info", "It looks like the data went into and came out of the database properly!");
	echo '<img src="'.$poky->photoURL.'" />'."\n\n";
	echo '<img src="'.$savedBook->photoURL.'" />'."\n\n";
} else {
	dbm_debug("error", "The new column didn't save to the table!");
}

// dbm_debug("info", "Script ran in ". (microtime(true) - $starttime) . " seconds");
?>