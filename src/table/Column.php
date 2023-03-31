<?php

namespace DatabaseDefinition\Src\Table;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Helpers\DefinitionHelper;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Interfaces\AddableInterface;

use Error;
use Faker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;



class Column implements AddableInterface{

    public string $name;
    public ?string $jsonName;
    public bool $fillable;
    public array $columnInfo;
    public int $columnNumber;

    public function __construct(array $columnInfo, int $columnNumber)
    {
        $this->columnInfo = $columnInfo;
        $this->columnNumber = $columnNumber;
        $this->name = $columnInfo["name"];
        $this->jsonName = isset($columnInfo["json_name"]) ? $columnInfo["json_name"] : null;
        $this->fillable = $columnInfo["fillable"];
    }

    /**
     * Generates a value according to the specified faker method
     */
    public function faker(){
        if ($this->columnInfo["faker"]["method"] === "null"){
            return null;
        }
        $faker = Faker\Factory::create();
        try {
            return call_user_func_array([$faker, $this->columnInfo["faker"]["method"]], $this->columnInfo["faker"]["params"]);
        } catch (Error $e){
            throw new SyntaxErrorException("Error : " . $e->getMessage() . " In Column " . $this->columnNumber);
        }
    }

    
    public function addInfo(Blueprint &$table){
        try{
            $column = call_user_func_array([$table, $this->columnInfo["type"]["method"]], $this->columnInfo["type"]["params"]);
        } catch (Error $e) {
            throw new SyntaxErrorException("Error : " . $e->getMessage() . " In Column " . $this->columnNumber);
        }
        $result = $this->applyProperties($column, $this->columnInfo["properties"], 0);
        if ($result !== null){
            throw new SyntaxErrorException("Error : " . $result . " In Column " . $this->columnNumber);
        }
    }

    /**
     * Applies the properties to the given column
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

    public function __toString()
    {
        return "{$this->name}, {$this->jsonName}, ". SO::boolToString($this->fillable).
        ", ". SO::getMethod($this->columnInfo["faker"], false) .", ". 
        SO::getMethod($this->columnInfo["type"]). implode(", ", $this->columnInfo["properties"]). ";";
    }
}





