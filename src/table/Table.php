<?php

namespace DatabaseDefinition\Src\Table;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Helpers\DefinitionHelper as DH;
use DatabaseDefinition\Src\Interfaces\TableInterface;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;

class Table implements TableInterface{

    public string $name;
    public int $numberOfRecords;
    public bool $hasTimestamps;
    public array $commands;
    public ?array $foreignKeys;
    public array $columns;

    #region constructor
    public function __construct(?array &$fileContents)
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
                throw new SyntaxErrorException($result . " In Foreign key definition In Table {$this->name}");
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
        } catch (SyntaxErrorException $e){
            throw new SyntaxErrorException($e->getMessage() . " In Table {$this->name}");
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

}
