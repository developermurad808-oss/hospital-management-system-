INSERT INTO hospital_profiles (name, address, phone, email, logo_path)
SELECT 'Demo City Hospital', '12 Wellness Avenue, Lagos', '+234 800 000 0000', 'hello@democityhospital.test', NULL
WHERE NOT EXISTS (SELECT 1 FROM hospital_profiles);

INSERT IGNORE INTO users (role_id, name, email, phone, password_hash) VALUES
((SELECT id FROM roles WHERE slug='super_admin'), 'Demo Owner', 'owner@hospital.test', '08000000001', '$2y$10$AmVkcM7QN692LLc4EJ9GUeCaShFBVYwpE9w4qdU/lG8Drig8kqHX.'),
((SELECT id FROM roles WHERE slug='doctor'), 'Dr. Ada Okafor', 'doctor@hospital.test', '08000000002', '$2y$10$AmVkcM7QN692LLc4EJ9GUeCaShFBVYwpE9w4qdU/lG8Drig8kqHX.'),
((SELECT id FROM roles WHERE slug='nurse'), 'Nurse Musa Bello', 'nurse@hospital.test', '08000000003', '$2y$10$AmVkcM7QN692LLc4EJ9GUeCaShFBVYwpE9w4qdU/lG8Drig8kqHX.'),
((SELECT id FROM roles WHERE slug='pharmacist'), 'Grace Pharmacy', 'pharmacy@hospital.test', '08000000004', '$2y$10$AmVkcM7QN692LLc4EJ9GUeCaShFBVYwpE9w4qdU/lG8Drig8kqHX.'),
((SELECT id FROM roles WHERE slug='lab_technician'), 'Tunde Lab', 'lab@hospital.test', '08000000005', '$2y$10$AmVkcM7QN692LLc4EJ9GUeCaShFBVYwpE9w4qdU/lG8Drig8kqHX.'),
((SELECT id FROM roles WHERE slug='accountant'), 'Mary Accounts', 'accounts@hospital.test', '08000000006', '$2y$10$AmVkcM7QN692LLc4EJ9GUeCaShFBVYwpE9w4qdU/lG8Drig8kqHX.');

INSERT IGNORE INTO staff_profiles (user_id, department_id, employee_no, specialization, qualification, hire_date, salary)
SELECT u.id, d.id, CONCAT('EMP-2026-', LPAD(u.id, 5, '0')), 'General Practice', 'MBBS', '2026-01-10', 450000
FROM users u JOIN departments d ON d.name='General Medicine'
WHERE u.email='doctor@hospital.test';

INSERT IGNORE INTO patients (patient_no, first_name, last_name, gender, date_of_birth, blood_group, genotype, allergies, phone, email, address, emergency_name, emergency_phone) VALUES
('PAT-2026-00001', 'Amina', 'Bello', 'female', '1994-04-12', 'O+', 'AS', 'Penicillin', '08010000001', 'amina@example.test', 'Ikeja, Lagos', 'Sani Bello', '08010000002'),
('PAT-2026-00002', 'Chinedu', 'Nwosu', 'male', '1988-09-21', 'A+', 'SS', 'None', '08010000003', 'chinedu@example.test', 'Surulere, Lagos', 'Ngozi Nwosu', '08010000004');

INSERT IGNORE INTO visits (visit_no, patient_id, doctor_id, status, priority)
SELECT 'VIS-2026-00001', p.id, u.id, 'queue', 'normal'
FROM patients p JOIN users u ON u.email='doctor@hospital.test'
WHERE p.patient_no='PAT-2026-00001';

INSERT IGNORE INTO appointments (appointment_no, patient_id, doctor_id, department_id, appointment_at, reason, status)
SELECT 'APT-2026-00001', p.id, u.id, d.id, DATE_ADD(NOW(), INTERVAL 1 DAY), 'Follow-up consultation', 'booked'
FROM patients p JOIN users u ON u.email='doctor@hospital.test' JOIN departments d ON d.name='General Medicine'
WHERE p.patient_no='PAT-2026-00002';
