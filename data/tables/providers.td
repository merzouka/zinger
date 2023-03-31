NAME: providers 
RECORDS: 10
RELATIONS: hasManyThroughManyMorphs("person", "seller")
HAS_TIMESTAMPS: true 
COLUMNS:
provider_id, id, false, null, id;
name, name, true, name, string;
age, age, true, numberBetween(10, 100), tinyInteger, nullable;