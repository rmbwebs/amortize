<?php


	class Blockable {
		static protected $constructor_block = array();
		protected function blockConstructor() {
			$this::$constructor_block[get_class($this)] = true;
		}
		protected function unBlockConstructor() {
			$this::$constructor_block[get_class($this)] = false;
		}
		protected function is_constructor_blocked() {
			return (
				isset($this::$constructor_block[get_class($this)]) &&
				      $this::$constructor_block[get_class($this)]
			);
		}
	}

	class Blocker extends Blockable{
	
		protected $sibling = null;
	
		public function __construct() {
			dbm_debug('info', "Trying to create a new ".get_class($this));
			
			if ($this->is_constructor_blocked()) {
				dbm_debug('info', "Found constructor block. Stopping");
			} else {
				$this->blockConstructor();
				$foo = new $this->sibling;
				$this->unBlockConstructor();
			} // End if
			
		} // End constructor
		
	} // End Class

	class Foo extends Blocker {
		protected $sibling = "Boo";
	}
	
	class Boo extends Blocker {
		protected $sibling = "Bar";
	}
	
	class Bar extends Blocker {
		protected $sibling = "Baz";
	}
	
	class Baz extends Blocker {
		protected $sibling = "Foo";
	}
	
	$foo = new Foo();

?>