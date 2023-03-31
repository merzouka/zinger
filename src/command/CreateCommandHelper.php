<?php

namespace DatabaseDefinition\Src\Command;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Console\ConsoleOutputFormatter;
use DatabaseDefinition\Src\Console\OutputType;
use DatabaseDefinition\Src\TableFactory;
use DatabaseDefinition\Src\TableType;
use Error;

class CreateCommandHelper{
    private static string $path;

    public static function setPath(){
        if (isset($path)){
            return;
        }
        $pathFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . "path.txt";
        $file = fopen($pathFile, "r");
        static::$path = fgets($file) . DIRECTORY_SEPARATOR;
        fclose($file);
    }

    /**
     * creates pivots for $tables
     *
     * @param array|null $tables the names of tables, if null create pivots of all
     * @param boolean $verbose true => enable output
     * @return void
     */
    public static function createPivots(?array $tables = null, bool $verbose = false){
        static::setPath();
        if ($tables === null || in_array("*", $tables)){
            $tables = [];
            foreach(scandir(static::$path . "tables") as $file){
                if ($file === "." || $file === ".."){
                    continue;
                }
                $tables[] = (explode(".", $file))[0];
            }
        }
        $error = false;
        foreach ($tables as $table){
            try{
                (TableFactory::createTable($table, TableType::Table, true))->createPivots();

            } catch (Error $e){
                if ($verbose){
                    $error = true;
                    (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
                }
            }
        }
        if ($error && $verbose){
            echo PHP_EOL;
        }
    }

}

CreateCommandHelper::createPivots(verbose:true);