<?php

namespace DatabaseDefinition\Src\Pivot;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";

use DatabaseDefinition\Src\Console\ConsoleOutputFormatter;
use DatabaseDefinition\Src\Console\OutputType;
use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\DefinitionHelper as DH;
use DatabaseDefinition\Src\Table\Column;
use DatabaseDefinition\Src\Table\Table;
use DatabaseDefinition\Src\Table\TableDefinition;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Helpers\TableParser;
use DatabaseDefinition\Src\TableType;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class PivotTable extends Table{

    #region properties
    protected string $childPrimaryPropertyName;
    public array $tableSampleIds;
    #endregion

    #region constructors
    public function __construct(?array &$fileContents, string $primaryPropertyName, bool $doRelations = false)
    {
        $this->childPrimaryPropertyName = $primaryPropertyName;
        parent::__construct($fileContents, $doRelations);
        $columns = DH::getInfoByPart("COLUMNS", $fileContents);
        // adding primary keys
        $this->{$this->childPrimaryPropertyName} = array_values(array_map(fn ($column) => PrimaryKey::toBeUsed($column), 
        $columns["PRIMARY"]));
        // adding columns
        $i = 0;
        foreach ($columns as $key => $column){
            if ($key === "PRIMARY"){
                continue;
            }
            $this->columns[] = new Column($column, $i);
            $i++;
        }
    }
    #endregion

    #region add primary keys

    /**
     * adds primary keys to table
     *
     * @param Blueprint $table
     * @return void
     */
    public function addPrimaryKeys(Blueprint &$table) : void{
        
        foreach ($this->{$this->childPrimaryPropertyName} as $primary){
            try{
                $primary->addInfo($table);
            } catch (CustomError $e){
                throw new CustomError($e->getMessage() . " In Pivot Table " . $this->name);
            }
        }
    }

    #endregion

    #region helpers
    /**
     * get the primary column (with auto_increment) from a given table
     *
     * @param TableDefinition $table
     * @return Column
     */
    protected static function getPrimaryColumn(TableDefinition $table) : Column{
        $primaryColumn = (array_filter($table->columns, fn($column) => 
        SO::translateType($column->columnInfo["type"]["method"]) !==
        $column->columnInfo["type"]["method"])
        )[0];
        return $primaryColumn;
    }
    #endregion

    #region general methods

    public function defineTable(Blueprint &$table)
    {
        parent::defineTable($table);
        $this->addPrimaryKeys($table);
    }

    /**
     * returns a string of the file contents
     *
     * @param string $columnText the columns
     * @param string $foreignKeyText the foreign keys if == "" the the file part is not added
     * @return string
     */
    protected function getString(string $columnText, string $foreignKeyText = "") : string{
        $foreignKeyText = $foreignKeyText === "" ? "" : PHP_EOL ."FOREIGN_KEYS: ". $foreignKeyText;
        return 
"EXCLUDE: model
NAME: {$this->name}
RECORDS: 0{$foreignKeyText}
HAS_TIMESTAMPS: false
COLUMNS:
{$columnText}
";
    }

    /**
     * gets sample ids from tables in relationship with for later seeding
     *
     * @param boolean $rehydrate true => get fresh values
     * @return void
     */
    public function fillIdsFromRelated(bool $rehydrate = false){}

    
    /**
     * return an instance of query builder to use for queries
     *
     * @return Builder
     */
    public function query() : Builder{
        return DB::table($this->name);
    }

    public function __toString()
    {
        $columns = array_merge($this->{$this->childPrimaryPropertyName}, $this->columns ?? []);
        $primaryKeyText = implode(PHP_EOL, array_map(fn ($column) => (string)$column, $columns));
        $foreignKeyText = implode(", ", array_map(fn ($foreignKey) => (string)$foreignKey, $this->foreignKeys ?? []));
        return $this->getString($primaryKeyText, $foreignKeyText);
    }
    
    /**
     * create the pivot file
     *
     * @return void
     */
    public function write(bool $verbose = false){
        if (TableParser::tableExists($this->name, TableType::Pivot)){
            $verbose = false;
        }
        $filePath = TableParser::getPath(TableType::Pivot). $this->name .TABLE_FILE_SUFFIX;
        $file = fopen($filePath, "w");
        fwrite($file, (string)$this);
        fclose($file);
        if ($verbose){
            (new ConsoleOutputFormatter(OutputType::Created, "new pivot [\e[1m$filePath\e[0m]."))->out();
        }
    }

    public function seed(int $number = 0, bool $rehydrate = false){
        parent::seed($number);
        // get some sample ids
        $this->fillIdsFromRelated($rehydrate); 
    }

    #endregion

    #region display
    protected function fillLengths(
        string $attName,
        string $prepareMethod,
        array $headerArray,
        bool $hasPrimary = true
    ){
        parent::fillLengths($attName, $prepareMethod, $headerArray);
        if (!$hasPrimary){
            return;
        }
        foreach ($this->{$this->childPrimaryPropertyName} as $primary){
            $this->lengths = array_map(
                fn($v1, $v2) => max($v1, $v2),
                $this->lengths,
                $primary->prepareRowColumns()
            );
        }
    }

    public function displayColumns(bool $updateLengths = false)
    {
        echo BOLD . "COLUMNS:" . QUIT . PHP_EOL;
        $this->fillLengths("columns", "prepareRowColumns", parent::TABLE_COLUMNS);
        $this->printHeader(parent::TABLE_COLUMNS);
        foreach ($this->{$this->childPrimaryPropertyName} as $primary){
            $primary->display($this->lengths);
        }
        if (isset($this->columns)){
            parent::displayColumns();
        }
        SO::printSeparator($this->lengths);
    }

    public function display()
    {
        parent::display();
        $this->displayColumns();
    }
    #endregion

}
