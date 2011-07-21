<?php

	// Include the Obfuscator class code.
	include 'obfuscator.php';

	// Create a new Obfuscator class.
	$packer=new Obfuscator();

	// Load file with PHP code.
	$packer->file('to_pack.php');

	// $packer->strip=false;	// Turn off stripping whitespace.
	// $packer->strip_comments=false;	// Turn off stripping comments (Automatically enabled by $strip. Enabling $strip will override this.)

	// Set encoding..
	// $packer->encoding=false;	// Turn all encoding off
	// $packer->encoding='base64';	// Use base64 encoding. This is the default.

	// Run the packer
	$packer->pack();

	// Echo the code.
	echo $packer->code();

	// Or save the code..
	// if ($packer->save('packed.php')) echo "Saved file.\n";

	// Or you can chain functions together.
	// echo $packer->file('to_pack.php')->pack()->code();

?>
