<?php

namespace Cheryl\File\Local;

class FilterIterator extends \FilterIterator {
    public function accept() {
        return \Cheryl\Cheryl::iteratorFilter($this->current());
    }
}
