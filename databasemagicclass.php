<?php

/**
 * This object makes it easy for a developer to create abstract objects which can save themselves
 * into and load themselves from an SQL database.  Objects are defined by setting a large array which
 * describes the way the data is stored in the database
 *
 *
 *
 */
class DatabaseMagicObject {

  /// An array that determines how the data for this object will be stored in the database
  /// Format is array(tablename => array(collumn1name => array(type, null, key, default, extras), column2name => array(...), etc.))
  protected $table_defs;

  /// Object status.
  /// Possible statuses are "needs saving", etc.
  protected $status;

  /// Object attributes are the data that is stored in the object and is saved to the database.
  /// Every instance of a DatabaseMagicObject has an array of attributes.  Each attribute corresponds
  /// to a column in the database table, and each Object corresponds to a row in the table.
  /// Through member functions, attributes can be read and set to and from an object.
  protected $attributes = array();

  /// Constructor.
  /// The constructor initializes the object by setting the name of the table (setting the object type)
  /// and possibly loading the object if an ID is passed to the constructor.
  function __construct($id = NULL, $table = "") {
    $this->tablename = $table;
    $this->initialize();
    if ($id != NULL) {
      $this->load($id);
    }
  }

  /// Initialize.
  /// Sets all the attributes to blank and the table key to 0.
  /// used for initializing new blank objects.
  function initialize() {
    $cols = getTableColumns($this->tablename);
    foreach ($cols as $col) { $this->attributes[$col] = ""; }
    $key = findTableKey($this->tablename);
    $this->attributes[$key] = 0;
    $this->status = "clean";
  }

  /// Returns ID
  /// Returns the ID of this object.  The ID is the column in this object's table which is the primary key.
  function getID() {
    $key = findTableKey($this->tablename);
    return $this->attributes[$key];
  }

  /// Loads an object from the database.
  /// This function loads the attributes for itself from the database, where the table primary key = $id
  /// This function has the ability to totally transform an object into a different instance of the same object.
  /// What I mean is, this function will set ALL attributes, including the ID.
  function load($id) {
    $key = findTableKey($this->tablename);
    $query = array($key => $id);
    $info = sqlMagicGet($this->tablename, $query);
    $this->setAttribs($info);
    $this->status = "clean";
  }

  /// Saves the object data to the database.
  /// This function records the attributes of the object into a row in the database.
  function save($force = FALSE) {
    if ( ($this->status != "clean") || ($force) ) {
      if ($id = sqlMagicPut($this->tablename, $this->attributes)) {
        // Successful Save
        $this->status = "clean";
        $this->attributes[findTableKey($this->tablename)] = $id;
        return TRUE;
      } else {
        die("Save Failed!\n".mysql_error());
        return FALSE;
      }
    }
  }

  /// Returns the array of attributes for the object.
  /// Pretty self-explainatory.
  function getAttribs() {
    return $this->attributes;
  }

  /// Set Attribs
  /// Sets attribute data for this object.
  function setAttribs($info) {
    $columns = getTableColumns($this->tablename);
    $returnVal = FALSE;
    foreach ($columns as $column) {
      if (isset($info[$column])) {
        $this->attributes[$column] = $info[$column];
        $returnVal = TRUE;
      }
    }
    if ($returnVal) {
      $this->status = "dirty";
    }
    return $returnVal;
  }

  /// Returns the name of the table that this object saves and loads under.
  /// Prety easy function really.
  function getTableName() {
    return $this->tablename;
  }

  /// "Adopts" another instance or extension of DatabaseMagicObject.
  /// "Adopts" basically means that a relational table is created between this object's table and the
  /// table of the object to be adopted, and an entry is placed in the relational table linking the two objects.
  /// From this point on, the adopted object can be retrieved as part of a list by using the method
  /// getChildren() on the adopting object.  Example:  A Category object "adopts" Product objects.
  function adopt($child) {
    $this->save(TRUE);
    $child->save(TRUE);

    $childTable  = $child->getTableName();
    $childID     = $child->getID();
    $parentTable = $this->tablename;
    $parentID    = $this->getID();

    return doAdoption($parentTable, $parentID, $childTable, $childID);
  }

  /// Free an adopted child from this object
  /// This function name is perfect in it's descriptiveness
  function emancipate($child) {
    $childTable  = $child->getTableName();
    $childID     = $child->getID();
    $parentTable = $this->tablename;
    $parentID    = $this->getID();

    return doEmancipation($parentTable, $parentID, $childTable, $childID);
  }

  /// Sets the children of this class into proper order
  function orderChildren($example, $ordering) {
    $childTable  = $example->getTableName();
    $parentTable = $this->getTableName();
    $parentID    = $this->getID();

    reorderChildren($parentTable, $parentID, $childTable, $ordering);
  }

  /// Retrieve a list of this object's "adopted" "children".
  /// Use this function to retrieve a list of objects previously "adopted" by this object using the adopt() method.
  /// $example is an instance of the object of the same type as those children you want to retrieve.
  /// Example:  $products = $mycategory->getChildren(new Product());
  function getChildren($example, $parameters = NULL) {
    $prototype = clone $example;
    $prototype->initialize();

    $parentTable = $this->getTableName();
    $parentID    = $this->getID();
    $childTable  = $prototype->getTableName();

    $list = getChildrenList($parentTable, $parentID, $childTable, $parameters);

    $children = array();
    if (is_array($list)) {
      foreach($list as $childid) {
        $temp = clone $prototype;
        $temp->__construct($childid);
        $children[] = $temp;
      }
    }
    return $children;
  }

}

?>