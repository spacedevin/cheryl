<?php

/**
 * Basic Cheryl example of using a separate index script
 */

require_once __DIR__ . '/../vendor/autoload.php';

// show errors for debugging
error_reporting(E_ALL ^ (E_NOTICE | E_STRICT));
ini_set('display_errors',true);

// give Cheryl our config. this will merge with the default config
\Cheryl\Cheryl::init([
	'users' => [[
		'username' => 'admin',
		'password' => password_hash('password', PASSWORD_BCRYPT),
		'permissions' => 'all'
	]]
]);

\Cheryl\Cheryl::start();
