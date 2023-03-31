<?php


namespace DatabaseDefinition\Src\Pivot;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Table\TableDefinition;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Helpers\TableParser;
use DatabaseDefinition\Src\Table\ForeignKey;
use DatabaseDefinition\Src\Pivot\PivotTable;
use DatabaseDefinition\Src\TableFactory;
use DatabaseDefinition\Src\TableType;
use Error;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class PivotDefinition extends PivotTable
{

    public array $primaryKeys;

    #region constructors
    private function __construct(?array &$fileContents = null)
    {
        if ($fileContents !== null) {
            parent::__construct($fileContents, "primaryKeys");
        }
    }

    /**
     * instantiate a new object to be later written to a file or returns table object if it exists
     *
     * @param string $tableName1
     * @param string $tableName2
     * @param string $primaryPivotName1 the name of the primary column 1 in pivot
     * @param string $primaryPivotName2 the name of the primary column 2 in pivot
     * @param string $pivotName the name of the table
     * @param bool $forceNew forcefully create new instance using info
     * @return PivotDefinition
     */
    public static function toBeWritten(
        string $tableName1,
        string $tableName2,
        string $primaryPivotName1 = "",
        string $primaryPivotName2 = "",
        string $tableName = "",
        bool $forceNew = false
    ): PivotDefinition {
        $tableName = $tableName !== "" ? $tableName : SO::getPivotName($tableName1, $tableName2, false);
        if (TableParser::tableExists($tableName, TableType::Pivot) && !$forceNew) {
            return TableFactory::createTable($tableName, TableType::Pivot);
        }
        $obj = new PivotDefinition();
        // either pivot name or join two table names in alphabetical order
        $obj->name = $tableName;
        // retrieve the info of the two primary columns
        $table1 = TableFactory::createTable($tableName1, TableType::Table);
        $table2 = TableFactory::createTable($tableName2, TableType::Table);
        // get the primary column in table one of type *Increments or id
        $primaryColumn1 = parent::getPrimaryColumn($table1);
        // same for table 2
        $primaryColumn2 = parent::getPrimaryColumn($table2);
        // get the names of the primary pivot columns
        $primaryPivotName1 = $primaryPivotName1 === "" ? SO::getSingular($tableName1) . "_id" : $primaryPivotName1;
        $primaryPivotName2 = $primaryPivotName2 === "" ? SO::getSingular($tableName2) . "_id" : $primaryPivotName2;
        // set primary keys attribute
        if (!in_array("unsigned", $primaryColumn1->columnInfo["properties"])) {
            $primaryColumn1->columnInfo["properties"][] = "unsigned";
        }
        if (!in_array("unsigned", $primaryColumn2->columnInfo["properties"])) {
            $primaryColumn2->columnInfo["properties"][] = "unsigned";
        }
        $obj->primaryKeys = [
            PrimaryKey::toBeWritten($primaryPivotName1, $primaryColumn1->columnInfo),
            PrimaryKey::toBeWritten($primaryPivotName2, $primaryColumn2->columnInfo),
        ];
        // set foreign keys text
        $obj->foreignKeys = [
            ForeignKey::fromParams($primaryPivotName1, $primaryColumn1->name, $tableName1, "cascade", "cascade"),
            ForeignKey::fromParams($primaryPivotName2, $primaryColumn2->name, $tableName2, "cascade", "cascade")
        ];
        return $obj;
    }

    /**
     * instantiate a new object to be later used
     *
     * @param string $tableName
     * @return PivotDefinition
     */
    public static function toBeUsed(?array &$fileContents): PivotDefinition
    {

        return new PivotDefinition($fileContents);
    }
    #endregion

    #region helpers
    /**
     * gets some sample ids to be later used in the seeding
     *
     * @param TableDefinition $table
     * @param string $primaryColumn
     * @return void
     */
    public function fillIdsFromTable(string $columnName, TableDefinition $table, string $primaryColumn)
    {
        $this->tableSampleIds[$columnName] = call_user_func_array(["App\\Models\\" . $table->modelName, "orderByRaw"], ["RAND()"])->take($this->numberOfRecords)->pluck($primaryColumn);
    }
    #endregion

    #region general methods

    public function defineTable(Blueprint &$table)
    {
        parent::defineTable($table);
        $table->primary(array_map(fn ($column) => $column->name, $this->primaryKeys));
    }


    public function fillIdsFromRelated(bool $rehydrate = false)
    {
        if (isset($this->tableSampleIds) && !$rehydrate) {
            return;
        }
        foreach ($this->foreignKeys as $foreignKey) {
            $table = new TableDefinition($foreignKey["on"]);
            $primaryColumn = static::getPrimaryColumn($table);
            $this->fillIdsFromTable($foreignKey["foreign"], $table, $primaryColumn->name);
        }
    }

    public function getFactoryArray(?array $arr = null): array
    {
        if ($arr === null) {
            $arr = [];
        }
        $predefinedKeys = array_keys($arr);
        $result = [];
        foreach ($this->tableSampleIds as $primary => $values) {
            if (in_array($primary, $predefinedKeys)) {
                continue;
            }
            $result[$primary] = $values[array_rand($values)];
        }
        return array_merge($result, parent::getFactoryArray($arr));
    }

    public function seed(int $number = 0, bool $rehydrate = false)
    {
        parent::seed($number, $rehydrate);
        $result = [];
        for ($i = 0; $i < $this->numberOfRecords; $i++) {
            $x = [];
            foreach ($this->tableSampleIds as $key => $value) {
                $x[$key] = $value;
            }
            foreach ($this->columns as $column) {
                $x[$column->name] = $column->faker();
            }
            $result = array_merge($result, [$x]);
        }
        DB::table($this->name)->insert($result);
    }

    /**
     * return file contents
     *
     * @return string
     */
    public function __toString()
    {
        $foreignKeyText = implode(", ", array_map(fn ($foreignKey) => (string)$foreignKey, $this->foreignKeys));
        $primaryKeyText = implode(PHP_EOL, array_map(fn ($primaryKey) => (string)$primaryKey, $this->primaryKeys));
        return parent::getString($primaryKeyText, $foreignKeyText);
    }

    public function getForeignOfTable(string $tableName): string
    {
        foreach ($this->foreignKeys as $foreignKey) {
            if ($foreignKey->keyInfo["on"] === $tableName) {
                return $foreignKey->keyInfo["foreign"];
            }
        }
        throw new Error("Foreign Column for '$tableName' doesn't exist.");
    }
    #endregion

}
