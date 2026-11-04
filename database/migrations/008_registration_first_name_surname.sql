ALTER TABLE users
  ADD COLUMN first_name VARCHAR(160) NULL AFTER name,
  ADD COLUMN surname VARCHAR(160) NULL AFTER first_name;

ALTER TABLE officer_registration_requests
  ADD COLUMN first_name VARCHAR(160) NULL AFTER name,
  ADD COLUMN surname VARCHAR(160) NULL AFTER first_name;

UPDATE users
SET first_name = SUBSTRING_INDEX(TRIM(name), ' ', 1),
    surname = NULLIF(TRIM(SUBSTRING(TRIM(name), CHAR_LENGTH(SUBSTRING_INDEX(TRIM(name), ' ', 1)) + 1)), '')
WHERE first_name IS NULL;

UPDATE officer_registration_requests
SET first_name = SUBSTRING_INDEX(TRIM(name), ' ', 1),
    surname = NULLIF(TRIM(SUBSTRING(TRIM(name), CHAR_LENGTH(SUBSTRING_INDEX(TRIM(name), ' ', 1)) + 1)), '')
WHERE first_name IS NULL;
