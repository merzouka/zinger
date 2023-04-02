NAME: products 
MODEL: Product
RECORDS: 10
RELATIONS: hasMany("invoices", "invoices"),
hasOneThroughMorph("shops", "purchasable", "shop")
HAS_TIMESTAMPS: true
COLUMNS:
product_id, null, false, null, id;
name, name, true, name, string;
price, price, true, numberBetween(100, 2000), integer;
