<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class helper_plugin_structdocapproval_compliance extends Plugin
{
    protected function db()
    {
        /** @var helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        return $helper ? $helper->getDB() : null;
    }

    /**
     * Build a management compliance report from immutable publication snapshots.
     *
     * Filters:
     * - scope: current|all
     * - page: case-insensitive page/namespace substring
     * - user: exact DokuWiki login that was required for the release
     * - status: all|outstanding|overdue|complete|not_required
     * - date_from/date_to: YYYY-MM-DD, inclusive
     */
    public function getReport(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $empty = [
            'filters' => $filters,
            'publications' => [],
            'users' => [],
            'totals' => [
                'publications' => 0,
                'users' => 0,
                'required' => 0,
                'acknowledged' => 0,
                'outstanding' => 0,
                'overdue' => 0,
            ],
        ];

        $db = $this->db();
        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        /** @var helper_plugin_structdocapproval_db $dbHelper */
        $dbHelper = plugin_load('helper', 'structdocapproval_db');
        if (!$db || !$publication || !$dbHelper) return $empty;

        $rows = $db->queryAll(
            'SELECT * FROM struct_docapproval_publications ORDER BY published_at DESC,pid ASC,publication_revision DESC'
        ) ?: [];

        $currentRevisionCache = [];
        $latestPublishedCache = [];
        $titleCache = [];
        $controlledCache = [];
        $now = time();
        $reminderMap = $this->loadReminderSummaries($db);
        $out = [];

        foreach ($rows as $snapshot) {
            $pid = cleanID((string)($snapshot['pid'] ?? ''));
            $publicationRevision = (int)($snapshot['publication_revision'] ?? 0);
            if ($pid === '' || !$publicationRevision) continue;
            if (auth_quickaclcheck($pid) < AUTH_READ) continue;

            if (!array_key_exists($pid, $currentRevisionCache)) {
                $currentRevisionCache[$pid] = $publication->getCurrentPublicationRevision($pid);
            }
            $isCurrent = $currentRevisionCache[$pid] === $publicationRevision;

            if (!array_key_exists($pid, $controlledCache)) {
                $controlledCache[$pid] = $dbHelper->isControlled($pid);
            }
            $isControlled = $controlledCache[$pid];

            if ($filters['scope'] === 'current') {
                if (!$isCurrent) continue;
                // Match the existing Admin current-compliance semantics: once a page
                // is no longer controlled, its evidence remains historical but it no
                // longer counts as a current DocApproval obligation.
                if (!$isControlled) continue;
            }

            if (!array_key_exists($pid, $titleCache)) {
                $titleCache[$pid] = $this->pageTitle($pid);
            }
            $title = $titleCache[$pid];

            if (
                $filters['page'] !== '' &&
                stripos($pid, $filters['page']) === false &&
                stripos($title, $filters['page']) === false
            ) {
                continue;
            }

            $publishedAt = (int)($snapshot['published_at'] ?? 0);
            if ($filters['date_from_ts'] && $publishedAt < $filters['date_from_ts']) continue;
            if ($filters['date_to_ts'] && $publishedAt > $filters['date_to_ts']) continue;

            $compliance = $publication->getComplianceForPage($pid, $publicationRevision, $snapshot);
            if (!$compliance) continue;

            $requiredUsers = (array)$compliance['required_users'];
            $acknowledgedUsers = (array)$compliance['acknowledged_users'];
            $outstandingUsers = (array)$compliance['outstanding_users'];
            $dueAt = (int)($snapshot['ack_due_at'] ?? 0);
            $isOverdue = $dueAt > 0 && $now > $dueAt && count($outstandingUsers) > 0;

            if ($filters['user'] !== '') {
                if (!in_array($filters['user'], $requiredUsers, true)) continue;
                $userComplete = array_key_exists($filters['user'], $acknowledgedUsers);
                if ($filters['status'] === 'outstanding' && $userComplete) continue;
                if ($filters['status'] === 'overdue' && ($userComplete || !$isOverdue)) continue;
                if ($filters['status'] === 'complete' && !$userComplete) continue;
                if ($filters['status'] === 'not_required') continue;
            } else {
                $requiredCount = count($requiredUsers);
                $outstandingCount = count($outstandingUsers);
                if ($filters['status'] === 'outstanding' && $outstandingCount === 0) continue;
                if ($filters['status'] === 'overdue' && !$isOverdue) continue;
                if ($filters['status'] === 'complete' && ($requiredCount === 0 || $outstandingCount !== 0)) continue;
                if ($filters['status'] === 'not_required' && $requiredCount !== 0) continue;
            }

            $viewRevision = (int)($snapshot['content_revision'] ?? $publicationRevision);
            if ($isCurrent) {
                if (!array_key_exists($pid, $latestPublishedCache)) {
                    $latest = WorkflowRecord::latestPublished($pid);
                    $latestPublishedCache[$pid] = $latest ? (int)$latest->get('revision') : 0;
                }
                if ($latestPublishedCache[$pid]) $viewRevision = $latestPublishedCache[$pid];
            }

            $out[] = [
                'pid' => $pid,
                'title' => $title,
                'publication_revision' => $publicationRevision,
                'content_revision' => (int)($snapshot['content_revision'] ?? $publicationRevision),
                'view_revision' => $viewRevision,
                'version' => (string)($snapshot['version'] ?? ''),
                'published_by' => (string)($snapshot['published_by'] ?? ''),
                'published_at' => $publishedAt,
                'ack_due_at' => $dueAt,
                'is_overdue' => $isOverdue,
                'notify_spec' => (string)($snapshot['notify_spec'] ?? ''),
                'acknowledge_spec' => (string)($snapshot['acknowledge_spec'] ?? ''),
                'change_summary' => (string)($snapshot['change_summary'] ?? ''),
                'is_current' => $isCurrent,
                'is_controlled' => $isControlled,
                'required_users' => $requiredUsers,
                'acknowledged_users' => $acknowledgedUsers,
                'outstanding_users' => $outstandingUsers,
                'reminder' => $reminderMap[$pid . '|' . $publicationRevision] ?? [
                    'batches' => 0,
                    'deliveries' => 0,
                    'last_sent_at' => 0,
                    'last_sent_by' => '',
                    'last_type' => '',
                    'last_cc_spec' => '',
                ],
            ];
        }

        $users = $this->aggregateUsers($out, $filters['user'], $publication);
        $totals = $this->aggregateTotals($out, $users, $filters['user']);

        return [
            'filters' => $filters,
            'publications' => $out,
            'users' => $users,
            'totals' => $totals,
        ];
    }

    protected function loadReminderSummaries($db): array
    {
        $rows = $db->queryAll(
            'SELECT id,batch_id,pid,publication_revision,sent_by,sent_at,reminder_type,cc_spec ' .
            'FROM struct_docapproval_reminders ORDER BY sent_at DESC,id DESC'
        ) ?: [];

        $out = [];
        $batchSets = [];
        foreach ($rows as $row) {
            $key = (string)$row['pid'] . '|' . (int)$row['publication_revision'];
            if (!isset($out[$key])) {
                $out[$key] = [
                    'batches' => 0,
                    'deliveries' => 0,
                    'last_sent_at' => (int)$row['sent_at'],
                    'last_sent_by' => (string)$row['sent_by'],
                    'last_type' => (string)($row['reminder_type'] ?? ''),
                    'last_cc_spec' => (string)($row['cc_spec'] ?? ''),
                ];
                $batchSets[$key] = [];
            }
            $out[$key]['deliveries']++;
            $batchSets[$key][(string)$row['batch_id']] = true;
        }

        foreach ($out as $key => &$summary) {
            $summary['batches'] = count($batchSets[$key]);
        }
        unset($summary);

        return $out;
    }

    protected function pageTitle(string $pid): string
    {
        $title = '';
        if (function_exists('p_get_first_heading')) {
            $title = trim((string)p_get_first_heading($pid));
        }
        return $title !== '' ? $title : $pid;
    }

    public function getUserSuggestions(): array
    {
        $db = $this->db();
        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        if (!$db || !$publication) return [];

        $rows = $db->queryAll(
            'SELECT DISTINCT user FROM struct_docapproval_requirements ORDER BY user'
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $user = trim((string)($row['user'] ?? ''));
            if ($user === '') continue;
            $out[] = [
                'user' => $user,
                'name' => $publication->getUserDisplayName($user),
            ];
        }
        return $out;
    }

    protected function normalizeFilters(array $filters): array
    {
        $scope = (string)($filters['scope'] ?? 'current');
        if (!in_array($scope, ['current', 'all'], true)) $scope = 'current';

        $status = (string)($filters['status'] ?? 'all');
        if (!in_array($status, ['all', 'outstanding', 'overdue', 'complete', 'not_required'], true)) {
            $status = 'all';
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));

        $dateFromTs = $this->dateBoundary($dateFrom, false);
        $dateToTs = $this->dateBoundary($dateTo, true);

        return [
            'scope' => $scope,
            'page' => trim((string)($filters['page'] ?? '')),
            'user' => trim((string)($filters['user'] ?? '')),
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_from_ts' => $dateFromTs,
            'date_to_ts' => $dateToTs,
        ];
    }

    protected function dateBoundary(string $value, bool $endOfDay): int
    {
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return 0;
        $ts = strtotime($value . ($endOfDay ? ' 23:59:59' : ' 00:00:00'));
        return $ts ?: 0;
    }

    protected function aggregateUsers(
        array $publications,
        string $onlyUser,
        helper_plugin_structdocapproval_publication $publication
    ): array {
        $stats = [];

        foreach ($publications as $row) {
            foreach ($row['required_users'] as $user) {
                if ($onlyUser !== '' && $user !== $onlyUser) continue;
                if (!isset($stats[$user])) {
                    $stats[$user] = [
                        'user' => $user,
                        'name' => $publication->getUserDisplayName($user),
                        'required' => 0,
                        'acknowledged' => 0,
                        'outstanding' => 0,
                        'overdue' => 0,
                    ];
                }
                $stats[$user]['required']++;
                if (array_key_exists($user, $row['acknowledged_users'])) {
                    $stats[$user]['acknowledged']++;
                } else {
                    $stats[$user]['outstanding']++;
                    if (!empty($row['is_overdue'])) $stats[$user]['overdue']++;
                }
            }
        }

        $out = array_values($stats);
        usort($out, static function (array $a, array $b): int {
            $cmp = ((int)($b['overdue'] ?? 0)) <=> ((int)($a['overdue'] ?? 0));
            if ($cmp !== 0) return $cmp;
            $cmp = ((int)$b['outstanding']) <=> ((int)$a['outstanding']);
            if ($cmp !== 0) return $cmp;
            $cmp = ((int)$b['required']) <=> ((int)$a['required']);
            if ($cmp !== 0) return $cmp;
            return strnatcasecmp((string)$a['name'], (string)$b['name']);
        });

        return $out;
    }

    protected function aggregateTotals(array $publications, array $users, string $onlyUser): array
    {
        $required = 0;
        $acknowledged = 0;
        $outstanding = 0;
        $overdue = 0;

        foreach ($publications as $row) {
            if ($onlyUser !== '') {
                if (!in_array($onlyUser, $row['required_users'], true)) continue;
                $required++;
                if (array_key_exists($onlyUser, $row['acknowledged_users'])) {
                    $acknowledged++;
                } else {
                    $outstanding++;
                    if (!empty($row['is_overdue'])) $overdue++;
                }
                continue;
            }

            $required += count($row['required_users']);
            $acknowledged += count($row['acknowledged_users']);
            $outstanding += count($row['outstanding_users']);
            if (!empty($row['is_overdue'])) $overdue += count($row['outstanding_users']);
        }

        return [
            'publications' => count($publications),
            'users' => count($users),
            'required' => $required,
            'acknowledged' => $acknowledged,
            'outstanding' => $outstanding,
            'overdue' => $overdue,
        ];
    }
}
