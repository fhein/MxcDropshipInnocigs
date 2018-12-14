<?php

namespace MxcDropshipInnocigs\Zend\Filter;

use Zend\Filter\Exception;

class StartsWith extends Needles
{
    /**
     * Returns the result of filtering $value
     *
     * @param  mixed $value
     * @throws Exception\RuntimeException If filtering $value is impossible
     * @return mixed
     */
    public function apply(&$value)
    {
        foreach($this->needles as $needle) {
            $len = strlen($needle);
            if (substr($value, 0, $len) === $needle) return true;
        }
        return false;
    }
}