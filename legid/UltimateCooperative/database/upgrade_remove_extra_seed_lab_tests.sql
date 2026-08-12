DELETE FROM lab_tests
WHERE name IN (
  'WBC',
  'PCV',
  'Haemoglobin',
  'Fasting Blood Sugar',
  'Random Blood Sugar',
  'HIV Screening',
  'HBsAg',
  'HCV',
  'Widal Reaction',
  'Stool Microscopy',
  'Urine Pregnancy Test'
);
