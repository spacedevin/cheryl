<?php

/**
 * Basic Cheryl example of using a separate index script
 */

require_once __DIR__ . '/../vendor/autoload.php';

// show errors for debugging
error_reporting(E_ALL ^ (E_NOTICE | E_STRICT));
ini_set('display_errors',true);

// give Cheryl our config. this will merge with the default config
// you can generate password hashes using password_hash('password', PASSWORD_BCRYPT)
\Cheryl\Cheryl::init();

\Cheryl\Cheryl::start();
