<?php

namespace DatabaseDefinition\Src\Command;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

class PathCommandHelper{

    public static function path(array $newPath){
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . PATH_FILE_NAME;
        $file = fopen($path, "w");
        fwrite($file, $newPath[0]);
        fclose($file);
    }
}