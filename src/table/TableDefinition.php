<?php

namespace DatabaseDefinition\Src\Table;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\DefinitionHelper as DH;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Table\Column;
use DatabaseDefinition\Src\TableFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * responsible for using tables and writing to model files
 */
class TableDefinition extends Table
{
    #region properties 
    public ?string $modelName;
    public string $primaryKeyName;
    public array $fillable;
    public array $includes;
    public array $foreignTableSampleIds;
    #endregion

    #region constructor
    public function __construct(?array $fileContents, bool $doRelations = false)
    {
        parent::__construct($fileContents, $doRelations);
        // setting properties
        $this->modelName = DH::getInfoByPart("MODEL", $fileContents);
        // adding columns
        $i = 0;
        $this->fillable = [];
        $this->primaryKeyName = "";
        $this->columns = [];
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
    }
    #endregion

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

    /**
     * gets some sample ids to be later used in the seeding
     *
     * @param TableDefinition $table
     * @param string $primaryColumn
     * @return void
     */
    public function fillIdsFromTable(string $columnName, TableDefinition $table)
    {
        $this->foreignTableSampleIds[$columnName] = call_user_func_array(["App\\Models\\" . $table->modelName, "orderByRaw"], ["RAND()"])->take($this->numberOfRecords)->pluck($table->primaryKeyName);
    }
    
    public function fillIdsFromRelated(bool $rehydrate = false)
    {
        if (isset($this->foreignTableSampleIds) && !$rehydrate) {
            return;
        }
        $this->foreignTableSampleIds = [];
        foreach ($this->foreignKeys as $foreignKey) {
            $table = TableFactory::createTable($foreignKey["on"]);
            $this->fillIdsFromTable($foreignKey["foreign"], $table);
        }
    }

    public function getFactoryArray(?array $arr = null): array
    {
        $this->fillIdsFromRelated();
        $result = [];
        $predefinedKeys = array_keys($arr);
        // fill columns that reference another table
        foreach ($this->foreignTableSampleIds as $columnName => &$samples){
            if (in_array($columnName, $predefinedKeys)){
                continue;
            }
            $result[$columnName] = $samples[array_rand($samples)];
        }
        return array_merge($result, parent::getFactoryArray($arr));
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
            throw new CustomError("Model '{$this->modelName}' doesn't exist.");
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
        } catch (CustomError $e){
            // the need part doesn't exist in model ...
            throw new CustomError($e->getMessage() . " in model '{$this->modelName}'.");
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

    public function createPivots(bool $verbose = false){
        foreach ($this->relations as $relation){
            $relation->createPivot($verbose);
        }
    }
    #endregion

    #region display
    public function displayInfo(bool $hasModelName = true)
    {
        parent::displayInfo(true);
    }

    public function display()
    {
        parent::display();
        if (isset($this->columns)) {$this->displayColumns(true);}
    }
    #endregion

}
