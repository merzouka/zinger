<?php

namespace DatabaseDefinition\Src\Interfaces;

include AUTOLOADER;

use Illuminate\Database\Schema\Blueprint;

interface AddableInterface{
    
    /**
     * Applies the column attributes (type of column, column properties) on the table
     * following the conventions used by laravel
     */
    public function addInfo(Blueprint &$table);
}