<?php

namespace DatabaseDefinition\Src\Command;

use DatabaseDefinition\Src\Alias\AliasHandler;
use DatabaseDefinition\Src\Console\ConsoleOutputFormatter;
use DatabaseDefinition\Src\Console\OutputType;
use DatabaseDefinition\Src\Error\BaseTableError;
use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\TableFactory;
use DatabaseDefinition\Src\TableType;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

/**
 * handles show command
 */
class ShowCommandHelper{

    #region private methods
    private static function displayNoneBase(string $tableName, TableType $type){
        $error = false;
        try {
            (TableFactory::createTable($tableName, $type, true, false))->display();
        } catch (CustomError $e) {
            (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
            $error = true;
        } catch (BaseTableError $e){
            (new ConsoleOutputFormatter(OutputType::Error, "Cannot display base table '$tableName' using 'table', use ".BOLD . "base" . QUIT." command instead."))->out();
            $error = true;
        }
        if ($error){
            echo PHP_EOL;
        }
    }
    #endregion

    #region public methods

    public static function table(array $tableNames){
        static::displayNoneBase($tableNames[0], TableType::Table);
    } 

    public static function pivot(array $tableNames){
        static::displayNoneBase($tableNames[0], TableType::Pivot);
    }

    public static function base (array $tableNames){
        try {
            (TableFactory::createTable($tableNames[0], TableType::Base, true))->display();
        } catch (CustomError $e){
            (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
            echo PHP_EOL;
        }
    }

    public static function alias(array $aliases = []){
        if ($aliases === [] || $aliases === ["*"]){
            (new ConsoleOutputFormatter(OutputType::Error, BOLD . "alias" . QUIT . " only supports specific aliases."))->out();
            echo PHP_EOL;
            return;
        }
        foreach ($aliases as $alias){
            try{
                AliasHandler::display($alias);
            } catch (CustomError $e){
                (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
                echo PHP_EOL;
            }
        }
    }

    #endregion

}

