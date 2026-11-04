CREATE TABLE IF NOT EXISTS organization_leadership (
  organization_id VARCHAR(32) NOT NULL,
  position VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL DEFAULT '',
  profile TEXT NULL,
  contact_email VARCHAR(254) NULL,
  facebook_url VARCHAR(500) NULL,
  profile_photo CHAR(32) NULL,
  photo_mime_type VARCHAR(32) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (organization_id, position),
  CONSTRAINT fk_org_leadership_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO organization_leadership (organization_id, position)
SELECT id, 'President' FROM organizations;

INSERT IGNORE INTO organization_leadership (organization_id, position)
SELECT id, 'Vice President' FROM organizations;

INSERT IGNORE INTO organization_leadership (organization_id, position)
SELECT id, 'Secretary' FROM organizations;

INSERT IGNORE INTO organization_leadership (organization_id, position)
SELECT id, 'Treasurer' FROM organizations;

INSERT IGNORE INTO organization_leadership (organization_id, position)
SELECT id, 'Public Relations Officer' FROM organizations;
