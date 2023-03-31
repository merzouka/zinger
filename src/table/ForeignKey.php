<?php 

namespace DatabaseDefinition\Src\Table;

use Error;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;

class ForeignKey{

    public array $keyInfo;
    

    private function  __construct(){}

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
            throw new SyntaxErrorException($e->getMessage());
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
        } catch (SyntaxErrorException $e){
            return $e->getMessage();
        }
        return null;
    }
    #endregion

    public function __toString()
    {
        $str = "(";
        foreach ($this->keyInfo as $method => $param){
            $str .= ($method == "onUpdate" ||  $method == "onDelete" ? ($method ." : ". $param) : "'$param'") . ", "; 
        }
        $str = substr($str, 0, -2);
        return $str . ")";
        
    }
}