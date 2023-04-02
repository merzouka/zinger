
NAME: shops
MODEL: Shop
RECORDS: 10
RELATIONS: morphsMany("shop", "purchasable"),
hasOneThroughMorph("images", "imageable", "image"),
hasManyThroughManyMorphs("seller", "person"),
hasOneThroughManyMorphs("seller", "super")
HAS_TIMESTAMPS: true
COLUMNS:
shop_id, null, false, null, id;
name, name, true, company, string;
address, address, true, address, string;
