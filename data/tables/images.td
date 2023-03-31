NAME: images
MODEL:Image
RECORDS: 10
RELATIONS: morphsOne("image", "imageable", "imageable_image")
HAS_TIMESTAMPS: false
COLUMNS:
image_id, id, false, null, id;
added_at, addedAt, true, date, timestamp, nullable;
path, path, true, text, string, nullable;