NAME: customers
MODEL:Customer
RECORDS: 10
RELATIONS: hasOneThroughMorph("images", "imageable", "image", "imageable_image", "image", 1),
hasMany("invoices"), hasManyThroughManyMorphs("person", "seller")
HAS_TIMESTAMPS: true
COLUMNS:
customer_id, null, false, null, id;
name,        name, true, name, string;
type,        type, true, randomElement(['I', 'B']), string(1);
email,       email, true, email, string;
address,     streetAddress, true, streetAddress, string, nullable;
city,        city,          true, city, string, nullable;
state,       state, true, state, string;
postal_code, postalCode, true, postcode, string, nullable


