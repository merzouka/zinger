<?php

namespace DatabaseDefinition\Src\Table;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Helpers\DefinitionHelper as DH;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Relationship\Relation;
use DatabaseDefinition\Src\Table\Column;
use DatabaseDefinition\Src\TableFactory;
use DatabaseDefinition\Src\TableType;
use Error;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

use function PHPUnit\Framework\fileExists;

class TableDefinition extends Table
{
    #region attributes
    public ?string $modelName;
    public string $primaryKeyName;
    private ?array $relations;
    public array $fillable;
    public array $includes;
    #endregion


    public function __construct(?array $fileContents, bool $doRelations = false)
    {
        parent::__construct($fileContents);
        // setting properties
        $this->modelName = DH::getInfoByPart("MODEL", $fileContents);
        $this->relations = [];
        if ($doRelations){
            foreach (DH::getInfoByPart("RELATIONS", $fileContents) as $relationInfo){
                $this->relations[] = new Relation($relationInfo, $this);
            }
        }
        // adding columns
        $i = 0;
        foreach (DH::getInfoByPart("COLUMNS", $fileContents) as $column){
            if ($column["type"]["method"] !== SO::translateType($column["type"]["method"])){
                $this->primaryKeyName = $column["name"];
            }
            if ($column["fillable"]){
                $this->fillable[] = $column["name"];
            }
            $this->columns[] = new Column($column, $i);
            $i++;
        }
        unset($fileContents);
    }

    #region main functions
    /**
     * seeds the table with the specified number of rows
     *
     * @param string $fullModelName
     * @return void
     */
    public function seed(int $number = 0, string $fullModelName = ""){
        parent::seed($number);
        if ($fullModelName === ""){
            $fullModelName = "App\\Models\\". $this->modelName;
        }
        call_user_func([$fullModelName, "factory"])->count($this->numberOfRecords)->create();
    }

    /**
     * returns formatted data array
     *
     * @param JsonResource $obj the resource
     * @return void
     */
    public function getResourceArray(JsonResource $obj){
        // get the object contents as an associative array
        $modelValues = get_object_vars($obj);
        $result = [];
        // if model (one instance) wrap in array
        if (get_class($modelValues["resource"]) !== "Illuminate\\Database\\Eloquent\\Collection"){
            $modelValues["resource"] = [$modelValues["resource"]];
        }
        // create return array
        foreach ($modelValues["resource"] as $resource){
            foreach ($this->columns as $column){
                if ($column->jsonName !== null){
                    $row[$column->jsonName] = $resource[$column->name];
                }
            }
            $result[] = $row;
        }
        return (count($result) == 1 ? $result[0] : $result);
    }

    /**
     * instantiates a new model
     *
     * @return Model
     */
    public function model() : Model{
        $modelClass = "App\\Models\\" . $this->modelName;
        return new $modelClass();
    }
    #endregion

    #region writing methods
    /**
     * adds fillable and primaryKey property to model file
     *
     * @param array $modelFileContents
     * @param integer $offset = index of START_ATTRIBUTES
     * @return integer
     */
    public function addFillableAndPrimary(array &$modelFileContents, int $offset) : int{
        $attEnd = DH::arrayFindFromOffset("END_ATTRIBUTES", $modelFileContents, $offset);
        $fillable = "[" . implode(", ", array_map(fn($str) => "'$str'", $this->fillable)) . "]";
        $arr = [
            "\tprotected " . '$primaryKey = ' . "'{$this->primaryKeyName}';",
            "\tprotected " . '$fillable = ' . "$fillable;"
        ];
        array_splice($modelFileContents, $offset + 1, $attEnd - $offset - 1, $arr);
        return $attEnd;
    }

    /**
     * add model dependencies to file
     *
     * @param array $modelFileContents
     * @param integer $includeStart the index of "namespace" keyword
     * @param integer $includeEnd the index of "class" keyword
     * @return void
     */
    public function addIncludes(
        array &$modelFileContents,
        int $includeStart,
        int $includeEnd
    ){
        $oldIncludes = array_slice($modelFileContents, $includeStart + 1, $includeEnd - $includeStart - 1);
        $oldIncludes = array_flip($oldIncludes);
        $newIncludes = array_keys(array_merge([
            "" => 0,
            "include_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'constants.php';" => 0,
            "include AUTOLOADER;" => 0,
        ], $oldIncludes, $this->includes
        ));
        array_splice($modelFileContents, $includeStart + 1, $includeEnd - $includeStart - 1, $newIncludes);
    }

    /**
     * fills the related model file with relevant info
     *
     * @return void
     */
    public function addModelInfo(){
        // getting model contents
        $modelPath = ROOT_DIR. "app" . DIRECTORY_SEPARATOR . "Models" . DIRECTORY_SEPARATOR . $this->modelName . ".php";
        if (!file_exists($modelPath)){
            throw new Error("Model '{$this->modelName}' doesn't exist.");
        }
        $file = fopen($modelPath, "r");
        $modelFileContents = explode(PHP_EOL, fread($file, filesize($modelPath)));
        fclose($file);
        try{

            $includeStart = DH::arrayFindFromOffset("namespace", $modelFileContents, 0);
            $includeEnd = DH::arrayFindFromOffset("class", $modelFileContents, $includeStart + 1);

            $attStart = DH::arrayFindFromOffset("START_ATTRIBUTES", $modelFileContents, $includeEnd + 1);
            $attEnd = $this->addFillableAndPrimary($modelFileContents, $attStart);

            $relationStart = DH::arrayFindFromOffset("START_RELATIONSHIPS", $modelFileContents, $attEnd + 1);
            $relationEnd = DH::arrayFindFromOffset("END_RELATIONSHIPS", $modelFileContents, $relationStart + 1);
        } catch (Error $e){
            throw new Error($e->getMessage() . " in model '{$this->modelName}'.");
        }

        $relationFunctions = [];
        $this->includes = [];
        foreach ($this->relations as $relation){
            //$i = 0;
            $this->includes = array_merge($this->includes, $relation->addRelation($relationFunctions, 0));
        }
        array_splice($modelFileContents, $relationStart + 1, $relationEnd - $relationStart - 1, $relationFunctions);
        $this->addIncludes($modelFileContents, $includeStart, $includeEnd);
        $file = fopen($modelPath, "w");
        fwrite($file, implode(PHP_EOL, $modelFileContents));
        fclose($file);
    }

    public function createPivots(){
        foreach ($this->relations as $relation){
            $relation->createPivot();
        }
    }
    #endregion

}

TableFactory::createTable("hello", TableType::Table);