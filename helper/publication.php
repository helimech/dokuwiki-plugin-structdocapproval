<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\Extension\AuthPlugin;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class helper_plugin_structdocapproval_publication extends Plugin
{
    protected function db()
    {
        /** @var helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        return $helper ? $helper->getDB() : null;
    }

    public function normalizeSpec(string $spec): string
    {
        $tokens = preg_split('/\s*,\s*/', trim($spec), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $seen = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token !== '') $seen[$token] = true;
        }
        return implode(', ', array_keys($seen));
    }

    /**
     * Validate publication audiences before the workflow is committed.
     *
     * Acknowledge is intentionally restricted to authenticated users and @groups so
     * every requirement can be snapshotted to an account at publication time.
     */
    public function prepareAudience(string $notifySpec, string $acknowledgeSpec): array
    {
        $notifySpec = $this->normalizeSpec($notifySpec);
        $acknowledgeSpec = $this->normalizeSpec($acknowledgeSpec);

        return [
            'notify_spec' => $notifySpec,
            'acknowledge_spec' => $acknowledgeSpec,
            'required_users' => $this->resolveAcknowledgementUsers($acknowledgeSpec),
        ];
    }

    protected function resolveAcknowledgementUsers(string $spec): array
    {
        /** @var AuthPlugin $auth */
        global $auth;
        if ($spec === '') return [];
        if (!$auth) throw new RuntimeException('The authentication backend is unavailable.');

        $users = [];
        foreach (preg_split('/\s*,\s*/', $spec, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $token = trim($token);
            if ($token === '') continue;

            if ($token[0] === '@') {
                if (!$auth->canDo('getUsers')) {
                    throw new RuntimeException(
                        'The authentication backend cannot enumerate group members for acknowledgement: ' . $token
                    );
                }

                $group = substr($token, 1);
                $members = $auth->retrieveUsers(0, 5000, ['grps' => $group]) ?: [];
                foreach ($members as $login => $data) {
                    if (is_int($login) && is_array($data) && isset($data['user'])) $login = $data['user'];
                    if (is_string($login) && trim($login) !== '') $users[$login] = true;
                }
                continue;
            }

            $data = $auth->getUserData($token);
            if ($data) {
                $users[$token] = true;
                continue;
            }

            if (strpos($token, '@') !== false) {
                throw new RuntimeException(
                    'Acknowledge accepts DokuWiki users and @groups only, not literal email addresses: ' . $token
                );
            }

            throw new RuntimeException('Unknown acknowledgement user: ' . $token);
        }

        ksort($users, SORT_NATURAL | SORT_FLAG_CASE);
        return array_keys($users);
    }

    public function persistAudience(WorkflowRecord $record, array $audience): void
    {
        $db = $this->db();
        if (!$db) throw new RuntimeException('DocApproval database is unavailable.');

        $pid = $record->getPid();
        $publicationRevision = (int)$record->get('revision');
        if (!$publicationRevision) throw new RuntimeException('Published revision is unavailable.');

        $version = Constants::formatVersion((string)$record->get('version'));
        $publishedAt = strtotime((string)($record->get('published_at') ?: $record->get('datetime')));
        if (!$publishedAt) $publishedAt = time();

        $pdo = $db->getPdo();
        $started = !$pdo->inTransaction();
        if ($started) $pdo->beginTransaction();

        try {
            $db->query(
                'REPLACE INTO struct_docapproval_publications ' .
                '(pid,publication_revision,content_revision,version,notify_spec,acknowledge_spec,change_summary,published_by,published_at) ' .
                'VALUES (?,?,?,?,?,?,?,?,?)',
                [
                    $pid,
                    $publicationRevision,
                    $publicationRevision,
                    $version,
                    (string)($audience['notify_spec'] ?? ''),
                    (string)($audience['acknowledge_spec'] ?? ''),
                    (string)($audience['change_summary'] ?? ''),
                    (string)$record->get('published_by'),
                    $publishedAt,
                ]
            );

            $db->query(
                'DELETE FROM struct_docapproval_requirements WHERE pid=? AND publication_revision=?',
                [$pid, $publicationRevision]
            );

            foreach (($audience['required_users'] ?? []) as $user) {
                $db->query(
                    'INSERT OR IGNORE INTO struct_docapproval_requirements ' .
                    '(pid,publication_revision,user,required_at) VALUES (?,?,?,?)',
                    [$pid, $publicationRevision, $user, $publishedAt]
                );
            }

            if ($started) $pdo->commit();
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function getSnapshot(string $pid, int $publicationRevision): ?array
    {
        $db = $this->db();
        if (!$db || !$publicationRevision) return null;
        $rows = $db->queryAll(
            'SELECT * FROM struct_docapproval_publications WHERE pid=? AND publication_revision=? LIMIT 1',
            [cleanID($pid), $publicationRevision]
        ) ?: [];
        return $rows ? $rows[0] : null;
    }

    /**
     * Find the current release boundary. Minor edits do not establish a new formal
     * publication cycle. Initialize and Admin Override to Published deliberately
     * establish a boundary without automatically assigning an acknowledgement audience.
     */
    public function getCurrentPublicationRevision(string $pid): int
    {
        $db = $this->db();
        if (!$db) return 0;

        $rows = $db->queryAll(
            'SELECT col5 AS revision FROM data_struct_docapproval ' .
            'WHERE pid=? AND col1=? AND (col2=? OR col2=? OR col2=?) ' .
            'ORDER BY rid DESC LIMIT 1',
            [
                cleanID($pid),
                Constants::STATUS_PUBLISHED,
                Constants::ACTION_PUBLISH,
                Constants::ACTION_INITIALIZE,
                Constants::ACTION_ADMIN_OVERRIDE,
            ]
        ) ?: [];

        return $rows ? (int)$rows[0]['revision'] : 0;
    }

    public function getCurrentSnapshot(string $pid): ?array
    {
        $revision = $this->getCurrentPublicationRevision($pid);
        return $revision ? $this->getSnapshot($pid, $revision) : null;
    }

    public function getRequiredUsers(string $pid, int $publicationRevision): array
    {
        $db = $this->db();
        if (!$db || !$publicationRevision) return [];
        $rows = $db->queryAll(
            'SELECT user FROM struct_docapproval_requirements ' .
            'WHERE pid=? AND publication_revision=? ORDER BY user',
            [cleanID($pid), $publicationRevision]
        ) ?: [];
        return array_values(array_filter(array_map(static function ($row) {
            return (string)($row['user'] ?? '');
        }, $rows)));
    }

    public function isRequired(string $pid, int $publicationRevision, string $user): bool
    {
        if ($user === '' || !$publicationRevision) return false;
        $db = $this->db();
        if (!$db) return false;
        return (bool)$db->queryValue(
            'SELECT 1 FROM struct_docapproval_requirements ' .
            'WHERE pid=? AND publication_revision=? AND user=? LIMIT 1',
            [cleanID($pid), $publicationRevision, $user]
        );
    }

    public function getComplianceForPage(string $pid, ?int $publicationRevision = null): ?array
    {
        $db = $this->db();
        if (!$db) return null;

        $pid = cleanID($pid);
        if ($publicationRevision === null) {
            $publicationRevision = $this->getCurrentPublicationRevision($pid);
        }
        if (!$publicationRevision) return null;

        $snapshot = $this->getSnapshot($pid, $publicationRevision);
        if (!$snapshot) return null;

        $required = $this->getRequiredUsers($pid, $publicationRevision);
        $ackRows = $db->queryAll(
            'SELECT user,acknowledged_at FROM struct_docapproval_acknowledgements ' .
            'WHERE pid=? AND publication_revision=? ORDER BY user',
            [$pid, $publicationRevision]
        ) ?: [];

        $acknowledged = [];
        foreach ($ackRows as $ack) {
            $user = (string)($ack['user'] ?? '');
            if ($user !== '') $acknowledged[$user] = (int)($ack['acknowledged_at'] ?? 0);
        }

        $requiredSet = array_fill_keys($required, true);
        $completed = [];
        foreach ($acknowledged as $user => $ts) {
            if (isset($requiredSet[$user])) $completed[$user] = $ts;
        }

        return [
            'pid' => $pid,
            'publication_revision' => $publicationRevision,
            'version' => (string)$snapshot['version'],
            'published_at' => (int)$snapshot['published_at'],
            'notify_spec' => (string)$snapshot['notify_spec'],
            'acknowledge_spec' => (string)$snapshot['acknowledge_spec'],
            'required_users' => $required,
            'acknowledged_users' => $completed,
            'outstanding_users' => array_values(array_diff($required, array_keys($completed))),
        ];
    }

    public function getCurrentComplianceRows(): array
    {
        $db = $this->db();
        if (!$db) return [];

        $rows = $db->queryAll(
            'SELECT DISTINCT pid FROM struct_docapproval_publications ORDER BY pid'
        ) ?: [];
        $out = [];

        /** @var helper_plugin_structdocapproval_db $dbHelper */
        $dbHelper = plugin_load('helper', 'structdocapproval_db');

        foreach ($rows as $row) {
            $pid = cleanID((string)($row['pid'] ?? ''));
            if ($pid === '') continue;
            if ($dbHelper && !$dbHelper->isControlled($pid)) continue;

            $publicationRevision = $this->getCurrentPublicationRevision($pid);
            if (!$publicationRevision) continue;
            $compliance = $this->getComplianceForPage($pid, $publicationRevision);
            if ($compliance) $out[] = $compliance;
        }

        return $out;
    }

    public function getPendingForUser(string $user): array
    {
        $user = trim($user);
        if ($user === '') return [];

        $db = $this->db();
        if (!$db) return [];

        $rows = $db->queryAll(
            'SELECT r.pid,r.publication_revision,p.version,p.published_at ' .
            'FROM struct_docapproval_requirements r ' .
            'JOIN struct_docapproval_publications p ON p.pid=r.pid AND p.publication_revision=r.publication_revision ' .
            'LEFT JOIN struct_docapproval_acknowledgements a ON a.pid=r.pid AND a.publication_revision=r.publication_revision AND a.user=r.user ' .
            'WHERE r.user=? AND a.user IS NULL ORDER BY p.published_at DESC,r.pid',
            [$user]
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $pid = cleanID((string)$row['pid']);
            if ($this->getCurrentPublicationRevision($pid) !== (int)$row['publication_revision']) continue;
            $out[] = $row;
        }
        return $out;
    }

    public function getUserDisplayName(string $user): string
    {
        /** @var AuthPlugin $auth */
        global $auth;
        $user = trim($user);
        if ($user === '') return '';
        if ($auth) {
            $data = $auth->getUserData($user);
            if ($data && trim((string)($data['name'] ?? '')) !== '') {
                return trim((string)$data['name']);
            }
        }
        return $user;
    }

    /**
     * Lightweight browser-autocomplete candidates for publication fields.
     */
    public function getPrincipalSuggestions(): array
    {
        /** @var AuthPlugin $auth */
        global $auth;
        if (!$auth || !$auth->canDo('getUsers')) return [];

        $users = $auth->retrieveUsers(0, 5000, []) ?: [];
        $items = [];
        foreach ($users as $login => $data) {
            if (is_int($login) && is_array($data) && isset($data['user'])) $login = $data['user'];
            if (is_string($login) && $login !== '') $items[$login] = true;
            foreach ((array)($data['grps'] ?? []) as $group) {
                $group = trim((string)$group);
                if ($group !== '') $items['@' . $group] = true;
            }
        }

        uksort($items, 'strnatcasecmp');
        return array_keys($items);
    }
}
