<?php

$relationFunction = [
"",
"\tpublic function $fnName(){",
"\t\treturn " . 'CustomMorph::' ."$relationMethod(". '$this'. ", $related::class, '$pivotTableName', '$ownerPrefix', '$relatedPrefix');",
"\t}"
];

