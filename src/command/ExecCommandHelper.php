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

class ExecCommandHelper{
    
    #region private methods
    private static function hasMigration(array $commands){
        foreach ($commands as $command){
            if (str_contains($command, "migration")){
                return true;
            }
        }
        return false;
    }
    private static function fillTables(array &$tables, TableType $type){
        $path = TableParser::getPath($type);
        foreach (scandir($path) as $file){
            if ($file !== "." && $file !== ".."){
                $tables[] = (explode(".", $file))[0];
            }
        }
    }
    #endregion

    #region public methods
    public static function exec(array $tables = [], bool $verbose = false){
        $fromUser = true;
        if ($tables === [] || $tables === ["*"]){
            $fromUser = false;
            static::fillTables($tables, TableType::Table);
            static::fillTables($tables, TableType::Pivot);
        }
        $error = false;
        foreach ($tables as $table){
            try{
                TableFactory::createTable($table)->exec($verbose);
            } catch (BaseTableError $e){
                if ($fromUser){
                    (new ConsoleOutputFormatter(OutputType::Error, "Cannot execute commands for base table."))->out();
                    $error = true;
                }
            } catch (CustomError $e){
                (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
                $error = true;
            }
        }
        if ($error){
            echo PHP_EOL;
        }
    }
    #endregion
}