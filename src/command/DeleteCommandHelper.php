<?php

namespace DatabaseDefinition\Src\Command;

use DatabaseDefinition\Src\Console\ConsoleOutputFormatter;
use DatabaseDefinition\Src\Console\OutputType;
use DatabaseDefinition\Src\Helpers\TableParser;
use DatabaseDefinition\Src\TableFactory;
use DatabaseDefinition\Src\TableType;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

class DeleteCommandHelper{

    #region constants
    private const MODELS = ROOT_DIR."app".DIRECTORY_SEPARATOR."Models".DIRECTORY_SEPARATOR;
    private const CONTROLLERS = ROOT_DIR."app".DIRECTORY_SEPARATOR."Http".DIRECTORY_SEPARATOR."Controllers".DIRECTORY_SEPARATOR;
    private const RESOURCES = ROOT_DIR."app".DIRECTORY_SEPARATOR."Http".DIRECTORY_SEPARATOR."Resources".DIRECTORY_SEPARATOR;
    private const FACTORIES =  ROOT_DIR."database".DIRECTORY_SEPARATOR."factories".DIRECTORY_SEPARATOR;
    private const MIGRATIONS = ROOT_DIR."database".DIRECTORY_SEPARATOR."migrations".DIRECTORY_SEPARATOR;
    private const SEEDERS = ROOT_DIR."database".DIRECTORY_SEPARATOR."seeders".DIRECTORY_SEPARATOR;
    private const EXCLUDES = [
        "Controller.php", "User.php", "UserFactory.php", ".", "..",
        "2014_10_12_000000_create_users_table.php",
        "2014_10_12_100000_create_password_reset_tokens_table.php",
        "2019_08_19_000000_create_failed_jobs_table.php",
        "2019_12_14_000001_create_personal_access_tokens_table.php",
        "DatabaseSeeder.php",
    ];
    #endregion

    #region private methods
    /**
     * returns the match in $haystack else false
     *
     * @param array $haystack
     * @param mixed $needle
     * @return mixed
     */
    private static function matchInArray(array $haystack, mixed $needle) : mixed{
        foreach ($haystack as $value){
            if (str_contains($needle, $value) || str_contains($value, $needle)){
                return $value;
            }
        }
        return false;
    }

    /**
     * deletes component
     *
     * @param string $component the plural lowercase name of the component
     * @param string $fileSuffix the upper camel case name of the component
     * @param array $tables
     * @return void
     */
    private static function deleteComponent(string $component, string $fileSuffix, array $tables = []){
        $component = $component === "collections" ? "resources" : $component;
        $path = constant("static::" . strtoupper($component));
        $hasDeleted = false;
        if ($tables === []){
            $component = strtolower($component);
            echo "delete all {$component}? [yes/" . BOLD . "no" . QUIT. "]";
            if (readline("> ") === "yes"){
                $fileSuffix = $fileSuffix === "" ? "Model" : $fileSuffix;
                foreach (scandir($path) as $file){
                    if (!in_array($file, static::EXCLUDES)){
                        unlink($path.$file);
                        (new ConsoleOutputFormatter(OutputType::Deleted, "$fileSuffix [" . BOLD . $path.$file . QUIT . "]."))->out();
                        $hasDeleted = true;
                    }
                }
            }
            if ($hasDeleted){
                echo PHP_EOL;
            }
            return;
        }
        $matches = [];
        $error = false;
        if ($component === "migrations"){
            foreach (scandir($path) as $file){
                $match = static::matchInArray($tables, $file);
                if (!in_array($file, static::EXCLUDES) && $match !== false){
                    unlink($path.$file);
                    (new ConsoleOutputFormatter(OutputType::Deleted, "Migration [" . BOLD . $path.$file . QUIT . "]."))->out();
                    $hasDeleted = true;
                    $matches[] = $match;
                }
            }
            foreach ($tables as $table){
                if (!in_array($table, $matches)){
                    (new ConsoleOutputFormatter(OutputType::Error, "$fileSuffix for '$table' doesn't exist."))->out();
                    $error = true;
                }
            }
            if ($error){
                echo PHP_EOL;
            }
            return;
        }
        foreach($tables as $table){
            if (TableParser::tableExists($table, TableType::Table)){
                $componentName = TableFactory::createTable($table, TableType::Table)->modelName.$fileSuffix;
                $fileName = $componentName . ".php";
                if (!in_array($fileName, static::EXCLUDES)){
                    @unlink($path. $fileName) or $error = true;
                    if (!$error){
                        (new ConsoleOutputFormatter(OutputType::Deleted, "$fileSuffix [" . BOLD . $path.$fileName . QUIT . "]."))->out();
                        $hasDeleted = true;
                    }
                    if ($error && $fileSuffix != "Collection" && $fileSuffix != "Resource"){
                        (new ConsoleOutputFormatter(OutputType::Error, "$fileSuffix '$componentName' doesn't exist."))->out();
                        $error = false;
                    }
                }
            }
        }
        if ($hasDeleted || ($error && $fileSuffix != "Collection" && $fileSuffix != "Resource")){
            echo PHP_EOL;
        }
    }

    /**
     * delete tables in $table of type $type
     *
     * @param TableType $type
     * @param array $tables
     * @return void
     */
    private static function deleteTableByType(
        TableType $type,
        array $tables = [],
        bool $forceDelete = false
    ){
        if ($tables === [] && !$forceDelete){
            (new ConsoleOutputFormatter(OutputType::Error, "Cannot delete all tables"))->out();
            echo PHP_EOL;
            return;
        } 
        $path = TableParser::getPath($type);
        if ($forceDelete || $tables === ["*"]){
            foreach (scandir($path) as $file){
                if (!in_array($file, static::EXCLUDES)){
                    unlink($path.$file);
                }
            }
            return;
        }
        $hasDeleted = false;
        $error = false;
        $errorPrefix = ucfirst($type->value);
        foreach ($tables as $table){
            @unlink($path.$table.TABLE_FILE_SUFFIX) or $error = true;
            if (!$error){
                (new ConsoleOutputFormatter(OutputType::Deleted, "table [" . BOLD . $path.$table.TABLE_FILE_SUFFIX . QUIT . "]."))->out();
                $hasDeleted = true;
            }
            if ($error){
                (new ConsoleOutputFormatter(OutputType::Error, "$errorPrefix '$table' does't exist."))->out();
                $error = false;
            }
        }
        if ($hasDeleted || $error){
            echo PHP_EOL;
        }
    }
    #endregion

    #region public methods

    public static function model(array $tables = []){
        static::deleteComponent("models", "", $tables); 
    }

    public static function controller(array $tables = []){
        static::deleteComponent("controllers", "Controller", $tables);
    }

    public static function resource(array $tables = []){
        static::deleteComponent("resources", "Resource", $tables);
    }

    public static function collection(array $tables = []){
        static::deleteComponent("collections", "Collection", $tables);
    }   

    public static function factory(array $tables = []){
        static::deleteComponent("factories", "Factory", $tables);
    }

    public static function migration(array $tables = []){
        static::deleteComponent("migrations", "Migration", $tables);
    }

    public static function seeder(array $tables = []){
        static::deleteComponent("seeders", "Seeder", $tables);
    }

    public static function components(array $tables = []){
        static::controller($tables);
        static::resource($tables);
        static::collection($tables);
        static::model($tables);
        static::factory($tables);
        static::migration($tables);
        static::seeder($tables);
    }

    public static function table(array $tables = []){
        static::deleteTableByType(TableType::Table, $tables);
    }

    public static function pivot(array $tables = []){
        static::deleteTableByType(TableType::Pivot, $tables, true);
    }

    #endregion

}
