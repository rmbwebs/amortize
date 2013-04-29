<?php
	/**
	 * Created by JetBrains PhpStorm.
	 * User: rich
	 * Date: 4/28/13
	 * Time: 9:06 PM
	 * To change this template use File | Settings | File Templates.
	 */

	class Collection extends AmortizeInterface {
		protected $table_name = 'collections';
		protected $table_columns = array('name' => "tinytext");
		protected $autoprimary = TRUE;
	}

	class Book extends AmortizeInterface {
		protected $table_name = 'myBooks';
		protected $table_columns = array(
			'isbn'    => "varchar(20)",
			'author'  => "tinytext",
			'title'   => "tinytext",
			'pubyear' => "year"
		);
		protected $autoprimary = TRUE;
	}

	$pub = new collection(1);
	dbm_debug('heading', "Loading \"{$pub->name}\" from the first example.");

	dbm_debug('info', "Default ordering");
	$books = $pub->getLinks("Book");
	foreach ($books as $book) {
		dbm_debug('data', "{$book->title} ({$book->pubyear})");
	}

	dbm_debug('info', "Order by year ascending");
	$books = $pub->getLinks("Book", "ORDER BY pubyear ASC");
	foreach ($books as $book) {
		dbm_debug('data', "{$book->title} ({$book->pubyear})");
	}

	dbm_debug('info', "Order by year descending");
	$books = $pub->getLinks("Book", "ORDER BY pubyear DESC");
	foreach ($books as $book) {
		dbm_debug('data', "{$book->title} ({$book->pubyear})");
	}

	dbm_debug('info', "Order by year ascending with limit (kind of a hack)");
	$books = $pub->getLinks("Book", "ORDER BY pubyear ASC LIMIT 1");
	foreach ($books as $book) {
		dbm_debug('data', "{$book->title} ({$book->pubyear})");
	}

	dbm_debug('info', "Order by year descending with limit (kind of a hack)");
	$books = $pub->getLinks("Book", "ORDER BY pubyear DESC LIMIT 1");
	foreach ($books as $book) {
		dbm_debug('data', "{$book->title} ({$book->pubyear})");
	}
