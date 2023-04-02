<?php

namespace DatabaseDefinition\Src\Table;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Error\CustomError;
use DatabaseDefinition\Src\Helpers\DefinitionHelper as DH;
use DatabaseDefinition\Src\Helpers\StringOper as SO;
use DatabaseDefinition\Src\Interfaces\TableInterface;
use DatabaseDefinition\Src\Relationship\Relation;
use Illuminate\Database\Schema\Blueprint;

class Table implements TableInterface{

    #region attributes
    public string $name;
    public int $numberOfRecords;
    public bool $hasTimestamps;
    public array $commands;
    public ?array $foreignKeys;
    protected ?array $relations;
    public array $columns;
    public array $lengths;
    #endregion

    #region constants
    public const TABLE_COLUMNS = [
        "name", "json name", "fillable?", "faker", "type", "properties"
    ];

    public const FOREIGN_COLUMNS = [
        "foreign", "references", "on", "onUpdate", "onDelete"
    ];

    public const INFO_COLUMNS = [
        "name", "#records", "timestamps?"
    ];
    #endregion

    #region constructor
    public function __construct(?array &$fileContents, bool $doRelations = false)
    {
        // setting properties
        $this->name = DH::getInfoByPart("NAME", $fileContents);
        $this->numberOfRecords = DH::getInfoByPart("RECORDS", $fileContents);
        $this->hasTimestamps = DH::getInfoByPart("HAS_TIMESTAMPS", $fileContents);
        $this->commands = DH::getInfoByPart("EXCLUDE", $fileContents);
        // adding foreign keys
        $foreignKeys = DH::getInfoByPart("FOREIGN_KEYS", $fileContents);
        if ($foreignKeys !== null){
            foreach ($foreignKeys as $key){
                $this->foreignKeys[] = ForeignKey::fromArray($key);
            }
        } else $this->foreignKeys = null;
        $this->relations = [];
        if ($doRelations){
            foreach (DH::getInfoByPart("RELATIONS", $fileContents) as $relationInfo){
                $this->relations[] = new Relation($relationInfo, $this);
            }
        }
    }
    #endregion

    #region execute commands
    /**
     * parses the $output and adds colors to it according to COMPONENT_COLOR_CODING
     *
     * @param string $output
     * @return array
     */
    private function parseOutput(array $output) : array{
        $formattedOutput = array_map(function ($output){
            foreach(COMPONENT_COLOR_CODING as $component => $color){
                if (str_contains($output, $component)){
                    $output = $color . $output . "\e[0m" . PHP_EOL;
                    break;
                }
            }
            return $output;
        }, $output);
        return $formattedOutput;
    }

    /**
     * a function to execute all table commands.
     * this function will create by default : model, controller, migration, resource, resource collection, factory, seeder
     * except if excluded, Note : if the model is excluded only the migration is created. 
     * If verbose the output is displayed to the console.
     * @return void
     */
    public function exec(bool $verbose = false){
        $commandOutput = [];
        chdir(ROOT_DIR);
        foreach ($this->commands as $command){
            echo "hello". PHP_EOL;
            if ($verbose){
                exec($command, $commandOutput);
                foreach ($this->parseOutput($commandOutput) as $output){
                    echo $output;
                }
            }
            else { exec($command); }
        }
        chdir(__DIR__);
    }
    #endregion

    #region add foreign keys
    

    /**
     * adds foreign keys to $table
     *
     * @param Blueprint $table
     * @return void
     */
    public function addForeignKeys(Blueprint &$table) : void{
        if ($this->foreignKeys === null){
            return;
        }
        foreach($this->foreignKeys as $foreignKey){
            $result = $foreignKey->addForeignKey($table);
            if ($result !== null){
                throw new CustomError($result . " In Foreign key definition In Table {$this->name}");
            } 
        }

    }
    #endregion

    #region add timestamps
    public function addTimestamps(Blueprint &$table){
        if (!$this->hasTimestamps){
            return;
        }
        $table->timestamps();
    }
    #endregion

    #region add columns
    public function addColumns(Blueprint &$table){
        if (!isset($this->columns)|| $this->columns === null){
            return;
        }
        try{
            foreach ($this->columns as $column){
                $column->addInfo($table);
            }
        } catch (CustomError $e){
            throw new CustomError($e->getMessage() . " In Table {$this->name}");
        }
    }
    #endregion

    #region general methods
    /**
     * adds all table information to $table
     *
     * @param Blueprint $table
     * @return void
     */
    public function defineTable(Blueprint &$table){
        $this->addForeignKeys($table);
        $this->addColumns($table);
        $this->addTimestamps($table);
    }

    /**
     * returns an array containing the values for the factory, if the $arr is provided its values
     * will be used instead of calling faker, if the faker value of a column is set to null it is
     * not added.
     *
     * @param array|null $arr
     * @return array
     */
    public function getFactoryArray(array $arr = null) : array{
        if ($arr === null){
            $arr = [];
        }
        $result = [];
        $predefinedKeys = array_keys($arr);
        foreach ($this->columns as $column){
            if (!in_array($column->name, $predefinedKeys)){

                $value = $column->faker();
                if ($value === null){
                    continue;
                }
                $value = [$value];
                if (in_array("nullable", $column->columnInfo["properties"])){
                    $value[] = null;
                }
                $result[$column->name] = $value[array_rand($value)];
            }
        }
        return $result;
    }

    /**
     * seeds the table with the specified number of rows
     *
     * @param string $fullModelName
     * @return void
     */
    public function seed(int $number = 0){
        if ($number !== 0){
            $this->numberOfRecords = $number;
        }
    }
    #endregion

    #region display
    /**
     * fills length property
     *
     * @return void
     */
    protected function fillLengths(string $attName, string $prepareMethod, array $headerArray){
        $this->lengths = array_map(fn($str) => strlen($str), $headerArray);
        if (!isset($this->{$attName})){
            return;
        }
        foreach ($this->{$attName} as $element){
            $this->lengths = array_map(
                fn($v1, $v2) => max($v1, $v2),
                $this->lengths,
                $element->{$prepareMethod}()
            );
        }
    }

    /**
     * print table header
     *
     * @param array $headerArray
     * @return void
     */
    protected function printHeader(array $headerArray){
        SO::printSeparator($this->lengths);
        $contents = implode(" | ", array_map(
            fn($str, $length) => SO::addChars($str, $length),
            $headerArray,
            $this->lengths
        ));
        echo "| $contents |" . PHP_EOL;
        SO::printSeparator($this->lengths);
    }

    /**
     * displays the table columns
     *
     * @return void
     */
    public function displayColumns(bool $updateLengths = false){
        echo BOLD . "COLUMNS:" . QUIT . PHP_EOL;
        if (!isset($this->lengths) || $updateLengths){
            $this->fillLengths("columns", "prepareRowColumns", static::TABLE_COLUMNS);
            $this->printHeader(static::TABLE_COLUMNS);
        }
        foreach ($this->columns as $column){
            $column->display($this->lengths);
        }
        SO::printSeparator($this->lengths);
    }

    /**
     * display the table foreign keys
     */
    public function displayForeign(){
        echo BOLD . "FOREIGN KEYS:" . QUIT . PHP_EOL;
        $this->fillLengths("foreignKeys", "prepareKeyColumns", static::FOREIGN_COLUMNS);
        $this->printHeader(static::FOREIGN_COLUMNS);
        foreach ($this->foreignKeys as $foreign){
            $foreign->display($this->lengths);
        }
        SO::printSeparator($this->lengths);
    }

    /**
     * display table commands
     *
     * @return void
     */
    public function displayCommands(){
        echo BOLD . "COMMANDS:" . QUIT . PHP_EOL;
        foreach ($this->commands as $command){
            echo $command . PHP_EOL;
        }
    }

    /**
     * displays table info
     *
     * @param boolean $hasModelName
     * @return void
     */
    public function displayInfo(bool $hasModelName = false){
        echo BOLD . "TABLE INFO:" . QUIT . PHP_EOL;
        $header = static::INFO_COLUMNS;
        $values = [
            $this->name,
            $this->numberOfRecords . "",
            $this->hasTimestamps ? GREEN.TICK.QUIT : RED.CROSS.QUIT
        ];
        if ($hasModelName){
            $header[] = "model";
            $values[] = $this->modelName;
        }
        $this->lengths = array_map(fn($s1, $s2) => max(strlen($s1), strlen($s2)), $header, $values);
        $this->printHeader($header);
        $valuesRow = implode(" | ", array_map(
            fn($str, $length) => SO::addChars($str, $length),
            $values,
            $this->lengths
        ));
        echo "| $valuesRow |" . PHP_EOL;
        SO::printSeparator($this->lengths); 
    }

    /**
     * display tables relations
     *
     * @return void
     */
    public function displayRelations(){
        echo BOLD . "RELATIONSHIPS:" . QUIT . PHP_EOL;
        foreach ($this->relations as $relation){
            $relation->display();
        }
    }

    public function display(){
        $this->displayInfo();
        if (isset($this->commands) && $this->commands !== []) {$this->displayCommands();}
        if (isset($this->foreignKeys)) {$this->displayForeign();}
        if (isset($this->relations) && $this->relations !== []) {$this->displayRelations();}
    }
    #endregion

}
