<?php

/**
 * Cheryl entrypoint
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Tipsy\Tipsy;
use Cheryl\Cheryl;

// show errors for debugging
if (getenv('DEBUG') || strpos($_SERVER['HTTP_HOST'], 'localhost') > -1) {
	error_reporting(E_ALL ^ (E_NOTICE | E_STRICT));
	ini_set('display_errors', true);
}

// set a tipsy database url if we have it defined
if (getenv('DATABASE_URL')) {
	Tipsy::config(['db' => ['url' => getenv('DATABASE_URL')]]);
}

// give Cheryl our config. this will merge with the default config
// you can generate password hashes using password_hash('password', PASSWORD_BCRYPT)
Cheryl::init([]);

// do anything else you need before you start the request routing
Cheryl::start();
