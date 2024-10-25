<?php

namespace DatabaseDefinition\Src\Pivot;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Interfaces\AddableInterface;
use Error;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * handles columns tagged as [*PRIMARY*]
 */
class PrimaryKey implements AddableInterface{

    public string $name;
    public ?string $jsonName;
    public array $type;
    public array $properties;
    private array $rowColumns;

    #region constructors
    private function __construct(){}

    /**
     * instantiate a new primary key instance to be later written to the pivot file
     *
     * @param string $name the name of the primary key
     * @param array $columnInfo array of the primary key of a table in the relationship
     * @return PrimaryKey
     */
    public static function toBeWritten(string $name, array $columnInfo) : PrimaryKey{
        $obj = new PrimaryKey();
        $obj->name = $name;
        $obj->jsonName = $columnInfo["json_name"] ?? "";
        $obj->type = $columnInfo["type"];
        $obj->type["method"] = SO::translateType($obj->type["method"]);
        $obj->properties = $columnInfo["properties"];
        return $obj;
    }

    /**
     * instantiate a new primary key instance to be later used
     *
     * @param string $str the primary key column definition in the file
     * @return PrimaryKey
     */
    public static function toBeUsed(array $info) : PrimaryKey{
        $obj = new PrimaryKey();
        $obj->name = $info["name"];
        $obj->jsonName = $info["json_name"] ?? "";
        $obj->type = $info["type"];
        $obj->properties = $info["properties"];
        return $obj;
    }
    #endregion

    #region general methods
    public function __toString()
    {
        $method = SO::getMethod($this->type);
        $properties = implode(", ", $this->properties);
        return "[*PRIMARY*]{$this->name}, null, {$method}". ($properties === "" ? "" : ", ". $properties) . ";";
    }

    /**
     * Applies the properties to the given column
     * returns null on success
     */
    private function applyProperties(ColumnDefinition &$column, array &$properties, int $index): string|null{
        if ($index >= count($properties)){
            return null;
        }
        try {
            $resultColumn = call_user_func([$column, $properties[$index]]);
        } catch (Error $e) {
            return $e->getMessage();
        }
        return $this->applyProperties($resultColumn, $properties, $index + 1);
    }


    public function addInfo(Blueprint &$table)
    {
        try{
            $this->type["params"][0] = $this->name;
            $column = call_user_func_array([$table, $this->type["method"]], $this->type["params"]);
        } catch (Error $e) {
            throw new CustomError("Error : " . $e->getMessage() . " In Primary Column " . $this->name);
        }
        $result = $this->applyProperties($column, $this->properties, 0);
        if ($result !== null){
            throw new CustomError("Error : " . $result . " In Primary Column " . $this->name);
        }
    }
    #endregion

    #region display

    public function prepareRowColumns() : array{
        $special = [GREEN.TICK.QUIT, RED.CROSS.QUIT];
        $this->rowColumns = [
            $this->name,
            $this->jsonName,
            RED . CROSS . QUIT,
            "null",
            SO::getMethod($this->type),
            implode(", ", $this->properties)
        ];
        return array_map(fn($str) => in_array($str, $special) ? 1 : strlen($str), $this->rowColumns);
    }

    public function display(array $lengths){
        $contents = implode(" | ", array_map(
            fn($str, $length) => SO::addChars($str, $length),
            $this->rowColumns,
            $lengths
        ));
        echo "| $contents |[*PRIMARY*]" . PHP_EOL;
    }
    #endregion

}