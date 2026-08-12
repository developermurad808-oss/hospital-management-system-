INSERT IGNORE INTO roles (name, slug) VALUES
('Super Admin','super_admin'),('Hospital Admin','hospital_admin'),('Doctor','doctor'),('Nurse','nurse'),
('Receptionist','receptionist'),('Pharmacist','pharmacist'),('Laboratory Technician','lab_technician'),
('Accountant','accountant'),('Patient','patient');

INSERT IGNORE INTO permissions (name, slug) VALUES
('View Dashboard','dashboard.view'),('Manage Patients','patients.manage'),('Manage Doctors','doctors.manage'),
('Manage Appointments','appointments.manage'),('Manage EMR','emr.manage'),('Manage Laboratory','lab.manage'),
('Manage Pharmacy','pharmacy.manage'),('Manage Billing','billing.manage'),('Manage Admissions','admissions.manage'),
('Manage Staff','staff.manage'),('Manage Reports','reports.manage'),('Manage Settings','settings.manage'),
('View All Roles','roles.view_all'),('Manage Security','security.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.slug = 'super_admin'
   OR (r.slug = 'hospital_admin' AND p.slug IN ('dashboard.view','patients.manage','doctors.manage','appointments.manage','emr.manage','lab.manage','pharmacy.manage','billing.manage','admissions.manage','staff.manage','reports.manage','settings.manage'))
   OR (r.slug = 'doctor' AND p.slug IN ('dashboard.view','patients.manage','appointments.manage','emr.manage','lab.manage','admissions.manage'))
   OR (r.slug = 'nurse' AND p.slug IN ('dashboard.view','patients.manage','appointments.manage','admissions.manage'))
   OR (r.slug = 'receptionist' AND p.slug IN ('dashboard.view','patients.manage','appointments.manage','billing.manage'))
   OR (r.slug = 'pharmacist' AND p.slug IN ('dashboard.view','pharmacy.manage'))
   OR (r.slug = 'lab_technician' AND p.slug IN ('dashboard.view','lab.manage'))
   OR (r.slug = 'accountant' AND p.slug IN ('dashboard.view','billing.manage','reports.manage'))
   OR (r.slug = 'patient' AND p.slug IN ('dashboard.view'));

INSERT IGNORE INTO departments (name, description) VALUES
('General Medicine','Outpatient and internal medicine'),('Emergency','Emergency and triage'),('Laboratory','Diagnostics'),
('Pharmacy','Dispensary'),('Maternity','Maternal care'),('Surgery','Surgical care');

INSERT IGNORE INTO medicine_categories (name) VALUES ('Analgesics'),('Antibiotics'),('Vitamins'),('Antimalarial');
INSERT IGNORE INTO medicines (category_id, name, sku, unit, reorder_level, selling_price) VALUES
((SELECT id FROM medicine_categories WHERE name='Analgesics'),'Paracetamol 500mg','MED-PARA-500','tablet',50,50.00),
((SELECT id FROM medicine_categories WHERE name='Antibiotics'),'Amoxicillin 500mg','MED-AMOX-500','capsule',30,180.00),
((SELECT id FROM medicine_categories WHERE name='Antimalarial'),'Artemether/Lumefantrine','MED-AL-20','pack',20,1200.00);
INSERT IGNORE INTO medicine_batches (medicine_id, batch_no, quantity, purchase_price, expiry_date) VALUES
((SELECT id FROM medicines WHERE sku='MED-PARA-500'),'PCT2026A',1000,20.00,'2027-12-31'),
((SELECT id FROM medicines WHERE sku='MED-AMOX-500'),'AMX2026A',400,95.00,'2027-10-30');

INSERT IGNORE INTO lab_tests (name, sample_type, price, normal_range) VALUES
('Full Blood Count','Blood',2500.00,'Varies by parameter'),('Blood Group','Blood',1500.00,'A/B/AB/O Rh +/-'),
('Malaria Parasite','Blood',1800.00,'Negative'),('Urinalysis','Urine',1200.00,'Normal');

INSERT IGNORE INTO wards (name, type, floor) VALUES ('General Ward A','general','Ground'),('ICU 1','icu','First');
INSERT IGNORE INTO beds (ward_id, bed_no, status) VALUES
((SELECT id FROM wards WHERE name='General Ward A'),'A-01','available'),
((SELECT id FROM wards WHERE name='General Ward A'),'A-02','available'),
((SELECT id FROM wards WHERE name='ICU 1'),'ICU-01','available');
