<?php

namespace DatabaseDefinition\Src\Pivot;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";

use DatabaseDefinition\Src\Console\ConsoleOutputFormatter;
use DatabaseDefinition\Src\Console\OutputType;
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
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;

class PivotTable extends Table{

    private string $childPrimaryPropertyName;
    public array $tableSampleIds;

    public function __construct(?array &$fileContents, string $primaryPropertyName)
    {
        $this->childPrimaryPropertyName = $primaryPropertyName;
        parent::__construct($fileContents);
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
            } catch (SyntaxErrorException $e){
                throw new SyntaxErrorException($e->getMessage() . " In Pivot Table " . $this->name);
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
    
    /**
     * create the pivot file
     *
     * @return void
     */
    public function write(){
        if (TableParser::tableExists($this->name, TableType::Pivot)){
            return;
        }
        $filePath = TableParser::getPath(TableType::Pivot). $this->name .TABLE_FILE_SUFFIX;
        $file = fopen($filePath, "w");
        fwrite($file, (string)$this);
        fclose($file);
        (new ConsoleOutputFormatter(OutputType::Created, "new pivot [\e[1m$filePath\e[0m]."))->out();
    }

    public function seed(int $number = 0, bool $rehydrate = false){
        parent::seed($number);
        // get some sample ids
        $this->fillIdsFromRelated($rehydrate); 
    }

    #endregion
}
