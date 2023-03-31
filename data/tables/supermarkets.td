NAME: super_markets 
RECORDS: 10
RELATIONS: hasManyThroughManyMorphs("seller", "person"),
hasOneThroughManyMorphs("seller", "super")
HAS_TIMESTAMPS: true
COLUMNS:
super_market_id, supermarketId, false, null, id;
name, name, true, company, string;
slogan, slogan, false, text, string, nullable;
address, address, true, address, string, nullable;
