<?php

namespace Whatasoft\IBlock\Properties\Custom;

use Whatasoft\IBlock\Fields\Manager;

class Base
{
    protected $fieldManager;
    
    public function __construct()
    {
        $this->fieldManager = new Manager();
    }
    
    public function getFieldManager()
    {
        return $this->fieldManager;
    }
}