<?php

/**
 * Basic Cheryl example of using a separate index script
 *
 * uses static cheryl methods
 *
 */

// show errors for debugging 
error_reporting(E_ALL ^ (E_NOTICE | E_STRICT));
ini_set('display_errors',true);


// if CHERYL_CONFIG is defined, the script will not automatilcy run
define('CHERYL_CONTROL', true);

// if you want better password hashing, put something here
define('CHERYL_SALT', 'SOMETHING/NOT/COOL/AND/RANDOM');

// include the Cheryl libraries
require_once('../../lib/Cheryl.php');

// give Cheryl our config. this will merge with the default config
Cheryl::init(array(
	'root' => '../files',
	'authentication' => array(
		'type' => 'pdo',
		'pdo' => new PDO('mysql:host=localhost;dbname=cheryl', 'root', 'root'),
		'user_table' => 'cheryl_user',
		'permission_table' => 'cheryl_permission'
	)
));

// manualy run the script since were using a custom config
Cheryl::start();
