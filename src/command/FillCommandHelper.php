<?php

namespace DatabaseDefinition\Src\Command;

use DatabaseDefinition\Src\Console\ConsoleOutputFormatter;
use DatabaseDefinition\Src\Console\OutputType;
use DatabaseDefinition\Src\Error\BaseTableError;
use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\TableParser;
use DatabaseDefinition\Src\TableFactory;
use DatabaseDefinition\Src\TableType;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

/**
 * handles fill commands
 */
class FillCommandHelper{

    #region private methods
    private static function fillTables(array &$tables, TableType $type){
        $path = TableParser::getPath($type);
        foreach (scandir($path) as $file){
            $tables[] = (explode(".", $file))[0];
        }
    }
    #endregion

    #region public methods
    public static function model(array $tables = []){
        $fromUser = true;
        if ($tables === [] || $tables === ["*"]){
            static::fillTables($tables, TableType::Table);
            $fromUser = false;
        }
        $error = false;
        foreach ($tables as $table){
            try{
                (TableFactory::createTable($table, TableType::Table, true))->addModelInfo();
            } catch (CustomError $e){
                (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
                $error = true;
            } catch (BaseTableError){
                if ($fromUser){
                    (new ConsoleOutputFormatter(OutputType::Error, "Base table cannot have a model."))->out();
                    $error = true;
                }
            }
        }
        if ($error){
            echo PHP_EOL;
        }
    }
    #endregion
}

