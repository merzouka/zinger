<?php

namespace DatabaseDefinition\Src\Helpers;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Error\BaseTableError;
use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\TableType;

/**
 * parses the table definition files and retrieves their info
 */
class TableParser{

    #region constants
    private const DEFINITION_GENERAL_PARTS = [
        "EXCLUDE", "NAME", "HAS_TIMESTAMPS", "RECORDS",
        "FOREIGN_KEYS", "RELATIONS", "COLUMNS", "MODEL"
    ];
    #endregion

    #region properties
    private static array $definitionParts = [];
    private static array $fileContents = [];
    #endregion

    #region methods
    public static function getPath(TableType $type) : string{
        if ($type == TableType::Base){
            $type = TableType::Table;
        }
        $pathFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . PATH_FILE_NAME;
        $file = fopen($pathFile, "r");
        $dataPath = fgets($file);
        fclose($file);
        return $dataPath.$type->value."s".DIRECTORY_SEPARATOR;
    }

    public static function tableExists(string $tableName, ?TableType $type = null) : bool{
        if ($type !== null){
            return file_exists(static::getPath($type) . $tableName . TABLE_FILE_SUFFIX);
        }
        return file_exists(static::getPath(TableType::Table) . $tableName . TABLE_FILE_SUFFIX) ||
        file_exists(static::getPath(TableType::Pivot) . $tableName . TABLE_FILE_SUFFIX);
    }

    /**
     * gets the text in each of predefined tags (see DEFINITION_PARTS_GENERAL) of the string
     * 
     */
    private static function getPartsText(string $str)
    {
        if ($str === "") {
            return;
        }
        $strParts = [];
        foreach (static::DEFINITION_GENERAL_PARTS as $part) {
            if (!in_array($part, static::$definitionParts)) {
                $strParts = explode($part . ":", $str);
                if (count($strParts) > 1) {
                    break;
                }
            }
        }
        if (count($strParts) > 1) {
            static::getPartsText($strParts[0]);
            static::$definitionParts[] = $part;
            static::getPartsText($strParts[1]);
            return;
        }

        static::$fileContents[] = $str;
    }

    /**
     * adds the parts as keys to text modules
     */
    private static function getIndexedParts() : array{
        $x = [];
        for ($i = 0; $i < count(static::$definitionParts); $i++){
            $x[static::$definitionParts[$i]] = static::$fileContents[$i];
        }
        return $x;   
    }

    /**
     * - returns an array with the parts as keys for there associated text parts in $tableContents.
     * - throws a BaseTableError if the table to be accessed is a base table and the $type is not
     * Base.
     * - throw a CustomError if the table of type $type doesn't exist.
     *
     * @param TableType $type
     * @param string $tableName
     * @return array
     */
    public static function getDefinitionParts(TableType $type, string $tableName) : array{
        // if table already parsed skip
        $ind = array_search("NAME", static::$definitionParts);
        if (
            isset(static::$fileContents[$ind]) &&
            SO::removeWhiteSpaces(static::$fileContents[$ind]) === $tableName &&
            !isset(static::$fileContents["isBase"])
        ){
            return static::getIndexedParts();
        }
        // empty up dependency arrays
        static::$definitionParts = [];
        static::$fileContents = [];

        $tablePath = static::getPath($type) . $tableName . TABLE_FILE_SUFFIX;
        if (!file_exists($tablePath)){
            throw new CustomError("Table '$tableName' of type '{$type->value}' doesn't exist.");
        }
        $file = fopen($tablePath, "r");
        $fileContents = SO::removeComments(fread($file, filesize($tablePath)));
        // if table shouldn't be base but is
        if (str_contains($fileContents, "[*BASE*]") && $type !== TableType::Base){
            throw new BaseTableError($tableName);
        }
        if ($type == TableType::Base){
            $fileContents = str_replace("[*BASE*]", "", $fileContents);
        }
        $fileContents = SO::removeWhiteSpaces($fileContents);
        static::getPartsText($fileContents);
        // to avoid sending base table info in the first conditional statement
        if ($type === TableType::Base){
            static::$fileContents["isBase"] = true;
        }
        fclose($file);
        return static::getIndexedParts();
    }
    #endregion
}

