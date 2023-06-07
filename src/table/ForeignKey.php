<?php 

namespace DatabaseDefinition\Src\Table;

use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use Error;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;

/**
 * used for writing and using foreign keys
 */
class ForeignKey{

    #region properties
    public array $keyInfo;
    // related to display
    private array $keyColumns;
    #endregion

    #region constructor
    private function  __construct(){}
    #endregion

    #region instantiation
    private static function addToArrayIfNotEmpty(array &$arr, string $key, string $val){
        if ($val !== ""){
            $arr[$key] = $val;
        }
    }

    /**
     * instantiate a new ForeignKey object from array
     *
     * @param array $keyInfo
     * @return ForeignKey
     */
    public static function fromArray(array $keyInfo) : ForeignKey{
        $obj = new ForeignKey();
        $obj->keyInfo = $keyInfo;
        return $obj;
    }
    
    /**
     * instantiate a new Foreign key object from parameters
     *
     * @param string $foreign
     * @param string $references
     * @param string $on
     * @param string $onUpdate
     * @param string $onDelete
     * @return ForeignKey
     */
    public static function fromParams(
        string $foreign, string $references = "", string $on = "", 
        string $onUpdate = "", string $onDelete = ""
    ) : ForeignKey{
        $obj = new ForeignKey();
        $arr["foreign"] = $foreign;
        static::addToArrayIfNotEmpty($arr, "references", $references);
        static::addToArrayIfNotEmpty($arr, "on", $on);
        static::addToArrayIfNotEmpty($arr, "onUpdate", $onUpdate);
        static::addToArrayIfNotEmpty($arr, "onDelete", $onDelete);
        $obj->keyInfo = $arr;
        return $obj;
    }

    #endregion

    #region apply key
    /**
     * adds references, on, onUpdate, onDelete to the foreign key
     *
     * @param ForeignKeyDefinition $def
     * @param array $foreignKey contains the properties of the foreign key
     * @param integer $index used to advance in the array $foreignKey
     * @return void
     */
    private function applyForeignProperties(ForeignKeyDefinition &$def,array &$foreignKey, int $index = 1){
        if ($index >= count($this->keyInfo)){
            return;
        }
        try {
            $method = (array_keys($this->keyInfo))[$index];
            $result = call_user_func_array([$def, $method], [$this->keyInfo[$method]]);
        } catch (Error $e){
            throw new CustomError($e->getMessage());
        }
        $this->applyForeignProperties($result, $foreignKey, $index + 1);
    }

    /**
     * adds this foreign key to table
     *
     * @param Blueprint $table
     * @return string|null
     */
    public function addForeignKey(Blueprint &$table) : string|null{
        try{
            $result = call_user_func_array([$table, "foreign"], [$this->keyInfo["foreign"]]);
        } catch (Error $e){
            return $e->getMessage();
        }
        try {
            $this->applyForeignProperties($result, $this->keyInfo);
        } catch (CustomError $e){
            return $e->getMessage();
        }
        return null;
    }
    #endregion

    #region general
    /**
     * used when creating pivot table
     *
     * @return string
     */
    public function __toString()
    {
        $str = "(";
        foreach ($this->keyInfo as $method => $param){
            $str .= ($method == "onUpdate" ||  $method == "onDelete" ? ($method ." : ". $param) : "'$param'") . ", "; 
        }
        $str = substr($str, 0, -2);
        return $str . ")";
        
    }
    #endregion

    #region display
    public function prepareKeyColumns(){
        $this->keyColumns = [
            $this->keyInfo["foreign"],
            $this->keyInfo["references"],
            $this->keyInfo["on"],
            $this->keyInfo["onUpdate"] ?? "",
            $this->keyInfo["onDelete"] ?? ""
        ];
        return array_map(fn($str) => strlen($str), $this->keyColumns);
    }

    public function display(array $lengths){
        $contents = implode(" | ", array_map(
            fn($str, $length) => SO::addChars($str, $length),
            $this->keyColumns,
            $lengths
        ));
        echo "| $contents |" . PHP_EOL;
    }
    #endregion

}