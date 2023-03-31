NAME: managers
RECORDS: 10
RELATIONS: hasManyThroughManyMorphs("super", "seller")
COLUMNS:
manager_id, id, false, null, id;
name, manger_name, true, name, string;
gender, gender, true, randomElement(["M", "F"]), string(1);