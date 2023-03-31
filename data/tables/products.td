NAME: products
MODEL:Product
RECORDS: 10
RELATIONS: hasMany("invoices", "invoices", "product_id"),
hasOneThroughMorph("shops", "purchasable", "shop")
HAS_TIMESTAMPS: true
COLUMNS:
id, productId, false, null, id;
name, productName, true, company, string;
price, price, true, numberBetween(100, 1000), integer;