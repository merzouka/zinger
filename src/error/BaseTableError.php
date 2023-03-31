<?php


namespace DatabaseDefinition\Src\Error;

use Error;

class BaseTableError extends Error{

    public string $tableName;
    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
        parent::__construct("Base table '{$tableName}' is not creatable.");
    }

}