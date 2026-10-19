ALTER TABLE struct_docapproval_reminders
    ADD COLUMN reminder_type TEXT NOT NULL DEFAULT 'manual';

ALTER TABLE struct_docapproval_reminders
    ADD COLUMN cc_spec TEXT NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS struct_docapproval_auto_reminder_runs (
    run_date TEXT PRIMARY KEY,
    token TEXT NOT NULL,
    started_at INTEGER NOT NULL DEFAULT 0,
    finished_at INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'running',
    detail TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_reminders_type
    ON struct_docapproval_reminders(reminder_type, sent_at);
