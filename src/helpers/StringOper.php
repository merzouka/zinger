<?php

namespace DatabaseDefinition\Src\Helpers;

/**
 * a class responsible for multiple string munipulations operations
 */
class StringOper{

    public const PLURAL_PATTERNS = [
        "ies", "s"
    ];

    public const WHITE_SPACE = [
        " ", PHP_EOL, "\t", "\n"
    ];

    public static function last(array $array) : mixed{
        return $array[count($array) - 1];
    }

    public static function removeWhiteSpaces(string $str) : string{
        return implode("", array_values(array_filter(str_split($str), fn($char) => !in_array($char, static::WHITE_SPACE))));
    }

    /**transforms a table name to a model name following conventions*/
    public static function getModelName(string $table_name){
        $name_parts = \explode("_", $table_name);
        $name_parts[count($name_parts) - 1] = static::getSingular(static::last($name_parts));
        return implode("", array_map(fn($part) => ucfirst(strtolower($part)), $name_parts));
    }

    public static function parseParam(string $str) : mixed{
        if (str_contains($str, "'") || str_contains($str, '"')){
            // param is string
            return trim($str, (str_contains($str, "'")) ? "'" : '"');
        } else if ($str == "true" || $str == "false"){
            return ($str == "true");
        } else if ($str == "null"){
            return null;
        } else if (str_contains($str, ".")){
            return (float)$str;
        } else {
            return (int)$str;
        }
    }

    /**
     * this function will return an array of items after splitting $str according to $seperator
     * that is if the $seperator is not inside parentesises
     */
    public static function splitIfNotBrackets(string $separator, string $str) : array{
        $in_parenthesis = 0;
        $array_item = "";
        $result = [];
        $chars = str_split($str);
        foreach ($chars as $char){
            if (substr($array_item, -strlen($separator)) == $separator && $in_parenthesis == 0){
                $result[] = substr($array_item, 0, -strlen($separator));
                $array_item = "";
            }
            if ($char == "(" || $char == "{" || $char == "["){
                $in_parenthesis++;
            }
            if ($char == ")" || $char == "}" || $char == "]"){
                $in_parenthesis--;
            }
            $array_item .= $char;
        }
        if (substr($array_item, -strlen($separator)) == $separator && $in_parenthesis == 0){
            $result[] = substr($array_item, 0, -strlen($separator));
            $array_item = "";
        }
        $result[] = $array_item;
        return $result;
    }

    public static function arrayToString(array $arr) : string{
        return "[". implode(", ", array_map(
            fn($element) => (is_array($element)) ?
            static::arrayToString($element) :
            static::translateValueByType($element), $arr
        )). "]";
    }

    /**
     * formats value if string return 'value'  if null return NULL
     *
     * @param mixed $value
     * @return string
     */
    public static function translateValueByType(mixed $value) : string{
        if (gettype($value) == "string"){
            return "'$value'";
        }
        if (gettype($value) == "NULL"){
            return "NULL";
        }
        return "" . $value;
    }

    /**
     * transforms an array of params to a string where the params are separated by ", "
     *
     * @param array $params
     * @return string
     */
    public static function getParams(array $params, int $startingIndex = 0) : string{
        return implode(", ", array_map(
            fn($element) => is_array($element) ? static::arrayToString($element) : static::translateValueByType($element),
            array_slice($params, $startingIndex)
        ));
    }
    
    public static function boolToString(bool $b) : string{
        return ($b) ? "true" : "false";
    }

    /**
     * transforms an array containing info of a method to a string "method(params)"
     *
     * @param array $methodInfo
     * @return string
     */
    public static function getMethod(array $methodInfo, bool $isTypeMethod = true) : string{
        $str = $methodInfo["method"];
        $start = (!$isTypeMethod) ? 0 : (count($methodInfo["params"]) > 1 ? 1 : -1);
        if ($start >= 0){
            if ($str == "null"){
                return $str;
            }
            $str .= "(". static::getParams($methodInfo["params"], $start) . ")";
        }
        return $str;
    }

    public static function translateType(string $str) : string{
        if($str == "id"){
            return "bigInteger";
        }
        else if (str_contains($str, "Increments")){
            $str = explode("Increments", $str);
            return $str[0] . "Integer";
        }
        return $str;
    }

    public static function getSingular(string $plural) : string{
        $matches = [];
        foreach (static::PLURAL_PATTERNS as $pattern){
            preg_match("/(". $pattern .")$/", $plural, $matches, PREG_OFFSET_CAPTURE);
            if ($matches !== []){
                return substr($plural, 0, $matches[0][1]) . (($pattern == "ies") ? "y" : "");
            }
        }
        return $plural;
    }

    public static function getPlural(string $singular) : string{
        // the word is plural
        if ($singular != static::getSingular($singular)){
            return $singular;
        } 
        $matches = [];
        preg_match("/y$/", $singular, $matches);
        if ($matches !== []){
            return substr($singular, 0, -1) . "ies"; 
        }
        
        return $singular . "s";
    }

    /**
     * return the pivot table name from the 2 strings by concatenating them in alphabetical order
     *
     * @param string $str1
     * @param string $str2
     * @param boolean $isSingular
     * @return string
     */
    public static function getPivotName(string $str1, string $str2, bool $isSingular = true) : string{
        if (!$isSingular){
            $str1 = static::getSingular($str1); $str2 = static::getSingular($str2);
        }
        return strcmp($str1, $str2) <= 0 ? $str1. "_" .$str2 : $str2. "_" .$str1;
    }

    /**
     * returns a string containing $str with additional $charToAdd where the result has 
     * length $desiredLength
     *
     * @param string $str
     * @param integer $desiredLength
     * @param string $charToAdd
     * @return string
     */
    public static function addChars(string $str, int $desiredLength, string $charToAdd = " ") : string{
        $special = [GREEN.TICK.QUIT, RED.CROSS.QUIT];
        $len = in_array($str, $special) ? 1 : strlen($str);
        $i = intdiv($desiredLength - $len, 2);
        return str_repeat(" ", $i) . $str . str_repeat(" ", $desiredLength - ($i + $len));
    }

    public static function printSeparator(array $lengths){
        echo "+". implode("+", array_map(fn($length) => str_repeat("-", $length + 2), $lengths)) . "+" . PHP_EOL;
    }

    public static function removeComments(string $str) : string{
        $x = "";
        $str = str_split($str);
        $result = "";
        $inComment = false;
        foreach ($str as $char){
            if (substr($x, -2) == "/*" && !$inComment){
                $inComment = true;
                $result = substr($result, 0, -2);
            }
            if (substr($x, -2) == "*/" && $inComment){
                $inComment = false;
            }
            if (!$inComment){
                $result .= $char;
            }
            $x .= $char;
        }
        return $result;
    }
}