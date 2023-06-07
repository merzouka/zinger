<?php


namespace DatabaseDefinition\Src;

include_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Error\SyntaxError;
use DatabaseDefinition\Src\Helpers\TableParser as TP;
use DatabaseDefinition\Src\Pivot\MorphPivotDefinition;
use DatabaseDefinition\Src\Pivot\PivotDefinition;
use DatabaseDefinition\Src\Pivot\PivotTable;
use DatabaseDefinition\Src\Table\TableDefinition;

/**
 * the primary class to create pivot, morph pivot and normal table class instances
 */
class TableFactory{
    #region private methods
    private static function returnMorphOrPivot(array &$fileContents) : PivotTable{
        // normal pivots only contain 2 primary keys
        if (substr_count($fileContents["COLUMNS"], "[*PRIMARY*]") == 2){
            return PivotDefinition::toBeUsed($fileContents);
        }
        // morph pivots contain more than 2 primary keys
        return MorphPivotDefinition::toBeUsed($fileContents);
    }

    private static function getFileContents(string $tableName, TableType $type, array &$fileContents){
        try{
            $fileContents = TP::getDefinitionParts($type, $tableName);
        } catch (CustomError $e){
            throw new CustomError("Table '$tableName' of type '{$type->value}' doesn't exist.");
        } catch (SyntaxError $e){
            throw new CustomError($e->getMessage() . " in {$type->value} '$tableName'.");
        }
    }
    #endregion

    #region public methods
    public static function createTable(
        string $tableName,
        ?TableType $type = null,
        bool $doRelations = false,
    ) : MorphPivotDefinition|PivotDefinition|TableDefinition{
        $fileContents = [];
        switch($type){
            case TableType::Table:
                static::getFileContents($tableName, TableType::Table, $fileContents);
                return new TableDefinition($fileContents, $doRelations);
                break;
            case TableType::Pivot:
                static::getFileContents($tableName, TableType::Pivot, $fileContents);
                return static::returnMorphOrPivot($fileContents);
                break;
            case TableType::Base:
                static::getFileContents($tableName, TableType::Base, $fileContents);
                return new TableDefinition($fileContents, $doRelations);
                break;
            default:
                $isTableDefinition = false;
                try{
                    $fileContents = TP::getDefinitionParts(TableType::Table, $tableName);
                    $isTableDefinition = true;
                } catch (CustomError){
                    try{
                        $fileContents = TP::getDefinitionParts(TableType::Pivot, $tableName);
                    } catch (CustomError){
                        throw new CustomError("Table '$tableName' doesn't exist.");
                    }
                }
                if ($isTableDefinition){
                    return new TableDefinition($fileContents, $doRelations);
                } else {
                    return static::returnMorphOrPivot($fileContents);
                }
        }
    }
    #endregion

}
