<?php

namespace DatabaseDefinition\Src\Alias;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include \AUTOLOADER;

use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\StringOper as SO;

class AliasHandler{

    private static array $aliases;
    private static string $aliasesPath;

    #region helpers
    /**
     * fill toFill with the contents of file 
     *
     * @param string $fileName should be "name.as"
     * @param array $toFill array to fill
     * @return void
     */
    private static function getFileAliases(string $fileName, array &$toFill){
        $filePath = static::getPath().$fileName;
        $file = fopen($filePath, "r");
        if (filesize($filePath) === 0){
            return;
        }
        $aliasArrays = explode(";", SO::removeWhiteSpaces(fread($file, filesize($filePath))));
        foreach ($aliasArrays as $aliasArray){
            if ($aliasArray === ""){
                continue;
            }
            // $aliasArray is in the form aliasArrayName = array
            $aliasArray = explode("=", $aliasArray);
            $toFill[$aliasArray[0]] = static::getAliasArray($aliasArray[1]);
        }
        fclose($file);
    }

    private static function loadAliases() : void{
        $aliasDir = static::getPath();
        static::$aliases = [];
        foreach (scandir($aliasDir) as $aliasFile){
            if ($aliasFile !== "." && $aliasFile !== ".." && str_contains($aliasFile, ALIAS_FILE_SUFFIX)){
                static::getFileAliases($aliasFile, static::$aliases);
            }
        }
    }
    
    /**
     * return a string containing the elements of the array in a nice format
     * [
     *     key : [
     *     key : value,
     *     key : value
     *     ]
     * ]
     *
     * @param array $array
     * @param string $prefix
     * @return string
     */
    private static function formatArrayToString(array $array, string $prefix = "") : string{
        $result = "[".PHP_EOL;
        $aliasStrings = [];
        foreach ($array as $key => $value){
            $aliasStrings[] = $prefix . "\t" ."$key : ". (is_array($value) ? static::formatArrayToString($value, $prefix."\t") : $value);
        }
        $result .= implode(",".PHP_EOL, $aliasStrings);
        return $result . PHP_EOL . "$prefix]";
    }

    /**
     * parses str to get the array of alias : value pairs
     *
     * @param string $str
     * @return array
     */
    private static function getAliasArray(string $str) : array{
        // remove [] and split array
        $str = SO::splitIfNotBrackets(",", substr($str, 1, -1));
        $result = [];
        foreach ($str as $alias){
            if ($alias === ""){
                continue;
            }
            $alias = SO::splitIfNotBrackets(":", $alias);
            $result[$alias[0]] = str_contains($alias[1], "[") ? static::getAliasArray($alias[1]) : $alias[1];
        }
        return $result;
    }

    private static function getValueRecursively(mixed $key, array $arr) : string|int|null{
        if (isset($arr[$key]) && !is_array($arr[$key])){
            return $arr[$key];
        }

        foreach ($arr as $element){
            if (is_array($element)){
                $value = static::getValueRecursively($key, $element);
                if ($value !== null){
                    return $value;
                }
            }
        }
        return null;
    }

    private static function getKeyRecursively(mixed $value, array $arr) : string|int|null{
        $key = array_search($value, $arr);
        if ($key !== false){
            return $key;
        }

        foreach ($arr as $element){
            if (is_array($element)){
                $key = static::getKeyRecursively($value, $element);
                if ($key !== null){
                    return $key;
                }
            }
        }
        return null;
    }
    #endregion

    #region reading functions
    /**
     * get the path of aliases using path in path.txt
     *
     * @return string
     */
    public static function getPath() : string{
        if (isset(static::$aliasesPath)){
            return static::$aliasesPath;
        }
        $pathFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . "path.txt";
        $file = fopen($pathFile, "r");
        $dataPath = fgets($file);
        fclose($file);
        static::$aliasesPath = $dataPath."aliases".DIRECTORY_SEPARATOR;
        return static::$aliasesPath;
    }

    

    public static function getArrayByNameOrGENERAL(string $aliasName = "") : array|null{
        if (!isset(static::$aliases)){
            static::loadAliases();
        }
        if (isset(static::$aliases[$aliasName])){
            return static::$aliases[$aliasName];
        }
        foreach (array_keys(static::$aliases) as $key){
            if (fnmatch($key, $aliasName)){
                return static::$aliases[$key];
            }
        }
        if (isset(static::$aliases["GENERAL"])){
            return static::$aliases["GENERAL"];
        }
        throw new CustomError("Alias '". $aliasName ."' Not Found");

    }
    #endregion

    #region alias info getters
    public static function getTableAlias(string $modelName, string $pivotTableName) : string | false{
        $associatedTableName = static::getTableName($modelName);
        $pivotTableAliases = static::getArrayByNameOrGENERAL($pivotTableName);
        $alias = static::getKeyRecursively($associatedTableName, $pivotTableAliases);
        if ($alias === false){
            throw new CustomError("Alias for table '$associatedTableName' doesn't exist in '$pivotTableName'.");
        } 
        return $alias;
    }

    public static function getAliasTable(string $alias, string $pivotTableName) : string{
        $pivotAliases = static::getArrayByNameOrGENERAL($pivotTableName);
        $table = static::getValueRecursively($alias, $pivotAliases);
        if ($table === null){
            throw new CustomError("Alias '$alias' doesn't exist in '$pivotTableName' array");
        }
        return $table;
    }

    public static function getTableName(string $modelName){
        $modelName = SO::last(explode("\\", $modelName));
        $modelAliases = static::getArrayByNameOrGENERAL("MODELS");
        if (!isset($modelAliases[$modelName])){
            throw new CustomError("Alias for '$modelName' doesn't exist.");
        }
        return $modelAliases[$modelName];
    }

    public static function getTableModel(string $tableName){
        $models = static::getArrayByNameOrGENERAL("MODELS");
        if (!isset(static::$aliases["MODELS"])){
            throw new CustomError("'MODELS' is not set.");
        }
        $modelName = array_search($tableName, $models);
        if ($modelName === false){
            throw new CustomError("No associated models for table '$tableName'.");
        }
        return "App\\Models\\" . $modelName;
    }

    public static function getAliasModel(string $alias, string $pivotTableName){
        return static::getTableModel(static::getAliasTable($alias, $pivotTableName));
    }

    public static function getAliasUsingPrefix(
        string $modelName,
        string $prefix,
        string $pivotTableName
        ) : string|int{
        $tableName = static::getTableName($modelName);
        $prefixArray = static::getPrefixAliases($prefix, $pivotTableName);
        $alias = array_search($tableName, $prefixArray);
        if ($alias === false){
            throw new CustomError("Alias not found in '$pivotTableName[$prefix]' for table '$tableName'");
        }
        return $alias;
    }

    public static function getPrefixAliases(string $prefix, string $pivotTableName) : array{
        $pivotAliases = static::getArrayByNameOrGENERAL($pivotTableName);
        if (!isset($pivotAliases[$prefix]) || !is_array($pivotAliases[$prefix])){
            throw new CustomError("Prefix '$prefix' doesn't exist in $pivotTableName.");
        }
        return $pivotAliases[$prefix];
    }
    #endregion

    #region write functions
    public static function registerTableModel(string $modelName, string $tableName){
        static::loadAliases();
        $modelAliases = [];
        if (isset(static::$aliases["MODELS"])){
            $modelAliases = static::$aliases["MODELS"];
        }
        static::addMODELSAlias([
            "MODELS" => array_merge($modelAliases, [$modelName => $tableName])
        ]);
    }
    
    /**
     * writes aliases to file of name file name
     *
     * @param array $aliases aliases to write [aliasName => array(aliases)]
     * @param string $fileName should be "name.as"
     * @return void
     */
    public static function writeAliasesToFile(array $aliases, string $fileName){
        $toWrite = [];
        $dependenciesPath = static::getPath() . $fileName;
        if (file_exists($dependenciesPath)){
            static::getFileAliases($fileName, $toWrite);
        }
        $toWrite = array_merge($toWrite, $aliases);
        $fileContents = [];
        foreach ($toWrite as $name => $aliases){
            $fileContents[] = "$name = " . static::formatArrayToString($aliases);
        }
        $fileContents = implode(";".PHP_EOL, $fileContents);
        $file = fopen(static::getPath() . $fileName, "w");
        fwrite($file, $fileContents);
        fclose($file);
    }

    public static function addMODELSAlias(array $modelAlias){
        static::writeAliasesToFile($modelAlias, "dependencies.as");
    }
    #endregion

}

