ALTER TABLE struct_docapproval_rules
    ADD COLUMN reviewer2 TEXT NOT NULL DEFAULT '';

ALTER TABLE struct_docapproval_pages
    ADD COLUMN reviewer2 TEXT NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS struct_docapproval_review_cycles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pid TEXT NOT NULL,
    workflow_revision INTEGER NOT NULL DEFAULT 0,
    reviewer1 TEXT NOT NULL,
    reviewer2 TEXT NOT NULL DEFAULT '',
    started_by TEXT NOT NULL DEFAULT '',
    started_at INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_review_cycles_current
    ON struct_docapproval_review_cycles(pid, active, id);
