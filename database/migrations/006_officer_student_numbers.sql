ALTER TABLE officer_registration_requests
  ADD COLUMN student_number VARCHAR(10) NULL AFTER name,
  ADD UNIQUE KEY uq_officer_request_student_number (student_number);
