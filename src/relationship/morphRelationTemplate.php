<?php

$relationFunction = [
"",
"\tpublic function $fnName(){",
"\t\treturn " . 'CustomMorph::' ."$relationMethod(". '$this'. ", '$pivotTableName', '$ownerPrefix', '$relatedPrefix');",
"\t}"
];

