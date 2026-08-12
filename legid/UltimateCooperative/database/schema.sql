CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hospital_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  address TEXT NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(160) NOT NULL,
  logo_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  profile_photo VARCHAR(255) NULL,
  two_factor_secret VARCHAR(80) NULL,
  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  department_id INT NULL,
  employee_no VARCHAR(40) NOT NULL UNIQUE,
  specialization VARCHAR(120) NULL,
  qualification VARCHAR(160) NULL,
  address TEXT NULL,
  hire_date DATE NULL,
  salary DECIMAL(12,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_no VARCHAR(40) NOT NULL UNIQUE,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  gender ENUM('male','female','other') NOT NULL,
  date_of_birth DATE NULL,
  blood_group VARCHAR(8) NULL,
  genotype VARCHAR(8) NULL,
  allergies TEXT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  address TEXT NULL,
  emergency_name VARCHAR(160) NULL,
  emergency_phone VARCHAR(40) NULL,
  portal_user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FULLTEXT KEY idx_patient_search (patient_no, first_name, last_name, phone),
  CONSTRAINT fk_patient_portal_user FOREIGN KEY (portal_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS doctor_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  weekday TINYINT NOT NULL,
  starts_at TIME NOT NULL,
  ends_at TIME NOT NULL,
  max_patients INT DEFAULT 20,
  CONSTRAINT fk_schedule_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_doctor_day (doctor_id, weekday)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_no VARCHAR(40) NOT NULL UNIQUE,
  patient_id INT NOT NULL,
  doctor_id INT NULL,
  department_id INT NULL,
  appointment_at DATETIME NOT NULL,
  reason TEXT NULL,
  status ENUM('booked','checked_in','with_doctor','completed','cancelled','no_show') DEFAULT 'booked',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointment_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_appointment_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_appointment_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_appointment_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_appointment_date (appointment_at, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_no VARCHAR(40) NOT NULL UNIQUE,
  patient_id INT NOT NULL,
  appointment_id INT NULL,
  doctor_id INT NULL,
  status ENUM('queue','consulting','lab','pharmacy','billing','admitted','closed') DEFAULT 'queue',
  priority ENUM('normal','urgent','emergency') DEFAULT 'normal',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  CONSTRAINT fk_visit_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_visit_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  CONSTRAINT fk_visit_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_visit_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emr_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  symptoms TEXT NULL,
  diagnosis TEXT NULL,
  treatment_plan TEXT NULL,
  progress_notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_emr_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_emr_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_emr_doctor FOREIGN KEY (doctor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS medical_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  emr_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(80) NOT NULL,
  uploaded_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_attachment_emr FOREIGN KEY (emr_id) REFERENCES emr_records(id) ON DELETE CASCADE,
  CONSTRAINT fk_attachment_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  status ENUM('pending','dispensed','partially_dispensed','cancelled') DEFAULT 'pending',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rx_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_rx_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_rx_doctor FOREIGN KEY (doctor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS medicine_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS medicines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NULL,
  name VARCHAR(160) NOT NULL,
  sku VARCHAR(80) NOT NULL UNIQUE,
  unit VARCHAR(40) NOT NULL,
  stock_type VARCHAR(30) NULL,
  reorder_level INT DEFAULT 10,
  selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_medicine_category FOREIGN KEY (category_id) REFERENCES medicine_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS medicine_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  medicine_id INT NOT NULL,
  batch_no VARCHAR(80) NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  expiry_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_batch_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
  INDEX idx_expiry (expiry_date),
  UNIQUE KEY uq_batch_medicine (medicine_id, batch_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prescription_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  medicine_id INT NULL,
  medicine_name VARCHAR(160) NOT NULL,
  dosage VARCHAR(120) NOT NULL,
  frequency VARCHAR(120) NOT NULL,
  duration VARCHAR(120) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  dispensed_quantity INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_rx_item_rx FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_rx_item_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_tests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL UNIQUE,
  sample_type VARCHAR(80) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  normal_range VARCHAR(160) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_no VARCHAR(40) NOT NULL UNIQUE,
  visit_id INT NOT NULL,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  status ENUM('requested','sample_collected','processing','completed','cancelled') DEFAULT 'requested',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lab_request_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_lab_request_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_lab_request_doctor FOREIGN KEY (doctor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_request_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lab_request_id INT NOT NULL,
  lab_test_id INT NOT NULL,
  result_value TEXT NULL,
  result_notes TEXT NULL,
  technician_id INT NULL,
  completed_at DATETIME NULL,
  CONSTRAINT fk_lab_item_request FOREIGN KEY (lab_request_id) REFERENCES lab_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_lab_item_test FOREIGN KEY (lab_test_id) REFERENCES lab_tests(id),
  CONSTRAINT fk_lab_item_tech FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  type ENUM('general','private','maternity','pediatric','icu','emergency') DEFAULT 'general',
  floor VARCHAR(40) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS beds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ward_id INT NOT NULL,
  bed_no VARCHAR(40) NOT NULL,
  status ENUM('available','occupied','maintenance','reserved') DEFAULT 'available',
  CONSTRAINT fk_bed_ward FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE CASCADE,
  UNIQUE KEY uq_ward_bed (ward_id, bed_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admission_no VARCHAR(40) NOT NULL UNIQUE,
  visit_id INT NOT NULL,
  patient_id INT NOT NULL,
  bed_id INT NOT NULL,
  admitted_by INT NULL,
  admitted_at DATETIME NOT NULL,
  discharged_at DATETIME NULL,
  discharge_summary TEXT NULL,
  status ENUM('active','discharged','transferred') DEFAULT 'active',
  CONSTRAINT fk_admission_visit FOREIGN KEY (visit_id) REFERENCES visits(id),
  CONSTRAINT fk_admission_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_admission_bed FOREIGN KEY (bed_id) REFERENCES beds(id),
  CONSTRAINT fk_admission_user FOREIGN KEY (admitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(40) NOT NULL UNIQUE,
  patient_id INT NOT NULL,
  visit_id INT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('draft','unpaid','partial','paid','void') DEFAULT 'unpaid',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_invoice_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_invoice_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
  CONSTRAINT fk_invoice_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  item_type ENUM('consultation','lab','medicine','admission','procedure','other') NOT NULL,
  description VARCHAR(220) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_invoice_item_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(40) NOT NULL UNIQUE,
  invoice_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('cash','card','bank_transfer','insurance','mobile_money') NOT NULL,
  reference VARCHAR(120) NULL,
  paid_by VARCHAR(160) NULL,
  received_by INT NULL,
  paid_at DATETIME NOT NULL,
  CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  CONSTRAINT fk_payment_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  channel ENUM('email','sms','in_app') NOT NULL,
  title VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('queued','sent','failed','read') DEFAULT 'queued',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(120) NOT NULL,
  entity VARCHAR(120) NOT NULL,
  entity_id INT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_entity (entity, entity_id),
  INDEX idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TRIGGER IF EXISTS trg_invoice_items_total;
CREATE TRIGGER trg_invoice_items_total
BEFORE INSERT ON invoice_items
FOR EACH ROW SET NEW.total = NEW.quantity * NEW.unit_price;

DROP TRIGGER IF EXISTS trg_invoice_recalculate_after_item;
CREATE TRIGGER trg_invoice_recalculate_after_item
AFTER INSERT ON invoice_items
FOR EACH ROW
UPDATE invoices
SET subtotal = (SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = NEW.invoice_id),
    total = (SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = NEW.invoice_id) - discount + tax
WHERE id = NEW.invoice_id;

DROP TRIGGER IF EXISTS trg_payment_invoice_status;
CREATE TRIGGER trg_payment_invoice_status
AFTER INSERT ON payments
FOR EACH ROW
UPDATE invoices
SET paid = (SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = NEW.invoice_id),
    status = CASE
      WHEN (SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = NEW.invoice_id) >= total THEN 'paid'
      WHEN (SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = NEW.invoice_id) > 0 THEN 'partial'
      ELSE 'unpaid'
    END
WHERE id = NEW.invoice_id;
