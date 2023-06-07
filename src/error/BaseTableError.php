<?php


namespace DatabaseDefinition\Src\Error;

use Error;
/**
 * error in case base table is trying to be accessed
 */
class BaseTableError extends Error{

    #region properties
    public string $tableName;
    #endregion

    #region constructor
    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
        parent::__construct("Base table '{$tableName}' doesn't support 'create' operations.");
    }
    #endregion

}