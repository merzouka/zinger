<?php

namespace DatabaseDefinition\Src;

/**
 * enum containing the available table types
 */
enum TableType : string{
    case Table = "table";
    case Pivot = "pivot";
    case Base = "base";
}