NAME: equipments
MODEL: Equipment
RECORDS: 10
RELATIONS: hasOneThroughMorph("shops", "purchasable", "shop")
HAS_TIMESTAMPS: true
COLUMNS:
equipment_id, null, false, null, id;
name, equipmentName, true, name, string;
price, price, true, numberBetween(100, 2000), integer;
