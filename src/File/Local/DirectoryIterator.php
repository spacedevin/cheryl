<?php

namespace Cheryl\File\Local;

class DirectoryIterator extends \DirectoryIterator {
	public function getExtension() {
		if (method_exists(get_parent_class($this), 'getExtension')) {
			$ext = parent::getExtension();
		} else {
			$ext = pathinfo($this->getPathName(), PATHINFO_EXTENSION);
		}
		return strtolower($ext);
	}
}
