<?php

class Book extends Amortize {
	protected $table_name = 'myBooks';
	protected $table_columns = array(
		'isbn'    => "varchar(20)",
		'author'  => "tinytext",
		'title'   => "tinytext",
		'pubyear' => "year"
	);
	protected $autoprimary = true;
}

class GraphicNovel extends Book {
	protected $table_name = "comics";
	protected $table_columns = array(
		'artist'   => "tinytext",
		'coloring' => "tinytext"
	);
	protected $autoprimary = true;
}

$book = new Book();
$book->dumpview(true);

$comic = new GraphicNovel();
$comic->dumpview(true);

?>