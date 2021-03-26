SELECT * FROM announcement_locales WHERE content LIKE '%file/media_center/announcement/%';
SELECT * FROM offer_locales WHERE content LIKE '%file/offer/%';
SELECT * FROM press_release_locales WHERE content LIKE '%file/media_center/press_release/%';
SELECT * FROM webpage_locale_contents WHERE content LIKE '%file/webpage/%';

UPDATE announcement_locales
SET content = REPLACE(content, 'file/media_center/announcement/', 'https://d395vcjurtz71r.cloudfront.net/media_center/announcement/')
WHERE content LIKE '%file/media_center/announcement/%';

UPDATE offer_locales
SET content = REPLACE(content, 'file/offer/', 'https://d395vcjurtz71r.cloudfront.net/offer/')
WHERE content LIKE '%file/offer/%';

UPDATE offer_locales
SET content = REPLACE(content, 'file/webpage/', 'https://d395vcjurtz71r.cloudfront.net/webpage/')
WHERE content LIKE '%file/webpage/%';

UPDATE press_release_locales
SET content = REPLACE(content, 'file/media_center/press_release/', 'https://d395vcjurtz71r.cloudfront.net/media_center/press_release/')
WHERE content LIKE '%file/media_center/press_release/%';

UPDATE webpage_locale_contents
SET content = REPLACE(content, 'file/offer/', 'https://d395vcjurtz71r.cloudfront.net/offer/')
WHERE content LIKE '%file/offer/%';

UPDATE webpage_locale_contents
SET content = REPLACE(content, 'file/webpage/', 'https://d395vcjurtz71r.cloudfront.net/webpage/')
WHERE content LIKE '%file/webpage/%';
