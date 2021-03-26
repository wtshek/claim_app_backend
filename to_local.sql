SELECT * FROM announcement_locales WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/media_center/announcement/%';
SELECT * FROM offer_locales WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/offer/%';
SELECT * FROM press_release_locales WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/media_center/press_release/%';
SELECT * FROM webpage_locale_contents WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/webpage/%';

UPDATE announcement_locales
SET content = REPLACE(content, 'https://d395vcjurtz71r.cloudfront.net/media_center/announcement/', 'file/announcement/')
WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/media_center/announcement/%';

UPDATE offer_locales
SET content = REPLACE(content, 'https://d395vcjurtz71r.cloudfront.net/offer/', 'file/offer/')
WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/offer/%';

UPDATE offer_locales
SET content = REPLACE(content, 'https://d395vcjurtz71r.cloudfront.net/webpage/', 'file/webpage/')
WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/webpage/%';

UPDATE press_release_locales
SET content = REPLACE(content, 'https://d395vcjurtz71r.cloudfront.net/media_center/press_release/', 'file/press_release/')
WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/media_center/press_release/%';

UPDATE webpage_locale_contents
SET content = REPLACE(content, 'https://d395vcjurtz71r.cloudfront.net/offer/', 'file/offer/')
WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/offer/%';

UPDATE webpage_locale_contents
SET content = REPLACE(content, 'https://d395vcjurtz71r.cloudfront.net/webpage/', 'file/webpage/')
WHERE content LIKE '%https://d395vcjurtz71r.cloudfront.net/webpage/%';
