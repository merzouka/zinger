<?php

namespace DatabaseDefinition\Src\Pivot;
enum Morph : string{
    case Many = "*";
    case One = "1";
}