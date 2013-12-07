<?php

error_reporting(E_ALL ^ (E_NOTICE | E_STRICT));
ignore_user_abort(false);
ini_set('display_errors',true);
set_time_limit(10);

$config = array(
	'root' => 'files'
);
require_once('../../src/cheryl.php');