<?php

namespace DatabaseDefinition\Src\Pivot;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Alias\AliasHandler;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Helpers\TableParser;
use DatabaseDefinition\Src\Pivot\PivotTable;
use DatabaseDefinition\Src\Table\ForeignKey;
use DatabaseDefinition\Src\TableFactory;
use DatabaseDefinition\Src\TableType;
use Illuminate\Support\Facades\DB;

#ToDo override fillIdsFromRelated
/**
 * responsible for creating and using morph pivot tables
 */
class MorphPivotDefinition extends PivotTable
{
    #region properties
    public array $primaryColumns;
    #endregion

    #region constructors
    private function __construct(?array &$fileContents = null, bool $doRelations = false)
    {
        if ($fileContents !== null) {
            parent::__construct($fileContents, "primaryColumns", $doRelations);
        }
    }

    /**
     * a helpers used to add a id column to the object using $prefix 
     *
     * @param string $prefix
     * @return void
     */
    private function addIdPrimary(string $prefix){
        $this->primaryColumns[] = (PrimaryKey::toBeWritten($prefix . "_id", [
            "json_name" => "null",
            "type" => ["method" => "bigInteger", "params" => [$prefix . "_id"]],
            "properties" => ["unsigned"]
        ]));
    }

    /**
     * a helpers used to add a type column to the object using $prefix and set its string length 
     * to $length.
     *
     * @param string $prefix
     * @param integer $length
     * @return void
     */
    private function addTypePrimary(string $prefix, int $length){
        $this->primaryColumns[] = (PrimaryKey::toBeWritten($prefix . "_type", [
            "json_name" => "null",
            "type" => ["method" => "string", "params" => [$prefix . "_type", $length]],
            "properties" => []
        ]));
    }

    /**
     * instantiates a new object to be written or returns table object if it exists
     *
     * @param Morph $type the type of table used to tell if the table has foreign keys or not
     * @param string $singularPrefix the prefix for the singular table if type == one else the order doesn't matter
     * @param string $morphedPrefix the prefix for the many tables
     * @param integer $typeLength1 the length of the $prefix_type table used in type declaration (string($typeLength))
     * @param integer $typeLength2 same as $typeLength1
     * @param string $singularTableName the name of the singular table in a oneMorph relationship used to get the type for the {$prefix1_id} column
     * @param string $tableName the name of the created table
     * @param bool $forceNew forcefully create new instance using info
     * @return MorphPivotDefinition
     */
    public static function toBeWritten(
        Morph $type,
        string $singularPrefix,
        string $morphedPrefix,
        int $singularTypeLength = 255,
        int $morphedTypeLength = 255,
        string $singularTableName = "",
        string $tableName = "",
        bool $forceNew = false
    ): MorphPivotDefinition {
        $tableName = $tableName === "" ? SO::getPivotName($singularPrefix, $morphedPrefix) : $tableName;
        // this conditional statement is used to set the column length for singularTable if
        // the pivot table has already been set
        if (TableParser::tableExists($tableName, TableType::Pivot) && !$forceNew) {
            return (TableFactory::createTable($tableName, TableType::Pivot))
            ->updateLength($singularPrefix. "_type", $singularTypeLength);
        }

        $obj = new MorphPivotDefinition();
        $obj->name = $tableName;
        // one morph and table name provided
        if ($type == Morph::One && $singularTableName !== "") {
            $primaryColumn = parent::getPrimaryColumn(TableFactory::createTable($singularTableName, TableType::Table));
            $p = PrimaryKey::toBeWritten($singularPrefix . "_id", $primaryColumn->columnInfo);
            if (!in_array("unsigned", $p->properties)){
                $p->properties[] = "unsigned";
            }
            $obj->primaryColumns[] = $p;
            // add foreign key to singular table
            $obj->foreignKeys[] = ForeignKey::fromParams($singularPrefix . "_id", $primaryColumn->name, $singularTableName, "cascade", "cascade");
        }
        // one morph and table name not provided
        else {
            // no foreign keys to be added
            $obj->foreignKeys = [];
            $obj->addIdPrimary($singularPrefix);
            // many morph => both have type and id columns
            if ($type == Morph::Many) {
                $obj->addTypePrimary($singularPrefix, $singularTypeLength);
            }
        }
        // adding identifying columns for related tables
        $obj->addIdPrimary($morphedPrefix);
        $obj->addTypePrimary($morphedPrefix, $morphedTypeLength);
        return $obj;
    }

    /**
     * returns an new instance to be later used
     *
     * @param string $tableName
     * @return MorphPivotDefinition
     */
    public static function toBeUsed(?array &$fileContents, bool $doRelations = false): MorphPivotDefinition
    {
        return new MorphPivotDefinition($fileContents, $doRelations);
    }
    #endregion 

    #region helpers
    /**
     * returns an array containing random lengths
     *
     * @param integer $upperBound maximum value
     * @param integer $numberOfValues number of values to generate
     * @return array
     */
    private static function getLengths(int $upperBound, int $numberOfValues): array
    {
        $arr = [];
        for ($i = 0; $i < $numberOfValues - 1; $i++) {
            $arr[] = rand(0, $upperBound);
        }
        sort($arr);
        $lengths = [];
        $i = 0;
        foreach ($arr as $value) {
            $lengths[] = $value - $i;
            $i = $value;
        }
        $lengths[] = $upperBound - $i;
        return $lengths;
    }

    /**
     * returns an array [key, value]
     *
     * @param array $array array containing a single element
     * @return array
     */
    private static function getKeyValue(array $array): array
    {
        return [array_keys($array)[0], array_values($array)[0]];
    }

    /**
     * resets the length of $typeColumn type to $length in a previously created table
     *
     * @param string $typeColumn
     * @param integer $length
     * @return MorphPivotDefinition
     */
    private function updateLength(string $typeColumn, int $length) : MorphPivotDefinition{
        foreach ($this->primaryColumns as &$primary){
            if ($primary->name === $typeColumn){
                $primary->type["params"][1] = $length;
            }
        }
        return $this;
    }
    #endregion

    #region general methods
    public function __toString()
    {
        $this->childPrimaryPropertyName = "primaryColumns";
        return parent::__toString();
    }

    /**
     * returns an array [prefix => array([type => id])]
     *
     * @param boolean $rehydrate true => get fresh values
     * @return void
     */
    public function fillIdsFromRelated(bool $rehydrate = false)
    {
        if (isset($this->tableSampleIds) && !$rehydrate) {
            return;
        }
        $aliasesForPrefixes = AliasHandler::getArrayByNameOrGENERAL($this->name);
        foreach ($aliasesForPrefixes as $prefix => $aliases) {
            $aliasesToAdd = array_rand($aliases, rand(1, count($aliases)));
            $result = [];
            $lengths = static::getLengths($this->numberOfRecords, count($aliasesToAdd));
            $i = 0;
            foreach ($aliasesToAdd as $alias) {
                $result = array_merge($result, static::getValuesFromTable($aliases[$alias], $alias, $lengths[$i]));
                $i++;
            }
            $this->tableSampleIds[$prefix] = shuffle($result);
        }
    }

    /**
     * returns an array of [type => id] where type is the alias of the table and id is a random
     * id form table
     *
     * @param string $tableName 
     * @param string $tableAlias
     * @param integer $numberOfValues
     * @return void
     */
    private static function getValuesFromTable(string $tableName, string $tableAlias, int $numberOfValues)
    {
        $table = TableFactory::createTable($tableName, TableType::Table);
        $primaryColumn = parent::getPrimaryColumn($table);
        $values = call_user_func_array(["App\\Models\\" . $table->modelName, "orderByRaw"], ["RAND()"])->take($numberOfValues)->pluck($primaryColumn->name);
        return array_map(fn ($value) => [$tableAlias => $value], $values);
    }

    public function getFactoryArray(array $arr = null): array
    {
        if ($arr === null) {
            $arr = [];
        }
        $predefinedKeys = array_keys($arr);
        $result = [];
        foreach ($this->primaryColumns as $primary) {
            if (in_array($primary->name, $predefinedKeys)) {
                continue;
            }
            [$prefix, $type] = explode("_", $primary->name);
            $array = static::getKeyValue($this->tableSampleIds[$prefix][array_rand($this->tableSampleIds[$prefix])]);
            $result[$primary->name] = $type == "id" ? $array[1] : $array[0];
        }

        return array_merge($result, parent::getFactoryArray($arr));
    }

    public function seed(int $number = 0, bool $rehydrate = false)
    {
        parent::seed($number, $rehydrate);
        $result = [];
        for ($i = 0; $i < $this->numberOfRecords; $i++) {
            $x = [];
            foreach ($this->tableSampleIds as $prefix => $values) {
                [$x[$prefix . "_type"], $x[$prefix . "_id"]] = static::getKeyValue($values[$i]);
            }
            foreach ($this->columns as $column) {
                $x[$column->name] = $column->faker();
            }
            $result = array_merge($result, [$x]);
        }
        DB::table($this->name)->insert($result);
    }

    #endregion

}
