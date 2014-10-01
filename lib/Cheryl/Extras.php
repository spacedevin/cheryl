<?php

// if this wasnt defined, then automaticly run the script asuming this is standalone
if (!defined('CHERYL_CONTROL')) {
	$cheryl = new Cheryl();
	$cheryl->go();
}