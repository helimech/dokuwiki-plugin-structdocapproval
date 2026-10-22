<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class helper_plugin_structdocapproval_acknowledge extends Plugin
{
    protected function db()
    {
        /** @var helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        return $helper ? $helper->getDB() : null;
    }

    public function getCurrentRequirementRevision(string $pid): int
    {
        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        if (!$publication) return 0;

        $revision = $publication->getCurrentPublicationRevision($pid);
        if (!$revision || !$publication->getSnapshot($pid, $revision)) return 0;
        return $revision;
    }

    public function isRequired(string $pid, int $publicationRevision, string $user): bool
    {
        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        return $publication && $publication->isRequired($pid, $publicationRevision, $user);
    }

    public function getAcknowledgement(string $pid, string $user, int $publicationRevision): ?array
    {
        $db = $this->db();
        if (!$db || $user === '' || !$publicationRevision) return null;
        $rows = $db->queryAll(
            'SELECT * FROM struct_docapproval_acknowledgements ' .
            'WHERE pid=? AND user=? AND publication_revision=? LIMIT 1',
            [cleanID($pid), $user, $publicationRevision]
        ) ?: [];
        return $rows ? $rows[0] : null;
    }

    public function getLatestAcknowledgement(string $pid, string $user, ?int $beforePublicationRevision = null): ?array
    {
        $db = $this->db();
        if (!$db || $user === '') return null;
        $sql = 'SELECT * FROM struct_docapproval_acknowledgements WHERE pid=? AND user=?';
        $args = [cleanID($pid), $user];
        if ($beforePublicationRevision !== null) {
            $sql .= ' AND publication_revision < ?';
            $args[] = $beforePublicationRevision;
        }
        $sql .= ' ORDER BY publication_revision DESC, acknowledged_at DESC LIMIT 1';
        $rows = $db->queryAll($sql, $args) ?: [];
        return $rows ? $rows[0] : null;
    }

    public function acknowledge(
        string $pid,
        string $user,
        int $publicationRevision,
        int $contentRevision,
        array $groups = []
    ): array {
        $pid = cleanID($pid);
        if ($user === '') throw new RuntimeException('You must be logged in to acknowledge a document.');
        if (auth_quickaclcheck($pid) < AUTH_READ) throw new RuntimeException('You may not read this page.');
        $currentRequirement = $this->getCurrentRequirementRevision($pid);
        if (!$currentRequirement || !$this->isRequired($pid, $currentRequirement, $user)) {
            throw new RuntimeException('You are not required to acknowledge this publication.');
        }
        $currentPublished = WorkflowRecord::latestPublished($pid);
        if (
            !$currentRequirement ||
            !$currentPublished ||
            $currentRequirement !== $publicationRevision ||
            (int)$currentPublished->get('revision') !== $contentRevision
        ) {
            throw new RuntimeException('The Published revision changed. Reload the page before acknowledging.');
        }

        $existing = $this->getAcknowledgement($pid, $user, $publicationRevision);
        if ($existing) return $existing;

        $version = Constants::formatVersion((string)$currentPublished->get('version'));
        $now = time();
        $db = $this->db();
        if (
            !$db || !$db->query(
                'INSERT OR IGNORE INTO struct_docapproval_acknowledgements ' .
                '(pid,user,publication_revision,content_revision,version,acknowledged_at) VALUES (?,?,?,?,?,?)',
                [$pid, $user, $publicationRevision, $contentRevision, $version, $now]
            )
        ) {
            throw new RuntimeException('Could not record the acknowledgement.');
        }

        // The primary key makes acknowledgement idempotent. If two browser requests
        // race, one insert wins and the other simply returns the already-stored row
        // instead of showing a false failure to the user.
        $stored = $this->getAcknowledgement($pid, $user, $publicationRevision);
        if (!$stored) throw new RuntimeException('Could not verify the acknowledgement record.');
        return $stored;
    }
}
