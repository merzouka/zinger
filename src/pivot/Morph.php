<?php

namespace DatabaseDefinition\Src\Pivot;

/**
 * enum containing type of morph
 */
enum Morph : string{
    case Many = "*";
    case One = "1";
}