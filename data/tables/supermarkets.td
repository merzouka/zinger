NAME: supermarkets 
MODEL: Supermarkets
RECORDS: 10
RELATIONS: hasManyThroughManyMorphs("seller", "person", "person_seller", "people", 1),
hasOneThroughManyMorphs("seller", "super")
COLUMNS:
id, id, false, null, id;
name, name, true, company, string;
area, area, true, numberBetween(100, 10000), integer, unsigned;
address, address, true, address, string;
