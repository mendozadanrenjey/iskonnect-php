ALTER TABLE announcements
  ADD COLUMN organization_id VARCHAR(32) NULL AFTER author_id,
  ADD KEY idx_announcement_organization (organization_id, created_at),
  ADD CONSTRAINT fk_announcement_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;
