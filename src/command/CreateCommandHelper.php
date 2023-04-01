<?php

namespace DatabaseDefinition\Src\Command;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Console\ConsoleOutputFormatter;
use DatabaseDefinition\Src\Console\OutputType;
use DatabaseDefinition\Src\Error\BaseTableError;
use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\TableParser;
use DatabaseDefinition\Src\TableFactory;
use DatabaseDefinition\Src\TableType;

/**
 * handles create commands
 */
class CreateCommandHelper{
    private static string $path;

    private static function setPath(){
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
    public static function pivot(?array $tables = null, bool $verbose = false){
        static::setPath();
        $tablesFromUser = true;
        if ($tables === null || in_array("*", $tables)){
            $tables = [];
            foreach(scandir(static::$path . "tables") as $file){
                if ($file === "." || $file === ".."){
                    continue;
                }
                $tables[] = (explode(".", $file))[0];
            }
            $tablesFromUser = false;
        }
        $error = false;
        foreach ($tables as $table){
            try{
                (TableFactory::createTable($table, TableType::Table, true))->createPivots($verbose);
            } catch (CustomError|BaseTableError $e){
                if ($e::class === "DatabaseDefinition\\Src\\Error\\BaseTableError" && $tablesFromUser){
                    (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
                    $error = true;
                    continue;
                } else if (!$tablesFromUser){
                    continue;
                }
                $error = true;
                (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
            }
        }
        if ($error && $verbose){
            echo PHP_EOL;
        }
    }

    /**
     * creates tables $tables
     *
     * @param array $tables
     * @param boolean $isBase
     * @return void
     */
    public static function table(array $tables, bool $isBase = false){
        $formatFile = !$isBase ? "tableFormat.php" : "baseTableFormat.php";
        $formatPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "table" . DIRECTORY_SEPARATOR . $formatFile;
        foreach ($tables as $tableName){
            if (TableParser::tableExists($tableName, TableType::Table)){
                (new ConsoleOutputFormatter(OutputType::Error, "Table '$tableName' already exists."))->out();
                echo PHP_EOL;
                return;
            }
            include_once $formatPath;
            $tablePath = TableParser::getPath(TableType::Table) . $tableName . TABLE_FILE_SUFFIX;
            $file = fopen($tablePath, "w");
            fwrite($file, $str);
            fclose($file);
            (new ConsoleOutputFormatter(OutputType::Created, ($isBase ? " base " : "")."table '$tableName'."))->out();
            echo PHP_EOL;
        }
        
    }

    /**
     * creates base tables $tables
     *
     * @param array $tables
     * @return void
     */
    public static function base(array $tables){
        static::table($tables, true);
    }

}

CreateCommandHelper::table(["hops", "lops"]);