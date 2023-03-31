<?php

$relationFunction = [
"",
"\tpublic function $fnName(){",
"\t\treturn " . '$this->' ."$relationMethod($related::class, '$pivotTableName', '$ownerPivotColumn', '$relatedPivotColumn', '$ownerPrimary', '$relatedPrimary');",
"\t}"
];