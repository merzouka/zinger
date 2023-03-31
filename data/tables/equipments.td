NAME: equipments
RECORDS: 10
RELATIONS: hasOneThroughMorph("shops", "purchasable", "shop")
HAS_TIMESTAMPS: false 
COLUMNS:
equipment_id, id, false, null, id;
name, name, true, company, string(100);
company, company, true, company, string, nullable;