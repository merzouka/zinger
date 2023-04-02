EXCLUDE: s
NAME: headquarters
MODEL: Headquarters
RECORDS: 10
RELATIONS: hasManyThroughManyMorphs("super", "seller")
HAS_TIMESTAMPS: false
COLUMNS: 
id, id, false, null, id;
name, name, true, company, string;
slogan, catchPhrase, true, text, string, nullable;
address, address, true, address, string, nullable;
