-- Run this once only when upgrading a database created from the earlier starter schema.
ALTER TABLE organizations
  ADD COLUMN department VARCHAR(160) NOT NULL DEFAULT 'Campus-wide' AFTER category;

ALTER TABLE users
  ADD COLUMN department VARCHAR(160) NULL AFTER course;

UPDATE organizations SET department='Information Technology' WHERE id='cs';
UPDATE organizations SET department='Business Administration and Entrepreneurship' WHERE id='bsa';
UPDATE organizations SET department='Teacher Education' WHERE id='edu';
UPDATE organizations SET department='Industrial Technology' WHERE id='ind';
UPDATE organizations SET department='Engineering' WHERE id='eng';
