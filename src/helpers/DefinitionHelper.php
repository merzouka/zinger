<?php

namespace DatabaseDefinition\Src\Helpers;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include \AUTOLOADER;

use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Helpers\TableParser;
use DatabaseDefinition\Src\TableType;

/**
 * used to parse and format info of pivot, base, general tables
 */
class DefinitionHelper
{


    #region General
    /**
     * gets the parameters of a function from a given string
     */
    private static function getParams(string $str): array
    {
        if ($str === ""){
            return [];
        }
        $is_array = ($str[0] == "[");
        $str = substr($str, ($is_array) ? 1 : 0, ($is_array) ? -1 : null);
        $param_values = array_map(
            fn ($param) => ($param[0] == "[") ? static::getParams($param) : // if nested array
                SO::parseParam($param), // if normal paramter
            SO::splitIfNotBrackets(",", $str)
        );
        return $param_values;
    }

    /**
     * returns an array:
     * "method" => method name
     * "params" => array(params), if no parameters were given params = empty array
     */
    public static function getMethodInfo(string $str): array
    {
        $str_parts = explode("(", $str);
        $result = [];
        $result["method"] = $str_parts[0];
        if (count($str_parts) > 1) {
            $params = static::getParams(substr($str_parts[1], 0, -1));
            $result["params"] = ($str_parts[1][0] == "[") ? [$params] : $params;
        } else $result["params"] = [];
        return $result;
    }

    /**
     * returns offset in $haystack that contains $needle starting from $offset inclusive
     *
     * @param string $needle 
     * @param array $haystack
     * @param integer $offset starting position in array
     * @return int
     */
    public static function arrayFindFromOffset(string $needle, array &$haystack, int $offset = 0) : int{
        for ($i = $offset; $i < count($haystack); $i++){
            if (str_contains($haystack[$i], $needle)){
                return $i;
            }
        }
        throw new CustomError("'$needle' doesn't exist");
    }

    #endregion

    #region COLUMNS methods
    /**
     * gets the info of a table column defined under the COLUMNS section
     * the return array = [
     * "name" if not specified 'id' is used,
     * "json_name" if null then omitted
     * "fillable" by default false unless set to true
     * "faker" = array ["method", "params"]
     * "type" = array["method", "params"] the name of the column is always appended to "params"
     * "properties" = empty array if non are given
     * ]
     *
     * @param string $column
     * @return array|null
     */
    public static function getColumnInfo(string $column): array|null
    {
        $column = SO::splitIfNotBrackets(",", $column);
        if (count($column) < 5) {
            return null;
        }
        $type = static::getMethodInfo($column[4]);
        array_unshift($type["params"], $column[0]);
        if ($column[0] === ""){
            $column[0] = "id";
        }
        $result = [];
        // if null exclude from resource return array
        if ($column[1] != "null"){
            $result["json_name"] = $column[1];
        }
        $result = array_merge($result, [
            "name"          => $column[0],
            "fillable"      => (strtolower($column[2]) == "true"),
            "faker"         => static::getMethodInfo($column[3]),
            "type"          => $type,
            "properties"    => array_slice($column, 5)
        ]);
        return $result;
        /*
        output : [
            "name"
            "json_name" (if not set to null)
            "fillable" => (value == true)
            "faker" => ["method", "params" => [(empty if non provided)]]
            "type" => ["method", "params" => ["name", specified method params]]
            "properties" => [(empty if non provided)]
        ]
        */
    }

    /**
     * gets info of columns flagged by [*PRIMARY*]
     * return array = ["name", "json_name", "type", "properties"]
     * faker is automatically set to null, and the column is not fillable
     *
     * @param string $column
     * @return array|null
     */
    public static function getPrimaryColumnInfo(string $column) : array|null{
        // remove [*PRIMARY*] tag
        $column = substr($column, 11);
        $column = SO::splitIfNotBrackets(",", $column);
        // TODO: column contains jsonName, type, properties
        if (count($column) < 3){
            return null;
        }
        $result = [];
        if ($column[1] != "null"){
            $result["json_name"] = $column[1];
        }
        $type = static::getMethodInfo($column[2]);
        array_unshift($type["params"], $column[0]);
        return array_merge($result, [
            "name"          => $column[0],
            "type"          => $type,
            "properties"    => array_slice($column, 3)
        ]);
        /*
        output : [
            "name"
            "json_name" (if not set to null)
            "type" => ["method", "params" => ["name", specified method params]]
            "properties" => [(empty if non provided)]
        ]
        */
    }

    public static function getColumnsInfo(string $str, bool $inChild = false): array
    {
        if ($str === ""){
            return [];
        }
        $str = explode(";", $str);
        $columns = [];
        foreach ($str as $column) {
            // check for inheritance, and recursively load columns
            if (str_contains($column, "[*") && !str_contains($column, "[*PRIMARY*]")){
                $parts = explode("*]", $column);
                // tableName, tableType
                $tableInfo = explode(",", substr($parts[0], 2));
                // get parent columns
                $columns = array_merge($columns, static::getColumnsInfo(TableParser::getDefinitionParts(TableType::from(strtolower($tableInfo[1])), $tableInfo[0])["COLUMNS"], true));
                $column = $parts[1];
            }
            if (str_contains($column, "[*PRIMARY*]")){
                if (!$inChild){
                    $column = static::getPrimaryColumnInfo($column);
                    if ($column !== null){
                        $columns["PRIMARY"][$column["name"]] = $column;
                    }
                }
            } else {
                $column = static::getColumnInfo($column);
                if ($column !== null) {
                    $columns[$column["name"]] = $column;
                }
            }
        }
        return $columns;
    }
    #endregion

    #region RELATIONS methods
    /**
     * gets the information of methods under the RELATIONS section
     */
    public static function getRelationMethodsInfo(string $str): array
    {
        if ($str === ""){
            return [];
        }
        $result = [];
        foreach (SO::splitIfNotBrackets(",", $str) as $relationMethod) {
            $methodInfo = static::getMethodInfo($relationMethod);
            $result = array_merge($result, [$methodInfo]);
        }
        return $result;
    }
    #endregion

    #region FOREIGN KEYS methods

    /**
     * returns array of onUpdate|onDelete => value
     * the value is not incased in " or '
     * duplicates are overridden by newer value assignments
     */
    private static function getOnUpdateOnDelete(array $arr): array|null
    {
        $result = [];
        foreach ($arr as $method) {
            $methodInfo = explode(":", $method);
            $value = strtolower($methodInfo[1]);
            $value = str_contains($value, "'") || str_contains($value, '"') ? substr($value, 1, -1) : $value;
            $result[$methodInfo[0]] = $value;
        }
        return (count($result) > 0) ? $result : null;
    }

    /**
     * gets the info of a single foreign key
     * return array ["foreign", "references", "on", "onUpdate", "onDelete"] 
     * "onUpdate" and "onDelete" are omitted if not provided
     */
    private static function getForeignKeyInfo(string $str): array
    {
        if ($str == ""){
            return [];
        }
        $str = substr($str, 1, -1); // remove parentheses
        $relationshipInfo = explode(",", $str);
        $foreignKeyMethods = static::getOnUpdateOnDelete(array_slice($relationshipInfo, 3));
        return array_merge([
            "foreign" => SO::parseParam($relationshipInfo[0]),
            "references" => SO::parseParam($relationshipInfo[1]),
            "on" => SO::parseParam($relationshipInfo[2]),

        ], ($foreignKeyMethods === null) ? [] : $foreignKeyMethods);
    }

    /**
     * return an array of the info of all foreign keys,
     */
    public static function getForeignKeysInfo(string $keys): array
    {
        $keys = SO::splitIfNotBrackets(",", $keys);
        $result = [];
        foreach ($keys as $key) {
            $result = array_merge($result, [static::getForeignKeyInfo($key)]);
        }
        return $result;
    }
    #endregion

    #region EXCLUDE methods

    /**
     * returns an array containing the commands info excluding $excludes
     * [["command"] => command value,
     * ["param"] => command params];
     * available commands are "make:migration", "make:model", "make:resource"
     *
     * @param string|null $excludes : parameters to exclude
     * @param string $modelName
     * @return array
     */
    public static function getCommandsInfo(string|null $excludes, string $modelName, string $tableName = ""): array
    {
        if ($modelName === ""){
            return [];
        }
        $commands = [];
        $modelRelatedOptions = ["c", "f", "m", "s"];
        $isApi = false;
        $resourceRelatedOptions = ["resource", "collection"];
        // if null nothing to exclude
        if ($excludes !== null){
            $excludes = array_map(fn($str) => strtolower($str),explode(",", $excludes));
            // if in array only run migration command
            if (in_array("model", $excludes)){
                $tableName = $tableName;
                return ["php artisan make:migration create_{$tableName}_table"];
            }
            $isApi = !in_array("api", $excludes);
            $modelRelatedOptions = array_filter($modelRelatedOptions, fn ($option) => !in_array($option, $excludes));
            $resourceRelatedOptions = array_filter($resourceRelatedOptions, fn ($option) => !in_array($option, $excludes));
        }
        $commands[] = [
            "command" => "make:model",
            "params" => [
                "args" => "-" . implode("", $modelRelatedOptions),
                "name" => $modelName
            ]
        ];
        if ($isApi) { $commands[0]["params"]["api"] = "--api"; }
        foreach ($resourceRelatedOptions as $option) {
            $commands[] = [
                "command"   => "make:resource",
                "params"    => ["name" => $modelName . ucfirst($option)]
            ];
        }
        $commands = array_map(fn($command) => "php artisan ". $command["command"] ." ".
            ((isset($command["params"]["args"]) ? $command["params"]["args"] . " " : "")).
            ((isset($command["params"]["api"])) ? "--api " : "").
            $command["params"]["name"],
            $commands
        );
        return $commands;
    }

    #endregion

    /**
     * returns the part of the file using the identifier $part after formatting it 
     * as needed
     * the following parts will return null if non existing: EXCLUDE, FOREIGN_KEYS, RELATIONS
     * @param string $part the part identifier
     * @param array $fileContents the contents of the file
     * @return mixed the formatted part
     */
    public static function getInfoByPart(string $part, array &$fileContents) : mixed
    {
        $modelName = $fileContents["MODEL"] ?? SO::getModelName($fileContents["NAME"]);
        return match ($part) {
            "EXCLUDE" => static::getCommandsInfo($fileContents["EXCLUDE"] ?? null, $modelName, $fileContents["NAME"]),
            "NAME" => $fileContents["NAME"],
            "MODEL" => $fileContents["MODEL"] ?? $modelName,
            "HAS_TIMESTAMPS" => (isset($fileContents["HAS_TIMESTAMPS"])) ? (strtolower($fileContents["HAS_TIMESTAMPS"]) == "false" ? false : true) : true,
            "RECORDS" => (isset($fileContents["RECORDS"])) ? (int) $fileContents["RECORDS"] : 0,
            "FOREIGN_KEYS" => (isset($fileContents["FOREIGN_KEYS"])) ? static::getForeignKeysInfo($fileContents["FOREIGN_KEYS"]) : [],
            "RELATIONS" => (isset($fileContents["RELATIONS"])) ? static::getRelationMethodsInfo($fileContents["RELATIONS"]) : [],
            "COLUMNS" => static::getColumnsInfo($fileContents["COLUMNS"])
        };
    }

}



