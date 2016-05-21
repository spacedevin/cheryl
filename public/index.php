<?php

/**
 * Basic Cheryl example of using a separate index script
 *
 * uses static cheryl methods
 *
 */

require_once __DIR__ . '/../vendor/autoload.php';

// show errors for debugging
error_reporting(E_ALL ^ (E_NOTICE | E_STRICT));
ini_set('display_errors',true);


// if CHERYL_CONFIG is defined, the script will not automatilcy run
define('CHERYL_CONTROL', true);

// if you want better password hashing, put something here
define('CHERYL_SALT', 'SOMETHING/NOT/COOL/AND/RANDOM');


// give Cheryl our config. this will merge with the default config
\Cheryl\Cheryl::init([
	'root' => '../files',
	'users' => [[
		'username' => 'admin',
		'password' => \Cheryl\Cheryl::password('password'), // remove the function and place a hashed password here so you dont have to save it in plain text
		'permissions' => 'all'
	]]
]);

// manualy run the script since were using a custom config
\Cheryl\Cheryl::go();
