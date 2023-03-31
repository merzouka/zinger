NAME: invoices
MODEL:Invoice
RECORDS: 10
RELATIONS: belongsTo("customers"), belongsTo("products")
FOREIGN_KEYS: ('customer_id', 'customer_id', 'customers', onUpdate : cascade, onDelete : cascade),
('product_id', 'id', 'products', onUpdate : cascade, onDelete : null)
HAS_TIMESTAMPS: true
COLUMNS:
invoice_id, id, false, null, id;
customer_id, customerId, true, null, bigInteger, unsigned;
product_id, productId, true, null, bigInteger, unsigned;
price, price, true, numberBetween(100, 20000), integer;
info, invoiceInfo, true, text, string(250), nullable;