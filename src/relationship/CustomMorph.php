<?php

namespace DatabaseDefinition\Src\Relationship;

include_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "helpers" . DIRECTORY_SEPARATOR . "constants.php";
include AUTOLOADER;

use DatabaseDefinition\Src\Alias\AliasHandler as AH;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Relationship\Morph;

/**
 * Uses database definition structure to pass parameters to Morph functions
 */
class CustomMorph
{

    public static function morphsOne(
        Model $owner,
        string $pivotTableName,
        string $ownerPrefix,
        string $relatedPrefix
    ): Model {
        return Morph::morphsOne(
            $owner,
            $pivotTableName,
            $ownerPrefix . "_id",
            $relatedPrefix . "_type",
            $relatedPrefix . "_id"
        );
    }

    public static function hasOneThroughMorph(
        Model $owner,
        string $related,
        string $pivotTableName,
        string $ownerPrefix,
        string $relatedPrefix
    ): Model {
        return Morph::hasOneThroughMorph(
            $owner,
            $related,
            $pivotTableName,
            $ownerPrefix . "_type",
            $ownerPrefix . "_id",
            $relatedPrefix . "_id"
        );
    }

    public static function morphsMany(
        Model $owner,
        string $pivotTableName,
        string $ownerPrefix,
        string $relatedPrefix
    ): Collection {
        return Morph::morphsMany(
            $owner,
            $pivotTableName,
            $ownerPrefix . "_id",
            $relatedPrefix . "_type",
            $relatedPrefix . "_id"
        );
    }

    public static function hasManyThroughMorph(
        Model $owner,
        string $related,
        string $pivotTableName,
        string $ownerPrefix,
        string $relatedPrefix
    ) : Collection{
        return Morph::hasManyThroughMorph(
            $owner,
            $related,
            $pivotTableName,
            $ownerPrefix . "_type",
            $ownerPrefix . "_id",
            $relatedPrefix . "_id"
        );
    }

    public static function hasOneThroughManyMorphs(
        Model $owner,
        string $pivotTableName,
        string $ownerPrefix,
        string $relatedPrefix
    ) : Model{
        $relatedPrefixArray = AH::getPrefixAliases($relatedPrefix, $pivotTableName);
        return Morph::hasOneThroughManyMorphs(
            $owner,
            $pivotTableName,
            $ownerPrefix . "_type",
            $ownerPrefix . "_id",
            $relatedPrefix . "_type",
            $relatedPrefix . "_id",
            AH::getAliasUsingPrefix($owner::class, $ownerPrefix, $pivotTableName),
            $relatedPrefixArray
        );
    }

    public static function hasManyThroughManyMorphs(
        Model $owner,
        string $pivotTableName,
        string $ownerPrefix,
        string $relatedPrefix
    ) : Collection{
        $relatedPrefixAliases = AH::getPrefixAliases($relatedPrefix, $pivotTableName);
        return Morph::hasManyThroughManyMorphs(
            $owner,
            $pivotTableName,
            $ownerPrefix . "_type",
            $ownerPrefix . "_id",
            $relatedPrefix . "_type",
            $relatedPrefix . "_id",
            AH::getAliasUsingPrefix($owner::class, $ownerPrefix, $pivotTableName),
            $relatedPrefixAliases
        );
    }
}
