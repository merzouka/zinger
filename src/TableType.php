<?php

namespace DatabaseDefinition\Src;

enum TableType : string{
    case Table = "table";
    case Pivot = "pivot";
}