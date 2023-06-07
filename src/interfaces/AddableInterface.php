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

    /**
     * puts the Addable info into an array attribute to be later displayed
     *
     * @return array the lengths of each column of addable
     */
    public function prepareRowColumns() : array;

    /**
     * display the current Addable to console
     *
     * @return void
     */
    public function display(array $lengths);
}