<?php

namespace DatabaseDefinition\Src;
include_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Table\Table;

/**
 * helper class for database, used in ordering tables
 */
class Node{
    public Table $table;
    public array $children;

    public function __construct(string $tableName)
    {
        $this->table = TableFactory::createTable($tableName);
        $this->children = [];
        if (!isset($this->table->foreignKeys)){
            return;
        }
        foreach ($this->table->foreignKeys as $foreign){
            $this->children[] = $foreign->keyInfo["on"];
        }
    }
}