<?php 

namespace DatabaseDefinition\Src\Interfaces;

include AUTOLOADER;

use Illuminate\Database\Schema\Blueprint;


interface TableInterface{

    public function defineTable(Blueprint &$table);

}