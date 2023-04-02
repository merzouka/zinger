<?php

namespace DatabaseDefinition\Src;

include_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Helpers\TableParser;

class Database{

    private static function seedByType(TableType $type, int $pivotNumberOfRecords = 30, int $tableNumberOfRecords = 0){
        $path = TableParser::getPath($type);
        foreach (scandir($path) as $table){
            $table = explode(".", $table)[0];
            echo "Seeding $table : " . RED . BOLD . "RUNNING" . QUIT . PHP_EOL;
            (TableFactory::createTable($table, $type))->seed($type == TableType::Pivot ? $pivotNumberOfRecords : $tableNumberOfRecords);
            echo "\rSeeding $table : " . GREEN . BOLD . "DONE". QUIT . str_repeat(" ", 40) . PHP_EOL;
        }
    }

    public static function seed(int $pivotNumberOfRecords = 30, int $tableNumberOfRecords = 0){
        static::seedByType(TableType::Table, tableNumberOfRecords: $tableNumberOfRecords);
        static::seedByType(TableType::Pivot, pivotNumberOfRecords: $pivotNumberOfRecords);
    }
}