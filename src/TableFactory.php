<?php


namespace DatabaseDefinition\Src;

include_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Error\BaseTableError;
use DatabaseDefinition\Src\Helpers\TableParser as TP;
use DatabaseDefinition\Src\Pivot\MorphPivotDefinition;
use DatabaseDefinition\Src\Pivot\PivotDefinition;
use DatabaseDefinition\Src\Pivot\PivotTable;
use DatabaseDefinition\Src\Table\Table;
use DatabaseDefinition\Src\Table\TableDefinition;

use Error;

class TableFactory{
    private static function returnMorphOrPivot(array &$fileContents) : PivotTable{
        // normal pivots only contain 2 primary keys
        if (substr_count($fileContents["COLUMNS"], "[*PRIMARY*]") == 2){
            return PivotDefinition::toBeUsed($fileContents);
        }
        // morph pivots contain more than 2 primary keys
        return MorphPivotDefinition::toBeUsed($fileContents);
    }

    public static function createTable(string $tableName, ?TableType $type = null, bool $doRelations = false) : Table{
        switch($type){
            case TableType::Table:
                try{
                    $fileContents = TP::getDefinitionParts(TableType::Table, $tableName);
                } catch (Error){
                    throw new Error("Table '$tableName' of type '{$type->value}' doesn't exist.");
                }
                return new TableDefinition($fileContents, $doRelations);
                break;
            case TableType::Pivot:
                try{
                    $fileContents = TP::getDefinitionParts(TableType::Pivot, $tableName);
                } catch (Error $e){
                    echo $e::class . PHP_EOL;
                    if ($e::class === "DatabaseDefinition\\Src\\Error\\BaseTableError"){
                        throw new BaseTableError($e->tableName);
                    }
                    throw new Error("Table '$tableName' of type '{$type->value}' doesn't exist.");
                }
                return static::returnMorphOrPivot($fileContents);
                break;
            default:
                $isTableDefinition = false;
                try{
                    $fileContents = TP::getDefinitionParts(TableType::Table, $tableName);
                    $isTableDefinition = true;
                } catch (Error){
                    try{
                        $fileContents = TP::getDefinitionParts(TableType::Pivot, $tableName);
                    } catch (Error){
                        throw new Error("Table '$tableName' doesn't exist.");
                    }
                }
                if ($isTableDefinition){
                    return new TableDefinition($fileContents, $doRelations);
                } else {
                    return static::returnMorphOrPivot($fileContents);
                }
        }
    }
}

