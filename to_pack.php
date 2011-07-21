<?php

	// Test
	/* Test
	 * Block
	 */
	/*
	  Test Block 2
	 */
	/**
	 ** Test Block 3
	 **/
	
	$test="Testing..";
	$msg=function($msg) {
		echo "Message: $msg\n";
	};

	function test($msg,$msg2) { echo "Message: $msg\nMessage 2: {$msg2}\n"; }
	class test {
		public $test="Test";
		function __construct() {
			global $test;
			$this->test = "Testing";
			$test="Testing4";
			$this->test("Testing 5..");
		}
		static function test($msg) { echo "Message: $msg\n"; }
	}

	
	test("Testing1","Testing2");
	test::test("Testing3");
	echo "{$test}\n";
	$test=new test();
	$msg("Testing5");

	echo "\n"; // New line for dump

	var_dump($test,$msg);

?>
