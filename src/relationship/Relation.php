<?php

namespace DatabaseDefinition\Src\Relationship;

use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Helpers\TableParser;
use DatabaseDefinition\Src\Pivot\Morph;
use DatabaseDefinition\Src\Pivot\MorphPivotDefinition;
use DatabaseDefinition\Src\Pivot\PivotDefinition;
use DatabaseDefinition\Src\Pivot\PivotTable;
use DatabaseDefinition\Src\Table\TableDefinition;
use DatabaseDefinition\Src\TableFactory;
use DatabaseDefinition\Src\TableType;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

class Relation
{   
    #region properties
    public TableDefinition $owner;
    public TableDefinition $related;
    public PivotTable $pivot;
    public string $method;
    public array $parameters;
    #endregion

    #region constants
    public const METHODS_WITHOUT_RELATED = [
        "morphsOne", "morphsMany", "hasOneThroughManyMorphs", "hasManyThroughManyMorphs"
    ];
    #endregion

    #region constructors
    public function __construct(array $relationInfo, TableDefinition &$owner, bool $forceNew = false)
    {
        $this->method = $relationInfo["method"];
        $this->owner = $owner;
        if (!in_array($this->method, static::METHODS_WITHOUT_RELATED)){
            $this->related = TableFactory::createTable($relationInfo["params"][0], TableType::Table);
        }
        // fill method params
        switch ($this->method){
            case "hasOne":
            case "belongsTo":
                $this->fillHasBelongsToAtt($relationInfo["params"]);
                break;
            case "hasMany":
                $this->fillHasBelongsToAtt($relationInfo["params"], true);
                break;
            case "belongsToMany":
                $this->fillBelongsToManyAtt($relationInfo["params"], $forceNew);
                break;
            case "morphsOne":
                $this->fillMorphAtt($relationInfo["params"], 0,$forceNew);
                break;
            case "hasOneThroughMorph":
                $this->fillMorphAtt($relationInfo["params"], 1, $forceNew, through:true);
                break;
            case "morphsMany":
                $this->fillMorphAtt($relationInfo["params"], 0, $forceNew, true);
                break;
            case "hasManyThroughMorph":
                $this->fillMorphAtt($relationInfo["params"], 1, $forceNew, true, through:true);
                break;
            case "hasOneThroughManyMorphs":
                $this->fillMorphAtt($relationInfo["params"], 0, $forceNew, manyMorphs:true);
                break;
            case "hasManyThroughManyMorphs":
                $this->fillMorphAtt($relationInfo["params"], 0, $forceNew, true, true);
                break;
            default:
                throw new CustomError("Relationship method '{$this->method}' is not valid in table '{$this->owner->name}'.");
                break;
        }
    }
    #endregion

    #region property fillers
    /**
     * sets values for $pivot and $parameters attributes if method is a morph method
     *
     * @param array $params
     * @param boolean $hasMany make function name plural?
     * @return void
     */
    private function fillHasBelongsToAtt(array &$params, bool $hasMany = false){
        $this->parameters["fnName"] = isset($params[1]) ? $params[1]:
        (!$hasMany ? SO::getSingular($this->related->name) : $this->related->name);
        $this->parameters["foreign"] = isset($params[2]) ? $params[2] :
        SO::getSingular($this->related->name) . "_id";
    }

    /**
     * sets values for $pivot and $parameters attributes if method is a morph method
     *
     * @param array $params
     * @param boolean $forceNew override default behavior of getting the pivot info from existing file
     * @return void
     */
    private function fillBelongsToManyAtt(array &$params, bool $forceNew){
        $this->parameters["fnName"] = isset($params[1]) ? $params[1] : $this->related->name;
        $ownerForeign = (isset($params[2])) ? $params[2] : "";
        $relatedForeign = (isset($params[3])) ? $params[3] : "";
        $tableName = (isset($params[4])) ? $params[4] : "";
        $this->pivot = PivotDefinition::toBeWritten(
            $this->owner->name,
            $this->related->name,
            $ownerForeign,
            $relatedForeign,
            $tableName,
            $forceNew
        );
    }

    /**
     * sets values for $pivot and $parameters attributes if method is a morph method
     *
     * @param array $params
     * @param integer $start the starting index for relevant values 
     * (eg : morphsMany doesn't need table name so $start = 0; 
     * whilst hasManyThroughMorph does so $start = 1)
     * @param boolean $forceNew override default behavior of getting the pivot info from existing file
     * @param boolean $many make function name plural?
     * @param boolean $manyMorphs pivot table has 4 primary columns? (prefix1_type and _id, prefix2_type and _id)
     * @param boolean $through use the related->name as the singularTableName?
     * @return void
     */
    private function fillMorphAtt(
        array &$params,
        int $start,
        bool $forceNew,
        bool $many = false,
        bool $manyMorphs = false,
        bool $through = false
    ){
        $singularFirst = $through ? ["related", "owner"] : ["owner", "related"];
        $followOwner = $through ? ["otherMorphed", "morphed"] : ["morphed", "otherMorphed"];
        $this->parameters["ownerPrefix"] = $ownerPrefix = $params[$start++];
        $this->parameters["relatedPrefix"] = $relatedPrefix = $params[$start++];
        $this->parameters["pivotTableName"] = $tableName = (isset($params[$start])) ? $params[$start++]:
        SO::getPivotName($ownerPrefix, $relatedPrefix);
        $this->parameters["fnName"] = isset($params[$start]) ? $params[$start++]:
        (!$many ? SO::getSingular($relatedPrefix) : SO::getPlural($relatedPrefix));
        // either owner has type then is morphed or both have type and the order doesn't matter
        $morphedTypeLength = (isset($params[$start])) ? $params[$start++] : 255; 
        $otherMorphedTypeLength = (isset($params[$start])) ? $params[$start] : 255;
        $type = $manyMorphs ? Morph::Many : Morph::One;
        $this->pivot = MorphPivotDefinition::toBeWritten(
            $type,
            ${$singularFirst[0] . "Prefix"},
            ${$singularFirst[1] . "Prefix"},
            ${$followOwner[0] . "TypeLength"},
            ${$followOwner[1] . "TypeLength"},
            $this->{$singularFirst[0]}->name,
            $tableName,
            $forceNew
        );
    }
    #endregion

    #region write methods
    /**
     * insert at $offset into $modelFileContents the defining function of the relationship
     *
     * @param array $modelFileContents
     * @param integer $offset
     * @return void
     */
    public function addRelation(array &$modelFileContents, int $offset)
    {
        $relationFunction = [];
        $param2ValueProvider = "owner";
        $morphTemplateFile = "hasThroughMorphTemplate.php";
        $relationMethod = $this->method;
        $fnName = $this->parameters["fnName"];
        $includes = [];
        // fill template parameters
        switch ($this->method) {
            case "hasOne":
                $param2ValueProvider = "owner";
                goto hasMany; // line 177
            case "belongsTo":
                $param2ValueProvider = "related";
            case "hasMany":
                hasMany:
                $related = $this->related->modelName;
                $includes["use App\\Models\\$related;"] = 0;
                $param1 = $this->parameters["foreign"];
                $param2 = $this->{$param2ValueProvider}->primaryKeyName;
                include "relationTemplate.php";
                break;
            case "belongsToMany":
                $related = $this->related->modelName;
                $includes["use App\\Models\\$related;"] = 0;
                $pivotTableName = $this->pivot->name;
                $ownerPivotColumn = $this->pivot->getForeignOfTable($this->owner->name);
                $relatedPivotColumn = $this->pivot->getForeignofTable($this->related->name);
                $ownerPrimary = $this->owner->primaryKeyName;
                $relatedPrimary = $this->related->primaryKeyName;
                include "manyToManyTemplate.php";
                break;
            case "morphsOne":
            case "morphsMany":
            case "hasOneThroughManyMorphs":
            case "hasManyThroughManyMorphs":
                $morphTemplateFile = "morphRelationTemplate.php";
                goto skipRelated; // line 204
            case "hasOneThroughMorph":
            case "hasManyThroughMorph":
                $related = $this->related->modelName;
                $includes["use App\\Models\\$related;"] = 0;
                skipRelated:
                $pivotTableName = $this->parameters["pivotTableName"];
                $ownerPrefix = $this->parameters["ownerPrefix"];
                $relatedPrefix = $this->parameters["relatedPrefix"];
                include $morphTemplateFile;
                $includes["use DatabaseDefinition\\Src\\Relationship\\CustomMorph;"] = 0;
                break;
        }

        array_splice($modelFileContents, $offset, 0, $relationFunction);
        //$offset += 4;
        return $includes;
    }

    public function createPivot(bool $verbose = false){
        // no pivots to create
        if (!isset($this->pivot)){
            return;
        }
        if (!TableParser::tableExists($this->pivot->name, TableType::Pivot)){
            $this->pivot->write($verbose);
        }
    }
    #endregion

    #region display
    public function display(){
        echo SO::getMethod([
            "method" => PURPLE . BOLD . $this->method . QUIT,
            "params" => array_map(
                fn($key, $value) => "$key:$value",
                array_keys($this->parameters),
                $this->parameters
        )], false) . PHP_EOL;
    }
    #endregion
}
