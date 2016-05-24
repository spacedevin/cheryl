<?php

use Tipsy\Tipsy;
use Cheryl\Cheryl;

class BasicTest extends Cheryl_Test {
	public function setUp() {
		$this->useOb = true;
		Tipsy::app()->config(dirname(__FILE__).'/config.yml');
		Cheryl::init([]);
	}

	public function testConfigFile() {
		$this->assertEquals('../files', \Cheryl\Cheryl::me()->tipsy()->config()['cheryl']['root']);
	}
}
