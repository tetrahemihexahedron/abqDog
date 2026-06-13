PRAGMA journal_mode = WAL;

CREATE TABLE dogs (
  id INTEGER PRIMARY KEY,
  dog_name TEXT NOT NULL,
  description TEXT NOT NULL,
  photo_filename TEXT NOT NULL,
  owner_name TEXT NOT NULL,
  owner_email TEXT NOT NULL,
  neighborhood TEXT,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

CREATE INDEX idx_dogs_status ON dogs(status);
CREATE INDEX idx_dogs_created_at ON dogs(created_at);
