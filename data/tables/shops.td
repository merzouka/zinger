NAME: shops 
MODEL:Shop
RECORDS: 10
RELATIONS: morphsMany("shop", "purchasable"),
hasOneThroughMorph("images", "imageable", "image", "imageable_image", "image", 1),
hasManyThroughManyMorphs("seller", "person"),
hasOneThroughManyMorphs("seller", "super")
HAS_TIMESTAMPS: true
COLUMNS:
shop_id, shopId, false, null, id;
name, name, true, company, string(100);
address,   address, true, address, string, nullable;