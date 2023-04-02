EXCLUDE: f, resource
NAME: customers
MODEL: Provider
RECORDS: 10
RELATIONS: hasManyThroughManyMorphs("person", "seller")
HAS_TIMESTAMPS: false 
COLUMNS:
provider_id, null, false, null, id;
[*person, table*]
