NAME: images
MODEL: Image
RECORDS: 10
RELATIONS: morphsOne("image", "imageable_image", "image_imageable", "imageable")
HAS_TIMESTAMPS: true
COLUMNS:
image_id, null, false, null, id;
resolution, resolution, true, null, string, nullable;
name, name, true, name, string, nullable;
