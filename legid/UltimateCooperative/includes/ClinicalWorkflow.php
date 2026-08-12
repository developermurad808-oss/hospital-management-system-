<?php
declare(strict_types=1);

final class ClinicalWorkflow
{
    public static function saveConsultation(array $data, array $user): string
    {
        $visit = Database::query('SELECT * FROM visits WHERE id=?', [$data['visit_id']])->fetch();
        if (!$visit) {
            throw new RuntimeException('Visit not found.');
        }

        Database::query(
            'INSERT INTO emr_records (visit_id, patient_id, doctor_id, symptoms, diagnosis, treatment_plan, progress_notes) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$data['visit_id'], $visit['patient_id'], $user['id'], $data['symptoms'] ?? '', $data['diagnosis'] ?? '', $data['treatment_plan'] ?? '', $data['progress_notes'] ?? '']
        );

        $hasMedicine = false;
        if (!empty($data['medicine_name']) && is_array($data['medicine_name'])) {
            Database::query('INSERT INTO prescriptions (visit_id, patient_id, doctor_id, notes) VALUES (?, ?, ?, ?)', [$data['visit_id'], $visit['patient_id'], $user['id'], $data['rx_notes'] ?? '']);
            $rxId = Database::connection()->lastInsertId();
            foreach ($data['medicine_name'] as $i => $medicine) {
                if (trim((string) $medicine) === '') {
                    continue;
                }
                $hasMedicine = true;
                Database::query(
                    'INSERT INTO prescription_items (prescription_id, medicine_name, dosage, frequency, duration, quantity) VALUES (?, ?, ?, ?, ?, ?)',
                    [$rxId, $medicine, $data['dosage'][$i] ?? '', $data['frequency'][$i] ?? '', $data['duration'][$i] ?? '', (int) ($data['quantity'][$i] ?? 1)]
                );
            }
        }

        $hasLab = false;
        if (!empty($data['lab_test_id']) && is_array($data['lab_test_id'])) {
            $hasLab = true;
            Database::query('INSERT INTO lab_requests (request_no, visit_id, patient_id, doctor_id) VALUES (?, ?, ?, ?)', [next_number('LAB', 'lab_requests', 'request_no'), $data['visit_id'], $visit['patient_id'], $user['id']]);
            $labId = Database::connection()->lastInsertId();
            foreach ($data['lab_test_id'] as $testId) {
                Database::query('INSERT INTO lab_request_items (lab_request_id, lab_test_id) VALUES (?, ?)', [$labId, $testId]);
            }
        }

        self::createInvoice($visit, $data, $user, $hasLab, $hasMedicine);

        $nextStatus = $hasLab ? 'lab' : ($hasMedicine ? 'pharmacy' : 'billing');
        Database::query('UPDATE visits SET status=? WHERE id=?', [$nextStatus, $data['visit_id']]);
        Auth::audit('consultation_saved', 'visits', (int) $data['visit_id']);

        return $nextStatus;
    }

    private static function createInvoice(array $visit, array $data, array $user, bool $hasLab, bool $hasMedicine): void
    {
        Database::query('INSERT INTO invoices (invoice_no, patient_id, visit_id, created_by) VALUES (?, ?, ?, ?)', [next_number('INV', 'invoices', 'invoice_no'), $visit['patient_id'], $visit['id'], $user['id']]);
        $invoiceId = Database::connection()->lastInsertId();
        Database::query('INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price) VALUES (?, ?, ?, 1, ?)', [$invoiceId, 'consultation', 'Doctor consultation', (float) ($data['consultation_fee'] ?? 0)]);

        if ($hasLab) {
            foreach ($data['lab_test_id'] as $testId) {
                $test = Database::query('SELECT name, price FROM lab_tests WHERE id=?', [$testId])->fetch();
                if ($test) {
                    Database::query('INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price) VALUES (?, ?, ?, 1, ?)', [$invoiceId, 'lab', 'Lab: ' . $test['name'], $test['price']]);
                }
            }
        }

        if ($hasMedicine) {
            foreach ($data['medicine_name'] as $i => $medicine) {
                if (trim((string) $medicine) === '') {
                    continue;
                }
                $qty = (int) ($data['quantity'][$i] ?? 1);
                $known = Database::query('SELECT selling_price FROM medicines WHERE name LIKE ? LIMIT 1', [$medicine])->fetch();
                $price = $known ? (float) $known['selling_price'] : 0;
                Database::query('INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price) VALUES (?, ?, ?, ?, ?)', [$invoiceId, 'medicine', 'Medicine: ' . $medicine, $qty, $price]);
            }
        }
    }
}
