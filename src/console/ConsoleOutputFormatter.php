<?php

namespace DatabaseDefinition\Src\Console;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

class ConsoleOutputFormatter{
    private OutputType $type;
    private string $message;

    public function __construct(OutputType $type, string $message)
    {
        $this->type = $type;
        $this->message = $message;
    }

    public function getMessage(){
        return $this->message;
    }
    
    public function getType(){
        return $this->type;
    }

    public function out(){
        echo"
{$this->type->value}  {$this->message}
";
    }
}

