-- Development-only seed data.
-- These intentionally fake entries are about pet squash, not dogs.
-- Matching fake images are tracked in dev-data/uploads/dogs/.

DELETE FROM dogs
WHERE owner_email LIKE '%@example.test';

INSERT INTO dogs (
  dog_name,
  description,
  photo_filename,
  owner_name,
  owner_email,
  neighborhood,
  status,
  created_at,
  updated_at
) VALUES
  (
    'FAKE Pet Squash: Zucchini',
    'A long green pet squash who enjoys sunny windowsills and pretending to fetch.',
    'zucchini.jpg',
    'Alex Testgarden',
    'zucchini-owner@example.test',
    'Nob Hill',
    'approved',
    '2026-01-01T12:00:00Z',
    '2026-01-01T12:00:00Z'
  ),
  (
    'FAKE Pet Squash: Butternut',
    'A mellow tan pet squash with a sweet personality and excellent couch presence.',
    'butternut.jpg',
    'Sam Seedwell',
    'butternut-owner@example.test',
    'North Valley',
    'approved',
    '2026-01-02T12:00:00Z',
    '2026-01-02T12:00:00Z'
  ),
  (
    'FAKE Pet Squash: Yellow Crookneck',
    'A bright yellow pet squash who wiggles dramatically when admired.',
    'yellow.jpg',
    'Riley Mockroot',
    'yellow-owner@example.test',
    'Barelas',
    'approved',
    '2026-01-03T12:00:00Z',
    '2026-01-03T12:00:00Z'
  ),
  (
    'FAKE Pet Squash: Acorn',
    'A compact pet squash with a serious expression and a fondness for blankets.',
    'acorn.jpg',
    'Jordan Placeholder',
    'acorn-owner@example.test',
    'Downtown',
    'pending',
    '2026-01-04T12:00:00Z',
    '2026-01-04T12:00:00Z'
  ),
  (
    'FAKE Pet Squash: Halloween',
    'A festive pet squash submitted for moderation testing and not yet public.',
    'halloween.jpeg',
    'Casey Fakedata',
    'halloween-owner@example.test',
    'University Heights',
    'rejected',
    '2026-01-05T12:00:00Z',
    '2026-01-05T12:00:00Z'
  ),
  (
    'FAKE Pet Squash: Pumpkin',
    'A round orange pet squash who believes every walk should end with pie.',
    'pumpkin.jpg',
    'Morgan Devpatch',
    'pumpkin-owner@example.test',
    'Ridgecrest',
    'approved',
    '2026-01-06T12:00:00Z',
    '2026-01-06T12:00:00Z'
  );
