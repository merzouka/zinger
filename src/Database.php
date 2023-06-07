<?php

namespace DatabaseDefinition\Src;

include_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Error\BaseTableError;
use DatabaseDefinition\Src\Helpers\TableParser;
use Error;

/**
 * mainly used for seeding database
 */
class Database{

    #region helpers
    /**
     * gets the tables to seed from appropriate folder
     *
     * @param TableType $type
     * @param array $tablesToSeed
     * @param array $excludes
     * @return void
     */
    private static function fillTablesToSeed(TableType $type, array &$tablesToSeed, array $excludes){
        $path = TableParser::getPath($type);
        foreach (scandir($path) as $table){
            $table = explode(".", $table)[0];
            if (!in_array($table, $excludes)){
                $tablesToSeed[] = $table;
            }
        }
    }

    /**
     * executes seed method for tables in $table
     *
     * @param array $tables
     * @param integer $number
     * @return void
     */
    private static function seedFromArray(array $tables, int $number){
        foreach ($tables as &$table){
            echo "Seeding $table : " . RED . BOLD . "RUNNING" . QUIT . PHP_EOL;
            $table->seed($number);
            echo "\rSeeding $table : " . GREEN . BOLD . "DONE". QUIT . str_repeat(" ", 40) . PHP_EOL;
        }
    }

    private static function seedByType(
        array $tablesToSeed = [],
        array $excludes = [],
        ?TableType $type = null,
        int $pivotNumberOfRecords = 30,
        int $tableNumberOfRecords = 0
    ){
        if ($tablesToSeed = []){
            if ($type === null){
                static::fillTablesToSeed(TableType::Table, $tablesToSeed, $excludes);
                $tablesToSeed = static::orderAndGetTables($tablesToSeed);
                static::seedFromArray($tablesToSeed, $tableNumberOfRecords);
                static::fillTablesToSeed(TableType::Pivot, $tablesToSeed, $excludes);
                $tablesToSeed = static::orderAndGetTables($tablesToSeed);
                static::seedFromArray($tablesToSeed, $pivotNumberOfRecords);
                return;
            }
            static::fillTablesToSeed($type, $tablesToSeed, $excludes);
        }
        $tablesToSeed = static::orderAndGetTables($tablesToSeed);
        throw new Error(count($tablesToSeed));
        throw new Error(implode(", ", array_map(fn($table) => $table->name, $tablesToSeed)));
        static::seedFromArray(
            $tablesToSeed,
            $type === TableType::Pivot ? $pivotNumberOfRecords : $tableNumberOfRecords
        );
    }
    #endregion

    #region seeding methods
    public static function seedTable(
        array $tablesToSeed = [],
        array $excludes = [],
        int $tableNumberOfRecords = 0
    ){
        static::seedByType(
            $tablesToSeed,
            $excludes,
            TableType::Table,
            tableNumberOfRecords: $tableNumberOfRecords
        );
    }

    public static function seedPivot(
        array $tablesToSeed = [],
        array $excludes = [],
        int $pivotNumberOfRecords = 30
    ){
        static::seedByType(
            $tablesToSeed,
            $excludes, 
            TableType::Pivot,
            pivotNumberOfRecords: $pivotNumberOfRecords
        );
    }

    public static function seed(
        array $tablesToSeed = [],
        array $excludes = [],
        int $pivotNumberOfRecords = 30,
        int $tableNumberOfRecords = 0
    ){
        static::seedByType(
            $tablesToSeed,
            $excludes,
            pivotNumberOfRecords: $pivotNumberOfRecords,
            tableNumberOfRecords: $tableNumberOfRecords
        );
    }
    #endregion

    #region sorting methods
    /**
     * sorts the nodes from root to child and puts result in sortedNodes
     *
     * @param array $heads
     * @param array $sortedNodes
     * @return void
     */
    private static function sortNodes(array $heads, array &$sortedNodes){
        foreach ($heads as &$head){
            if (str_contains(get_class($head), "Node")){
                $sortedNodes[] = $head->table;
            }
        }
        foreach ($heads as &$head){
            if (isset($head->children)){
                static::sortNodes($head->children, $sortedNodes);
            }
        }
    }

    private static function isChild(Node &$node, Node &$head) : bool{
        if ($head === $node){
            return true;
        }
        foreach ($head->children as &$child){
            if (str_contains(get_class($child), "Node")){
                if (static::isChild($node, $child)){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * get the unique values in array $tables
     *
     * @param array $tables
     * @return void
     */
    private static function unique(array &$nodes){
        $names = [];
        $uniques = [];
        foreach ($nodes as &$node){
            if (!in_array($node->table->name, $names)){
                $names[] = $node->table->name;
                $uniques[] = $node->table;
            }
        }
        return $uniques;
    }

    /**
     * orders the table according to which one relies the least on the others and returns
     * an array containing the Table instances of tables
     *
     * @param array $tableNames
     * @return array
     */
    public static function orderAndGetTables(array $tableNames) : array{
        if ($tableNames === []){
            return [];
        }
        $nodes = [];
        foreach($tableNames as $tableName){
            try {
                $new = new Node($tableName);
            } catch (BaseTableError){
                continue;
            }
            foreach ($nodes as &$node){
                foreach ($node->children as &$child){
                    if ($child == $tableName){
                        $child = $new;
                        break;
                    }
                }
            }
            foreach ($new->children as &$child){
                foreach ($nodes as &$node){
                    if ($child == $node->table->name){
                        $child = $node;
                    }
                }
            }
            $nodes[] = $new;
        }
        $heads = [];
        foreach ($nodes as &$node){
            // insert first element
            if (count($heads) == 0){
                $heads[] = $node;
                continue;
            }
            // see if node is child of any head
            $inserted = false;
            $i = 0;
            foreach ($heads as &$head){
                if (static::isChild($head, $node)){
                    if ($inserted){
                        unset($heads[$i]);
                    } else {
                        $head = $node;
                        $inserted = true;
                    }
                } else if (static::isChild($node, $head)){
                    $inserted = true;
                    break;
                }
                $i++;
            }
            if (!$inserted){
                $heads[] = $node;
            }
        }
        $sortedNodes = [];
        static::sortNodes($heads, $sortedNodes);
        $sortedNodes = array_reverse($sortedNodes);
        return static::unique($sortedNodes);
    }
    #endregion
}
