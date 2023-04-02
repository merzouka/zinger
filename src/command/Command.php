<?php

namespace DatabaseDefinition\Src\Command;

use DatabaseDefinition\Src\Console\ConsoleOutputFormatter;
use DatabaseDefinition\Src\Console\OutputType;
use Error;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;


class Command{


    public static function run(array $args){
        try {
            @$className = "DatabaseDefinition\\Src\\Command\\" . ucfirst($args[0]) . "CommandHelper";
            if (@$args[1] === "-v"){
                $verbose = true;
                $args = array_slice($args, 2);
            } else {
                $args = array_slice($args, 1);
                $verbose = false;
            }
            $classMethods = get_class_methods($className);
            if (count($classMethods) == 1){
                $args = array_merge([$classMethods[0]], array_slice($args, 0));
            }
            call_user_func_array([$className, @$args[0]], [array_slice($args, 1), $verbose]);
        } catch (Error){
            (new ConsoleOutputFormatter(OutputType::Error, "Invalid command."))->out();
            echo PHP_EOL;
        }
    }
}