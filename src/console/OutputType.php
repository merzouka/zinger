<?php

namespace DatabaseDefinition\Src\Console;



enum OutputType : string{
    case Error = "\t\e[48;5;226m\e[1m Error \e[0m";
    case Info = "\t\e[48;5;19m\e[1m Info \e[0m";
    case Deleted = "\t\e[48;5;196m\e[1m Deleted \e[0m";
    case Created = "\t\e[48;5;46m\e[1m Created \e[0m";
}
