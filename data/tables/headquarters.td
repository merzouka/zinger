NAME: headquarters 
MODEL: Headquarter
RECORDS: 10
RELATIONS: hasManyThroughManyMorphs("super", "seller")
HAS_TIMESTAMPS: true
COLUMNS:
id, id, false, null, id;
name, name, true, company, string;
address, address, true, address, string;