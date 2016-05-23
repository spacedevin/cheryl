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

// give Cheryl our config. this will merge with the default config
\Cheryl\Cheryl::init([
	'root' => '../files',
	'users' => [[
		'username' => 'admin',
		'password' => password_hash('password', PASSWORD_BCRYPT),
		'permissions' => 'all'
	]]
]);

// manualy run the script since were using a custom config
\Cheryl\Cheryl::go();
