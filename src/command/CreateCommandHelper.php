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
    #region properties
    private static string $path;
    #endregion

    #region private methods
    private static function setPath(){
        if (isset($path)){
            return;
        }
        $pathFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . PATH_FILE_NAME;
        $file = fopen($pathFile, "r");
        static::$path = fgets($file) . DIRECTORY_SEPARATOR;
        fclose($file);
    }

    /**
     * creates tables $tables
     *
     * @param array $tables
     * @param boolean $isBase
     * @return void
     */
    private static function generalTable(array $tables, bool $isBase = false){
        $formatFile = !$isBase ? "tableFormat.php" : "baseTableFormat.php";
        $formatPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "table" . DIRECTORY_SEPARATOR . $formatFile;
        var_dump($tables);
        foreach ($tables as $tableName){
            var_dump($tableName);
            if (TableParser::tableExists($tableName, TableType::Table)){
                (new ConsoleOutputFormatter(OutputType::Error, "Table '$tableName' already exists."))->out();
                echo PHP_EOL;
                return;
            }
            include $formatPath;
            $tablePath = TableParser::getPath(TableType::Table) . $tableName . TABLE_FILE_SUFFIX;
            $file = fopen($tablePath, "w");
            fwrite($file, $str);
            fclose($file);
            (new ConsoleOutputFormatter(OutputType::Created, ($isBase ? " base " : "")."table '$tableName'."))->out();
            echo PHP_EOL;
        }
        
    }
    #endregion

    #region public methods
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
                $error = true;
                (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
            } catch (BaseTableError $e){
                if ($tablesFromUser){
                    (new ConsoleOutputFormatter(OutputType::Error, $e->getMessage()))->out();
                    $error = true;
                }
            }
        }
        if ($error){
            echo PHP_EOL;
        }
    }

    /**
     * creates tables $tables
     *
     * @param array $tables
     * @return void
     */
    public static function table(array $tables){
        static::generalTable($tables);
    }

    /**
     * creates base tables $tables
     *
     * @param array $tables
     * @return void
     */
    public static function base(array $tables){
        static::generalTable($tables, true);
    }
    #endregion

}
