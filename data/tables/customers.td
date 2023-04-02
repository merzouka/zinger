EXCLUDE: s
NAME: customers
MODEL: Customer
RECORDS: 10
RELATIONS: hasMany("invoices", "invoices"), 
hasOneThroughMorph("images", "imageable", "image", "image_imageable", "image", 1),
hasManyThroughManyMorphs("person", "seller")
HAS_TIMESTAMPS: true
COLUMNS:
customer_id, id, false, null, id;
[*person, base*]

