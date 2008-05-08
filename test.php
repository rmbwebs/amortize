<?php

header("Content-type: text/html");

include_once 'databasemagic.php';

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


echo "Creating a fresh book<br />";
$aBook = new Book();
$aBook->dumpview(true);

$bookAttributes = array(
	'isbn'   => "0-7414-4456-9",
	'author' => "Larry Correia",
	'title'  => "Monster Hunter International",
	'pubyear' => "2007"
);
echo "Setting book attributes . . . ";
$aBook->setAttribs($bookAttributes);
echo "done.<br />";
$aBook->dumpview(true);
echo "Saving book . . . ";
$aBook->save();
echo "done.<br />";
$aBook->dumpview(true);


echo "<br /><br /><br />Creating a fresh book<br />";
$aBook = new Book();
$aBook->dumpview(true);

$bookAttributes = array(
	'isbn'   => "9781581603071",
	'author' => "Jeff Cooper",
	'title'  => "The Art of the Rifle",
	'pubyear' => "2002"
);
echo "Setting book attributes . . . ";
$aBook->setAttribs($bookAttributes);
echo "done.<br />";
$aBook->dumpview(true);
echo "Saving book . . . ";
$aBook->save();
echo "done.<br />";
$aBook->dumpview(true);



echo "<br /><br /><br />Loading book 2 . . . ";
$greatBook = new Book("2");  // Loads Cooper's book from the database
echo "done.<br />";
$greatBook->dumpview(true);
echo "Creating a fresh Review . . . ";
$myReview = new Review;    // Create a new review
echo "Done.<br />";
$myReview->dumpview(true);
echo "Setting attributes on the Review . . . ";
$myReview->setAttribs(
	array(
		'reviewtext' => "RIP, Colonel.  Thanks for the great book.",
		'reviewer'   => "A guy",
		'revdate'    => "2008-02-03"
	)
);
echo "done.<br />";
$myReview->dumpview(true);


echo "Linking the Review. . .<br />";

// Saves the review and uses the relational database to link
// this review with this book (creating the relational databases as needed)
$greatBook->link($myReview);
echo "done.<br />";
$greatBook->dumpview(true);
$myReview->dumpview(true);



$book = new Book(2);  // Load AotR
$bookReviews = $book->getLinks("Review");
foreach ( $bookReviews as $review ) {
	$info = $review->getAttribs();
	echo "Book Review by ".$info['reviewer']."<br />";
	echo "\"".$info['reviewtext']."\"<br /><br />";
}



?>