<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
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

    public function isAssigned(string $pid, string $user = '', array $groups = []): bool
    {
        $pid = cleanID($pid);
        $assignments = Assignments::getInstance();
        if (!$assignments->isControlled($pid)) return false;
        return $assignments->userHasRole($pid, Constants::ROLE_ACKNOWLEDGE, $user, $groups);
    }

    /**
     * Return the DokuWiki revision that created the current acknowledgement requirement.
     *
     * Normal Publish and initial baseline publication create a new requirement. A
     * Published-preserving Minor Edit does not: the displayed content revision can move
     * forward while the formal acknowledgement requirement remains tied to the same
     * publication cycle.
     */
    public function getCurrentRequirementRevision(string $pid): int
    {
        $db = $this->db();
        if (!$db) return 0;
        $row = $db->queryAll(
            'SELECT col5 AS revision FROM data_struct_docapproval ' .
            'WHERE pid=? AND col1=? AND (col2=? OR col2=?) ' .
            'ORDER BY rid DESC LIMIT 1',
            [
                cleanID($pid),
                Constants::STATUS_PUBLISHED,
                Constants::ACTION_PUBLISH,
                Constants::ACTION_INITIALIZE,
            ]
        ) ?: [];
        if ($row) return (int)$row[0]['revision'];

        $published = WorkflowRecord::latestPublished($pid);
        return $published ? (int)$published->get('revision') : 0;
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
        if (!$this->isAssigned($pid, $user, $groups)) {
            throw new RuntimeException('You are not assigned to acknowledge this document.');
        }

        $currentRequirement = $this->getCurrentRequirementRevision($pid);
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
        if (!$db || !$db->query(
            'INSERT INTO struct_docapproval_acknowledgements ' .
            '(pid,user,publication_revision,content_revision,version,acknowledged_at) VALUES (?,?,?,?,?,?)',
            [$pid, $user, $publicationRevision, $contentRevision, $version, $now]
        )) {
            throw new RuntimeException('Could not record the acknowledgement.');
        }

        return [
            'pid' => $pid,
            'user' => $user,
            'publication_revision' => $publicationRevision,
            'content_revision' => $contentRevision,
            'version' => $version,
            'acknowledged_at' => $now,
        ];
    }
}
