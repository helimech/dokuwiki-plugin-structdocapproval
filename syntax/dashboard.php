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

        global $INPUT;

        $filters = [
            'page' => $INPUT->str('docapproval_dash_page'),
            'status' => $INPUT->str('docapproval_dash_status'),
            'assignee' => $INPUT->str('docapproval_dash_assignee'),
            'ack' => $INPUT->str('docapproval_dash_ack'),
        ];
        if ($filters['status'] === '') $filters['status'] = 'all';
        if ($filters['ack'] === '') $filters['ack'] = 'all';

        $report = $helper->getDocuments($filters);
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
        $html = '<form method="get" action="' . hsc(DOKU_BASE . DOKU_SCRIPT) .
            '" class="docapproval-dashboard-filters">';
        $html .= '<input type="hidden" name="id" value="' . hsc($pageId) . '" />';
        $html .= '<input type="hidden" name="docapproval_dash_view" value="documents" />';

        $html .= '<label>' . hsc($this->getLang('compliance_page_filter')) .
            '<input class="edit" type="text" name="docapproval_dash_page" value="' .
            hsc((string)$filters['page']) . '" placeholder="' .
            hsc($this->getLang('compliance_page_placeholder')) . '" /></label>';

        $html .= '<label>' . hsc($this->getLang('dashboard_workflow_filter'));
        $html .= '<select name="docapproval_dash_status">';
        $html .= $this->option('all', $this->getLang('dashboard_all_statuses'), $filters['status']);
        foreach (Constants::statuses() as $status) {
            $html .= $this->option($status, $this->getLang('status_' . $status), $filters['status']);
        }
        $html .= '</select></label>';

        $html .= '<label>' . hsc($this->getLang('dashboard_assignee_filter')) .
            '<input class="edit" type="text" name="docapproval_dash_assignee" ' .
            'list="docapproval-dashboard-assignees" value="' . hsc((string)$filters['assignee']) .
            '" placeholder="' . hsc($this->getLang('dashboard_assignee_placeholder')) . '" /></label>';
        $html .= '<datalist id="docapproval-dashboard-assignees">';
        foreach ($assignees as $assignee) {
            $login = (string)$assignee['user'];
            $name = (string)$assignee['name'];
            $label = $name !== $login ? $name . ' (' . $login . ')' : $login;
            $html .= '<option value="' . hsc($login) . '" label="' . hsc($label) . '"></option>';
        }
        $html .= '</datalist>';

        $html .= '<label>' . hsc($this->getLang('dashboard_ack_filter'));
        $html .= '<select name="docapproval_dash_ack">';
        $html .= $this->option('all', $this->getLang('compliance_status_all'), $filters['ack']);
        $html .= $this->option('outstanding', $this->getLang('compliance_status_outstanding'), $filters['ack']);
        $html .= $this->option('complete', $this->getLang('compliance_status_complete'), $filters['ack']);
        $html .= $this->option('none_required', $this->getLang('dashboard_ack_none'), $filters['ack']);
        $html .= '</select></label>';

        $html .= '<div class="docapproval-dashboard-filter-actions">';
        $html .= '<button type="submit">' . hsc($this->getLang('compliance_apply')) . '</button>';
        $html .= '<a class="button" href="' .
            hsc(wl($pageId, ['docapproval_dash_view' => 'documents'])) . '">' .
            hsc($this->getLang('compliance_reset')) . '</a>';
        $html .= '</div></form>';

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
            'ack_outstanding' => $this->getLang('dashboard_ack_outstanding'),
        ];

        $html = '<div class="docapproval-dashboard-counts">';
        foreach ($cards as $key => $label) {
            $html .= '<div class="docapproval-dashboard-count"><strong>' .
                (int)($counts[$key] ?? 0) . '</strong><span>' . hsc($label) . '</span></div>';
        }
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

        $html = '<div class="docapproval-dashboard-tablewrap"><table class="inline">';
        $html .= '<thead><tr>';
        foreach ([
            'page',
            'dashboard_workflow',
            'assigned_to',
            'dashboard_waiting_since',
            'dashboard_published_rev',
            'acknowledge',
            'change_summary',
            'compliance_details'
        ] as $key) {
            $html .= '<th>' . hsc($this->getLang($key)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $pid = (string)$row['pid'];
            $html .= '<tr>';
            $html .= '<td><a href="' . hsc(wl($pid)) . '">' . hsc($pid) . '</a></td>';
            $html .= '<td>' . hsc($this->getLang('status_' . $row['status'])) . '</td>';
            $html .= '<td>' . $this->renderAssignment((string)$row['assigned_spec'], $publication) . '</td>';
            $html .= '<td>' . hsc($this->formatIsoDate((string)$row['updated'])) . '</td>';
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

        return '<span class="docapproval-dashboard-ack outstanding">' .
            (int)$row['ack_outstanding'] . ' ' . hsc($this->getLang('dashboard_outstanding_short')) .
            ' (' . (int)$row['acknowledged'] . '/' . (int)$row['ack_required'] . ')</span>';
    }

    protected function renderDocumentDetails(string $dashboardPage, array $row, $publication): string
    {
        $html = '<details class="docapproval-dashboard-details"><summary>' .
            hsc($this->getLang('compliance_show_details')) . '</summary>';
        $html .= '<div class="docapproval-dashboard-detail-body">';

        $html .= '<div class="docapproval-dashboard-detail-grid">';
        $html .= '<div><strong>' . hsc($this->getLang('dashboard_current_workflow')) . '</strong><dl>';
        $html .= $this->detailPair($this->getLang('status'), $this->getLang('status_' . $row['status']));
        $html .= $this->detailPair($this->getLang('updated'), $this->formatIsoDate((string)$row['updated']));
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

    protected function formatIsoDate(string $iso): string
    {
        if ($iso === '') return '';
        $timestamp = strtotime($iso);
        return $timestamp ? dformat($timestamp) : $iso;
    }
}
