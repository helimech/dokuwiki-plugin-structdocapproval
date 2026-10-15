CREATE TABLE IF NOT EXISTS struct_docapproval_publications (
    pid TEXT NOT NULL,
    publication_revision INTEGER NOT NULL,
    content_revision INTEGER NOT NULL,
    version TEXT NOT NULL DEFAULT '',
    notify_spec TEXT NOT NULL DEFAULT '',
    acknowledge_spec TEXT NOT NULL DEFAULT '',
    published_by TEXT NOT NULL DEFAULT '',
    published_at INTEGER NOT NULL,
    PRIMARY KEY (pid, publication_revision)
);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_publications_pid
    ON struct_docapproval_publications(pid, publication_revision);

CREATE TABLE IF NOT EXISTS struct_docapproval_requirements (
    pid TEXT NOT NULL,
    publication_revision INTEGER NOT NULL,
    user TEXT NOT NULL,
    required_at INTEGER NOT NULL,
    PRIMARY KEY (pid, publication_revision, user)
);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_requirements_user
    ON struct_docapproval_requirements(user, pid, publication_revision);
