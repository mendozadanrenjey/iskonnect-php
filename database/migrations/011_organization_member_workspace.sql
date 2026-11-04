CREATE TABLE IF NOT EXISTS organization_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id VARCHAR(32) NOT NULL,
  uploader_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name CHAR(32) NOT NULL UNIQUE,
  mime_type VARCHAR(127) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org_file_created (organization_id, created_at),
  CONSTRAINT fk_org_file_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_org_file_uploader FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id VARCHAR(32) NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(180) NOT NULL,
  status ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_org_ticket_org_status (organization_id, status, updated_at),
  KEY idx_org_ticket_student (student_id, updated_at),
  CONSTRAINT fk_org_ticket_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_org_ticket_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_ticket_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org_ticket_message (ticket_id, created_at),
  CONSTRAINT fk_org_ticket_message_ticket FOREIGN KEY (ticket_id) REFERENCES organization_tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_org_ticket_message_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
