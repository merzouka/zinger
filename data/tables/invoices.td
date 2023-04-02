NAME: invoices 
MODEL: Invoice
RECORDS: 10
FOREIGN_KEYS: ('customer_id', 'customer_id', 'customers', onUpdate : cascade, onDelete : cascade),
("product_id", "product_id", "products", onDelete : null)
RELATIONS:  belongsTo("customers"), belongsTo("products")
COLUMNS:
invoice_id, null, false, null, id;
customer_id, customerId, true, null, bigInteger, unsigned;
product_id, productId, true, null, bigInteger, unsigned;
state, state, true, randomElement(["P", "V"]), string(1); /*paid, void*/
