<?php

use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;

class syntax_plugin_structdocapproval_dashboard extends DokuWiki_Syntax_Plugin
{
    public function getType() { return 'substition'; }
    public function getPType() { return 'block'; }
    public function getSort() { return 159; }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern(
            '----+ *struct[_]?docapproval[_]?dashboard *-+\n.*?\n?----+',
            $mode,
            'plugin_structdocapproval_dashboard'
        );
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        return [];
    }

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode !== 'xhtml') return false;
        if (method_exists($renderer, 'nocache')) $renderer->nocache();

        global $INPUT, $ID;

        $user = trim($INPUT->server->str('REMOTE_USER'));
        if ($user === '') {
            $renderer->doc .= '<div class="plugin-structdocapproval-dashboard">' .
                '<h2>' . hsc($this->getLang('dashboard_title')) . '</h2>' .
                '<p class="docapproval-warning">' . hsc($this->getLang('my_login_required')) . '</p></div>';
            return true;
        }

        $manager = auth_ismanager();
        $allowed = $manager
            ? ['my', 'documents', 'compliance', 'history']
            : ['my'];

        $view = trim($INPUT->str('docapproval_dash_view'));
        if (!in_array($view, $allowed, true)) $view = 'my';

        /** @var helper_plugin_structdocapproval_tasks $tasksHelper */
        $tasksHelper = plugin_load('helper', 'structdocapproval_tasks');
        /** @var helper_plugin_structdocapproval_dashboard $dashboardHelper */
        $dashboardHelper = plugin_load('helper', 'structdocapproval_dashboard');

        $taskCount = 0;
        if ($tasksHelper) {
            $tasks = $tasksHelper->getCurrentUserTasks();
            $taskCount = count($tasks['acknowledgements']) +
                count($tasks['reviews']) +
                count($tasks['training']) +
                count($tasks['publish']);
        }

        $overview = $manager && $dashboardHelper
            ? $dashboardHelper->getOverview()
            : ['documents' => 0, 'ack_outstanding' => 0];

        $renderer->doc .= '<div class="plugin-structdocapproval-dashboard">';
        $renderer->doc .= '<div class="docapproval-dashboard-head">';
        $renderer->doc .= '<h2>' . hsc($this->getLang('dashboard_title')) . '</h2>';
        $renderer->doc .= '<p>' . hsc($this->getLang('dashboard_intro')) . '</p>';
        $renderer->doc .= '</div>';

        $renderer->doc .= $this->renderTabs($ID, $view, $allowed, $taskCount, $overview);

        if ($view === 'my') {
            $this->renderEmbedded($renderer, 'structdocapproval_my');
        } elseif ($view === 'documents') {
            $renderer->doc .= $this->renderDocuments($ID, $dashboardHelper);
        } elseif ($view === 'compliance') {
            $this->renderEmbedded($renderer, 'structdocapproval_compliance');
        } elseif ($view === 'history') {
            $this->renderEmbedded($renderer, 'structdocapproval_history');
        }

        $renderer->doc .= '</div>';
        return true;
    }

    protected function renderTabs(
        string $pageId,
        string $active,
        array $allowed,
        int $taskCount,
        array $overview
    ): string {
        $labels = [
            'my' => $this->getLang('dashboard_tab_my'),
            'documents' => $this->getLang('dashboard_tab_documents'),
            'compliance' => $this->getLang('dashboard_tab_compliance'),
            'history' => $this->getLang('dashboard_tab_history'),
        ];
        $counts = [
            'my' => $taskCount,
            'documents' => (int)($overview['documents'] ?? 0),
            'compliance' => (int)($overview['ack_outstanding'] ?? 0),
            'history' => null,
        ];

        $html = '<nav class="docapproval-dashboard-tabs" aria-label="' .
            hsc($this->getLang('dashboard_title')) . '">';
        foreach ($allowed as $view) {
            $class = $view === $active ? ' active' : '';
            $url = wl($pageId, ['docapproval_dash_view' => $view]);
            $html .= '<a class="docapproval-dashboard-tab' . $class . '" href="' . hsc($url) . '">';
            $html .= '<span>' . hsc($labels[$view]) . '</span>';
            if ($counts[$view] !== null) {
                $html .= '<strong>' . (int)$counts[$view] . '</strong>';
            }
            $html .= '</a>';
        }
        return $html . '</nav>';
    }

    protected function renderEmbedded(Doku_Renderer $renderer, string $component): void
    {
        $plugin = plugin_load('syntax', $component);
        if (!$plugin || !method_exists($plugin, 'render')) {
            $renderer->doc .= '<p class="docapproval-warning">' .
                hsc($this->getLang('dashboard_component_unavailable')) . '</p>';
            return;
        }

        $plugin->render('xhtml', $renderer, ['embedded' => true]);
    }

    protected function renderDocuments(
        string $pageId,
        ?helper_plugin_structdocapproval_dashboard $helper
    ): string {
        if (!$helper) {
            return '<p class="docapproval-warning">' .
                hsc($this->getLang('dashboard_component_unavailable')) . '</p>';
        }

        // Render the complete ACL-filtered current document set once. The browser
        // then performs the operational filters, sorting and paging live via
        // DataTables without another PHP/page request.
        $report = $helper->getDocuments([]);
        $assignees = $helper->getAssigneeSuggestions();

        $html = '<div class="docapproval-dashboard-documents">';
        $html .= '<h3>' . hsc($this->getLang('dashboard_documents_title')) . '</h3>';
        $html .= '<p class="docapproval-dashboard-section-intro">' .
            hsc($this->getLang('dashboard_documents_intro')) . '</p>';
        $html .= $this->renderDocumentFilters($pageId, $report['filters'], $assignees);
        $html .= $this->renderDocumentCounts($report['counts']);
        $html .= $this->renderDocumentTable($pageId, $report['rows']);
        $html .= '</div>';
        return $html;
    }

    protected function renderDocumentFilters(string $pageId, array $filters, array $assignees): string
    {
        $html = '<div class="docapproval-dashboard-filters" data-docapproval-live-filters="1">';

        $html .= '<label>' . hsc($this->getLang('compliance_page_filter')) .
            '<input class="edit docapproval-dashboard-page-filter" type="search" ' .
            'placeholder="' . hsc($this->getLang('compliance_page_placeholder')) . '" autocomplete="off" /></label>';

        $html .= '<label>' . hsc($this->getLang('dashboard_workflow_filter'));
        $html .= '<select class="docapproval-dashboard-status-filter">';
        $html .= $this->option('all', $this->getLang('dashboard_all_statuses'), 'all');
        foreach (Constants::statuses() as $status) {
            $html .= $this->option($status, $this->getLang('status_' . $status), 'all');
        }
        $html .= '</select></label>';

        $html .= '<label>' . hsc($this->getLang('dashboard_assignee_filter')) .
            '<input class="edit docapproval-dashboard-assignee-filter" type="search" ' .
            'list="docapproval-dashboard-assignees" placeholder="' .
            hsc($this->getLang('dashboard_assignee_placeholder')) . '" autocomplete="off" /></label>';
        $html .= '<datalist id="docapproval-dashboard-assignees">';
        foreach ($assignees as $assignee) {
            $login = (string)$assignee['user'];
            $name = (string)$assignee['name'];
            $label = $name !== $login ? $name . ' (' . $login . ')' : $login;
            $html .= '<option value="' . hsc($login) . '" label="' . hsc($label) . '"></option>';
        }
        $html .= '</datalist>';

        $html .= '<label>' . hsc($this->getLang('dashboard_ack_filter'));
        $html .= '<select class="docapproval-dashboard-ack-filter">';
        $html .= $this->option('all', $this->getLang('compliance_status_all'), 'all');
        $html .= $this->option('outstanding', $this->getLang('compliance_status_outstanding'), 'all');
        $html .= $this->option('overdue', $this->getLang('compliance_status_overdue'), 'all');
        $html .= $this->option('complete', $this->getLang('compliance_status_complete'), 'all');
        $html .= $this->option('none_required', $this->getLang('dashboard_ack_none'), 'all');
        $html .= '</select></label>';

        $html .= '<label>' . hsc($this->getLang('dashboard_rows_per_page'));
        $html .= '<select class="docapproval-dashboard-page-length">';
        foreach ([25, 50, 100, -1] as $length) {
            $label = $length === -1 ? $this->getLang('dashboard_rows_all') : (string)$length;
            $selected = $length === 50 ? ' selected' : '';
            $html .= '<option value="' . $length . '"' . $selected . '>' . hsc($label) . '</option>';
        }
        $html .= '</select></label>';

        $html .= '<div class="docapproval-dashboard-filter-actions">';
        $html .= '<button type="button" class="docapproval-dashboard-reset">' .
            hsc($this->getLang('compliance_reset')) . '</button>';
        $html .= '</div></div>';

        return $html;
    }

    protected function option(string $value, string $label, string $selected): string
    {
        return '<option value="' . hsc($value) . '"' . ($value === $selected ? ' selected' : '') . '>' .
            hsc($label) . '</option>';
    }

    protected function renderDocumentCounts(array $counts): string
    {
        $cards = [
            Constants::STATUS_DRAFT => $this->getLang('status_draft'),
            Constants::STATUS_AWAITING_REVIEW => $this->getLang('status_awaiting_review'),
            Constants::STATUS_AWAITING_TRAINING => $this->getLang('status_awaiting_training'),
            Constants::STATUS_READY_TO_PUBLISH => $this->getLang('status_ready_to_publish'),
            Constants::STATUS_PUBLISHED => $this->getLang('status_published'),
        ];

        $html = '<div class="docapproval-dashboard-counts">';
        foreach ($cards as $key => $label) {
            $html .= '<button type="button" class="docapproval-dashboard-count" ' .
                'data-docapproval-status-card="' . hsc($key) . '">' .
                '<strong data-docapproval-count="' . hsc($key) . '">' .
                (int)($counts[$key] ?? 0) . '</strong><span>' . hsc($label) . '</span></button>';
        }

        $html .= '<button type="button" class="docapproval-dashboard-count" ' .
            'data-docapproval-ack-card="outstanding">' .
            '<strong data-docapproval-count="ack_outstanding">' .
            (int)($counts['ack_outstanding'] ?? 0) . '</strong><span>' .
            hsc($this->getLang('dashboard_ack_outstanding')) . '</span></button>';

        $html .= '<button type="button" class="docapproval-dashboard-count attention" ' .
            'data-docapproval-ack-card="overdue">' .
            '<strong data-docapproval-count="ack_overdue">' .
            (int)($counts['ack_overdue'] ?? 0) . '</strong><span>' .
            hsc($this->getLang('compliance_overdue')) . '</span></button>';

        return $html . '</div>';
    }

    protected function renderDocumentTable(string $pageId, array $rows): string
    {
        if (!$rows) {
            return '<p class="docapproval-dashboard-none">' .
                hsc($this->getLang('dashboard_no_documents')) . '</p>';
        }

        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');

        $html = '<div class="docapproval-dashboard-tablewrap"><table class="inline docapproval-dashboard-documents-table">';
        $html .= '<thead><tr>';
        foreach ([
            'page',
            'dashboard_workflow',
            'assigned_to',
            'dashboard_waiting_since',
            'dashboard_published_rev',
            'dashboard_acknowledgement',
            'change_summary',
            'compliance_details'
        ] as $key) {
            $html .= '<th>' . hsc($this->getLang($key)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $pid = (string)$row['pid'];
            $title = trim((string)($row['title'] ?? $pid));
            $status = (string)$row['status'];
            $updatedTs = strtotime((string)$row['updated']) ?: 0;
            $pageSearch = strtolower($pid . ' ' . $title);
            $html .= '<tr data-docapproval-page="' . hsc($pageSearch) . '"' .
                ' data-docapproval-status="' . hsc($status) . '"' .
                ' data-docapproval-ack="' . hsc((string)$row['ack_state']) . '"' .
                ' data-docapproval-overdue="' . (!empty($row['ack_overdue']) ? '1' : '0') . '"' .
                ' data-docapproval-assignees="' . hsc(strtolower((string)($row['assigned_search'] ?? ''))) . '"' .
                ' data-docapproval-ack-outstanding="' . (int)$row['ack_outstanding'] . '">';
            $html .= '<td class="docapproval-dashboard-page-cell"><a href="' . hsc(wl($pid)) . '">' .
                hsc($title) . '</a>';
            if ($title !== $pid) {
                $html .= '<small>' . hsc($pid) . '</small>';
            }
            $html .= '</td>';
            $html .= '<td data-order="' . $this->statusPriority($status) . '">' .
                hsc($this->getLang('status_' . $status)) . '</td>';
            $html .= '<td>' . $this->renderAssignment((string)$row['assigned_spec'], $publication) . '</td>';
            $html .= '<td data-order="' . $updatedTs . '">' .
                hsc($this->formatIsoDate((string)$row['updated']));
            $age = $this->formatAgeFromIso((string)$row['updated']);
            if ($age !== '') {
                $html .= '<small class="docapproval-age">' . hsc($age) . '</small>';
            }
            $html .= '</td>';
            $html .= '<td>' . hsc($row['published_version'] !== '' ? (string)$row['published_version'] : '—') . '</td>';
            $html .= '<td>' . $this->renderAckStatus($row) . '</td>';
            $html .= '<td>' . $this->renderSummary((string)$row['change_summary']) . '</td>';
            $html .= '<td>' . $this->renderDocumentDetails($pageId, $row, $publication) . '</td>';
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div>';
    }

    protected function renderAssignment(string $spec, $publication): string
    {
        $spec = trim($spec);
        if ($spec === '') return '—';

        $tokens = Assignments::getInstance()->splitSpec($spec);
        $out = [];
        foreach ($tokens as $token) {
            if ($token !== '' && $token[0] === '@') {
                $out[] = hsc($token);
                continue;
            }
            $name = $publication ? $publication->getUserDisplayName($token) : $token;
            $out[] = '<span title="' . hsc($token) . '">' . hsc($name) . '</span>';
        }
        return implode(', ', $out);
    }

    protected function renderAckStatus(array $row): string
    {
        if ($row['ack_state'] === 'none') return '—';
        if ($row['ack_state'] === 'not_required') {
            return '<span class="docapproval-dashboard-ack none">' .
                hsc($this->getLang('dashboard_ack_not_required')) . '</span>';
        }
        if ($row['ack_state'] === 'complete') {
            return '<span class="docapproval-dashboard-ack complete">' .
                (int)$row['acknowledged'] . '/' . (int)$row['ack_required'] . '</span>';
        }

        $html = '<span class="docapproval-dashboard-ack outstanding' .
            (!empty($row['ack_overdue']) ? ' overdue' : '') . '">' .
            (int)$row['ack_outstanding'] . ' ' . hsc($this->getLang('dashboard_outstanding_short')) .
            ' (' . (int)$row['acknowledged'] . '/' . (int)$row['ack_required'] . ')';
        if (!empty($row['ack_due_at'])) {
            $html .= '<small>' . $this->renderDueStatus((int)$row['ack_due_at']) . '</small>';
        }
        return $html . '</span>';
    }

    protected function renderDocumentDetails(string $dashboardPage, array $row, $publication): string
    {
        $html = '<details class="docapproval-dashboard-details"><summary>' .
            hsc($this->getLang('compliance_show_details')) . '</summary>';
        $html .= '<div class="docapproval-dashboard-detail-body">';

        $html .= '<div class="docapproval-dashboard-detail-grid">';
        $html .= '<div><strong>' . hsc($this->getLang('dashboard_current_workflow')) . '</strong><dl>';
        $html .= $this->detailPair($this->getLang('status'), $this->getLang('status_' . $row['status']));
        $updatedDisplay = $this->formatIsoDate((string)$row['updated']);
        $updatedAge = $this->formatAgeFromIso((string)$row['updated']);
        if ($updatedAge !== '') $updatedDisplay .= ' — ' . $updatedAge;
        $html .= $this->detailPair($this->getLang('updated'), $updatedDisplay);
        if ($row['submitted_by'] !== '') {
            $html .= $this->detailPair(
                $this->getLang('submitted_by'),
                $this->displayUser((string)$row['submitted_by'], $publication, false)
            );
        }
        if ($row['reviewed_by'] !== '') {
            $html .= $this->detailPair(
                $this->getLang('reviewed_by'),
                $this->displayUser((string)$row['reviewed_by'], $publication, false)
            );
        }
        if ($row['training_disposition'] !== '') {
            $html .= $this->detailPair(
                $this->getLang('training_impact'),
                $this->getLang('training_' . $row['training_disposition'])
            );
        }
        if ($row['training_note'] !== '') {
            $html .= $this->detailPair($this->getLang('training_note'), (string)$row['training_note']);
        }
        if ($row['comment'] !== '') {
            $html .= $this->detailPair($this->getLang('comment'), (string)$row['comment']);
        }
        $html .= '</dl></div>';

        $html .= '<div><strong>' . hsc($this->getLang('dashboard_current_publication')) . '</strong><dl>';
        if ($row['published_revision']) {
            $html .= $this->detailPair(
                $this->getLang('version'),
                $row['published_version'] !== '' ? (string)$row['published_version'] : $this->getLang('version_unset')
            );
            $html .= $this->detailPair($this->getLang('compliance_published'), $this->formatIsoDate((string)$row['published_at']));
            if ($row['published_by'] !== '') {
                $html .= $this->detailPair(
                    $this->getLang('compliance_published_by'),
                    $this->displayUser((string)$row['published_by'], $publication, false)
                );
            }
        } else {
            $html .= '<p class="docapproval-dashboard-none">' .
                hsc($this->getLang('dashboard_no_published')) . '</p>';
        }
        $html .= '</dl>';

        if ($row['ack_state'] !== 'none') {
            $html .= '<p><strong>' . hsc($this->getLang('ack_report_title')) . ':</strong> ' .
                $this->renderAckStatus($row) . '</p>';
            if ($row['outstanding_users']) {
                $html .= '<p><strong>' . hsc($this->getLang('ack_report_pending_heading')) . ':</strong> ';
                $names = [];
                foreach ($row['outstanding_users'] as $user) {
                    $names[] = $this->displayUser((string)$user, $publication, true);
                }
                $html .= implode(', ', $names) . '</p>';
            }
        }
        $html .= '</div></div>';

        if (!empty($row['publication_history'])) {
            $html .= '<div class="docapproval-dashboard-publication-history">';
            $html .= '<strong>' . hsc($this->getLang('dashboard_recent_publications')) . '</strong>';
            $html .= '<table class="inline"><thead><tr>';
            foreach (['version','compliance_published','dashboard_acknowledgement','ack_due_date'] as $key) {
                $html .= '<th>' . hsc($this->getLang($key)) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($row['publication_history'] as $release) {
                $version = Constants::formatVersion((string)$release['version']);
                if ($version === '') $version = $this->getLang('version_unset');
                $required = (int)$release['required'];
                $complete = (int)$release['acknowledged'];
                $ack = $required > 0 ? $complete . '/' . $required : '—';

                $html .= '<tr>';
                $releaseUrl = wl((string)$row['pid'], ['rev' => (int)$release['content_revision']]);
                $html .= '<td><a href="' . hsc($releaseUrl) . '">' . hsc($version) . '</a></td>';
                $html .= '<td>' . hsc($this->formatTimestamp((int)$release['published_at'])) . '</td>';
                $html .= '<td>' . hsc($ack) . '</td>';
                $html .= '<td>' . ($release['ack_due_at']
                    ? $this->renderDueStatus(
                        (int)$release['ack_due_at'],
                        $required === 0 || $complete >= $required
                    )
                    : '—') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        }

        $historyUrl = wl($dashboardPage, [
            'docapproval_dash_view' => 'history',
            'docapproval_history_page' => (string)$row['pid'],
        ]) . '#docapproval-history';
        $html .= '<p class="docapproval-dashboard-detail-links">';
        $html .= '<a href="' . hsc(wl((string)$row['pid'])) . '">' .
            hsc($this->getLang('my_view')) . '</a> · ';
        $html .= '<a href="' . hsc($historyUrl) . '">' .
            hsc($this->getLang('view_history')) . '</a>';
        $html .= '</p></div></details>';

        return $html;
    }

    protected function renderDueStatus(int $dueAt, bool $complete = false): string
    {
        if ($dueAt <= 0) return '—';
        if ($complete) {
            return '<span class="docapproval-due complete">' .
                hsc(date('Y-m-d', $dueAt)) . '</span>';
        }

        $todayEnd = strtotime(date('Y-m-d') . ' 23:59:59');
        $dueDay = strtotime(date('Y-m-d', $dueAt) . ' 23:59:59');
        $days = (int)floor(($dueDay - $todayEnd) / 86400);

        if ($days < 0) {
            $abs = abs($days);
            $label = sprintf(
                $this->getLang($abs === 1 ? 'ack_overdue_one_day' : 'ack_overdue_days'),
                $abs
            );
            $class = 'overdue';
        } elseif ($days === 0) {
            $label = $this->getLang('ack_due_today');
            $class = 'today';
        } else {
            $label = sprintf($this->getLang('ack_due_in_days'), $days);
            $class = 'future';
        }

        return '<span class="docapproval-due ' . hsc($class) . '">' .
            hsc(date('Y-m-d', $dueAt)) . '<small>' . hsc($label) . '</small></span>';
    }

    protected function formatTimestamp(int $timestamp): string
    {
        return $timestamp > 0 ? dformat($timestamp) : '';
    }

    protected function detailPair(string $label, string $value): string
    {
        return '<dt>' . hsc($label) . '</dt><dd>' . hsc($value) . '</dd>';
    }

    protected function displayUser(string $user, $publication, bool $html): string
    {
        $name = $publication ? $publication->getUserDisplayName($user) : $user;
        if (!$html) return $name;
        return '<span title="' . hsc($user) . '">' . hsc($name) . '</span>';
    }

    protected function renderSummary(string $summary): string
    {
        $summary = trim($summary);
        return $summary === ''
            ? '<span class="docapproval-dashboard-none">—</span>'
            : nl2br(hsc($summary));
    }

    protected function statusPriority(string $status): int
    {
        switch ($status) {
            case Constants::STATUS_AWAITING_REVIEW:
                return 1;
            case Constants::STATUS_AWAITING_TRAINING:
                return 2;
            case Constants::STATUS_READY_TO_PUBLISH:
                return 3;
            case Constants::STATUS_DRAFT:
                return 4;
            case Constants::STATUS_PUBLISHED:
                return 5;
            default:
                return 99;
        }
    }

    protected function formatAgeFromIso(string $iso): string
    {
        if ($iso === '') return '';
        $timestamp = strtotime($iso);
        if (!$timestamp) return '';
        $seconds = max(0, time() - $timestamp);

        if ($seconds < 3600) {
            $minutes = max(1, (int)floor($seconds / 60));
            return sprintf(
                $this->getLang($minutes === 1 ? 'age_minute' : 'age_minutes'),
                $minutes
            );
        }
        if ($seconds < 86400) {
            $hours = max(1, (int)floor($seconds / 3600));
            return sprintf(
                $this->getLang($hours === 1 ? 'age_hour' : 'age_hours'),
                $hours
            );
        }

        $days = max(1, (int)floor($seconds / 86400));
        return sprintf(
            $this->getLang($days === 1 ? 'age_day' : 'age_days'),
            $days
        );
    }

    protected function formatIsoDate(string $iso): string
    {
        if ($iso === '') return '';
        $timestamp = strtotime($iso);
        return $timestamp ? dformat($timestamp) : $iso;
    }
}
