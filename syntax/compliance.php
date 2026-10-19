<?php

use dokuwiki\plugin\structdocapproval\meta\Constants;

class syntax_plugin_structdocapproval_compliance extends DokuWiki_Syntax_Plugin
{
    public function getType() { return 'substition'; }
    public function getPType() { return 'block'; }
    public function getSort() { return 158; }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern(
            '----+ *struct[_]?docapproval[_]?compliance *-+\n.*?\n?----+',
            $mode,
            'plugin_structdocapproval_compliance'
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

        if (!auth_ismanager()) {
            $renderer->doc .= '<div class="plugin-structdocapproval-compliance">' .
                '<p class="docapproval-warning">' . hsc($this->getLang('compliance_denied')) . '</p>' .
                '</div>';
            return true;
        }

        global $INPUT, $ID;

        /** @var helper_plugin_structdocapproval_compliance $helper */
        $helper = plugin_load('helper', 'structdocapproval_compliance');
        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        if (!$helper || !$publication) return false;

        $filters = [
            'scope' => $INPUT->str('docapproval_comp_scope'),
            'page' => $INPUT->str('docapproval_comp_page'),
            'user' => $INPUT->str('docapproval_comp_user'),
            'status' => $INPUT->str('docapproval_comp_status'),
            'date_from' => $INPUT->str('docapproval_comp_from'),
            'date_to' => $INPUT->str('docapproval_comp_to'),
        ];
        if ($filters['scope'] === '') $filters['scope'] = 'current';
        if ($filters['status'] === '') $filters['status'] = 'all';

        $report = $helper->getReport($filters);
        $users = $helper->getUserSuggestions();

        $embedded = is_array($data) && !empty($data['embedded']);
        $html = '<div class="plugin-structdocapproval-compliance' . ($embedded ? ' embedded' : '') . '">';
        if (!$embedded) $html .= '<h2>' . hsc($this->getLang('compliance_title')) . '</h2>';
        $html .= '<p class="docapproval-compliance-intro">' . hsc($this->getLang('compliance_intro')) . '</p>';
        $html .= $this->renderFilters($ID, $report['filters'], $users);
        $html .= $this->renderTotals($report['totals']);
        $html .= $this->renderPublicationTable($report['publications'], $publication);
        $html .= $this->renderUserTable($report['users']);
        $html .= $this->renderExportData($report['publications'], $publication);
        $html .= '</div>';

        $renderer->doc .= $html;
        return true;
    }

    protected function renderFilters(string $pageId, array $filters, array $users): string
    {
        global $INPUT;
        $dashboardView = $INPUT->str('docapproval_dash_view');

        $html = '<form method="get" action="' . hsc(DOKU_BASE . DOKU_SCRIPT) . '" class="docapproval-compliance-filters">';
        $html .= '<input type="hidden" name="id" value="' . hsc($pageId) . '" />';
        if ($dashboardView === 'compliance') {
            $html .= '<input type="hidden" name="docapproval_dash_view" value="compliance" />';
        }

        $html .= '<label>' . hsc($this->getLang('compliance_scope'));
        $html .= '<select name="docapproval_comp_scope">';
        $html .= $this->option('current', $this->getLang('compliance_scope_current'), $filters['scope']);
        $html .= $this->option('all', $this->getLang('compliance_scope_all'), $filters['scope']);
        $html .= '</select></label>';

        $html .= '<label>' . hsc($this->getLang('compliance_page_filter')) .
            '<input class="edit" type="text" name="docapproval_comp_page" value="' .
            hsc((string)$filters['page']) . '" placeholder="' . hsc($this->getLang('compliance_page_placeholder')) . '" /></label>';

        $html .= '<label>' . hsc($this->getLang('compliance_user_filter')) .
            '<input class="edit" type="text" name="docapproval_comp_user" list="docapproval-compliance-users" value="' .
            hsc((string)$filters['user']) . '" placeholder="' . hsc($this->getLang('compliance_user_placeholder')) . '" /></label>';
        $html .= '<datalist id="docapproval-compliance-users">';
        foreach ($users as $user) {
            $login = (string)$user['user'];
            $name = (string)$user['name'];
            $label = $name !== $login ? $name . ' (' . $login . ')' : $login;
            $html .= '<option value="' . hsc($login) . '" label="' . hsc($label) . '"></option>';
        }
        $html .= '</datalist>';

        $html .= '<label>' . hsc($this->getLang('compliance_status_filter'));
        $html .= '<select name="docapproval_comp_status">';
        $html .= $this->option('all', $this->getLang('compliance_status_all'), $filters['status']);
        $html .= $this->option('outstanding', $this->getLang('compliance_status_outstanding'), $filters['status']);
        $html .= $this->option('complete', $this->getLang('compliance_status_complete'), $filters['status']);
        $html .= $this->option('not_required', $this->getLang('compliance_status_not_required'), $filters['status']);
        $html .= '</select></label>';

        $html .= '<label>' . hsc($this->getLang('compliance_from')) .
            '<input class="edit" type="date" name="docapproval_comp_from" value="' . hsc((string)$filters['date_from']) . '" /></label>';
        $html .= '<label>' . hsc($this->getLang('compliance_to')) .
            '<input class="edit" type="date" name="docapproval_comp_to" value="' . hsc((string)$filters['date_to']) . '" /></label>';

        $html .= '<div class="docapproval-compliance-filter-actions">';
        $html .= '<button type="submit">' . hsc($this->getLang('compliance_apply')) . '</button>';
        $resetParams = $dashboardView === 'compliance'
            ? ['docapproval_dash_view' => 'compliance']
            : [];
        $html .= '<a class="button" href="' . hsc(wl($pageId, $resetParams)) . '">' .
            hsc($this->getLang('compliance_reset')) . '</a>';
        $html .= '<button type="button" class="docapproval-compliance-export">' .
            hsc($this->getLang('compliance_export_csv')) . '</button>';
        $html .= '</div></form>';

        return $html;
    }

    protected function option(string $value, string $label, string $selected): string
    {
        return '<option value="' . hsc($value) . '"' . ($value === $selected ? ' selected' : '') . '>' .
            hsc($label) . '</option>';
    }

    protected function renderTotals(array $totals): string
    {
        $percent = $this->percent((int)$totals['acknowledged'], (int)$totals['required']);
        $html = '<div class="docapproval-compliance-counts">';
        $html .= $this->countCard((int)$totals['publications'], $this->getLang('compliance_publications'));
        $html .= $this->countCard((int)$totals['users'], $this->getLang('compliance_users'));
        $html .= $this->countCard((int)$totals['required'], $this->getLang('ack_required_count'));
        $html .= $this->countCard((int)$totals['acknowledged'], $this->getLang('ack_complete_count'));
        $html .= $this->countCard((int)$totals['outstanding'], $this->getLang('ack_outstanding_count'));
        $html .= $this->countCard($percent, $this->getLang('compliance_completion'));
        $html .= '</div>';
        return $html;
    }

    protected function countCard($value, string $label): string
    {
        return '<div class="docapproval-compliance-count"><strong>' . hsc((string)$value) .
            '</strong><span>' . hsc($label) . '</span></div>';
    }

    protected function renderPublicationTable(array $rows, helper_plugin_structdocapproval_publication $publication): string
    {
        $html = '<section class="docapproval-compliance-section">';
        $html .= '<h3>' . hsc($this->getLang('compliance_publication_table')) . ' (' . count($rows) . ')</h3>';

        if (!$rows) {
            return $html . '<p class="docapproval-compliance-none">' . hsc($this->getLang('compliance_none')) . '</p></section>';
        }

        $html .= '<div class="docapproval-compliance-tablewrap"><table class="inline">';
        $html .= '<thead><tr>';
        foreach (['page','version','compliance_published','ack_required_count','ack_complete_count','ack_outstanding_count','compliance_completion','change_summary','compliance_details'] as $key) {
            $html .= '<th>' . hsc($this->getLang($key)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $required = count($row['required_users']);
            $complete = count($row['acknowledged_users']);
            $outstanding = count($row['outstanding_users']);
            $version = Constants::formatVersion((string)$row['version']);
            if ($version === '') $version = $this->getLang('version_unset');
            $url = wl((string)$row['pid'], ['rev' => (int)$row['view_revision']]);

            $html .= '<tr>';
            $html .= '<td class="docapproval-compliance-page-cell"><a href="' . hsc($url) . '">' .
                hsc((string)($row['title'] ?? $row['pid'])) . '</a>';
            if ((string)($row['title'] ?? '') !== '' && (string)$row['title'] !== (string)$row['pid']) {
                $html .= '<small>' . hsc((string)$row['pid']) . '</small>';
            }
            if (!empty($row['is_current'])) {
                $html .= ' <span class="docapproval-compliance-current">' . hsc($this->getLang('compliance_current_badge')) . '</span>';
            }
            $html .= '</td>';
            $html .= '<td>' . hsc($version) . '</td>';
            $html .= '<td>' . hsc($this->formatTimestamp((int)$row['published_at']));
            $publishedAge = $this->formatAge((int)$row['published_at']);
            if ($publishedAge !== '') {
                $html .= '<small class="docapproval-age">' . hsc($publishedAge) . '</small>';
            }
            $html .= '</td>';
            $html .= '<td>' . $required . '</td>';
            $html .= '<td>' . $complete . '</td>';
            $html .= '<td>' . $outstanding . '</td>';
            $html .= '<td>' . hsc($this->percent($complete, $required)) . '</td>';
            $html .= '<td>' . $this->renderSummary((string)$row['change_summary']) . '</td>';
            $html .= '<td>' . $this->renderDetails($row, $publication) . '</td>';
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div></section>';
    }

    protected function renderDetails(array $row, helper_plugin_structdocapproval_publication $publication): string
    {
        $html = '<details class="docapproval-compliance-details"><summary>' . hsc($this->getLang('compliance_show_details')) . '</summary>';
        $html .= '<div class="docapproval-compliance-detail-body">';

        if (trim((string)$row['published_by']) !== '') {
            $name = $publication->getUserDisplayName((string)$row['published_by']);
            $html .= '<p><strong>' . hsc($this->getLang('compliance_published_by')) . ':</strong> ' .
                '<span title="' . hsc((string)$row['published_by']) . '">' . hsc($name) . '</span></p>';
        }
        $html .= '<p><strong>' . hsc($this->getLang('release_acknowledge')) . ':</strong> ' .
            ($row['acknowledge_spec'] !== '' ? hsc((string)$row['acknowledge_spec']) : '—') . '</p>';

        $html .= '<div class="docapproval-compliance-detail-grid">';
        $html .= '<div><strong>' . hsc($this->getLang('ack_report_acknowledged_heading')) . '</strong>';
        if (!$row['acknowledged_users']) {
            $html .= '<p><em>' . hsc($this->getLang('ack_report_none')) . '</em></p>';
        } else {
            $html .= '<ul>';
            foreach ($row['acknowledged_users'] as $user => $timestamp) {
                $html .= '<li>' . $this->renderUser($publication, (string)$user);
                if ($timestamp) $html .= ' — ' . hsc($this->formatTimestamp((int)$timestamp));
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</div>';

        $html .= '<div><strong>' . hsc($this->getLang('ack_report_pending_heading')) . '</strong>';
        if (!$row['outstanding_users']) {
            $html .= '<p><em>' . hsc($this->getLang('ack_report_none')) . '</em></p>';
        } else {
            $html .= '<ul>';
            foreach ($row['outstanding_users'] as $user) {
                $html .= '<li>' . $this->renderUser($publication, (string)$user) . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</div></div></div></details>';
        return $html;
    }

    protected function renderUser(helper_plugin_structdocapproval_publication $publication, string $user): string
    {
        $name = $publication->getUserDisplayName($user);
        return '<span title="' . hsc($user) . '">' . hsc($name) . '</span>';
    }

    protected function renderExportData(
        array $rows,
        helper_plugin_structdocapproval_publication $publication
    ): string {
        $export = [];
        foreach ($rows as $row) {
            $base = [
                'page' => (string)$row['pid'],
                'title' => (string)($row['title'] ?? $row['pid']),
                'version' => Constants::formatVersion((string)$row['version']),
                'publication_revision' => (int)$row['publication_revision'],
                'content_revision' => (int)$row['content_revision'],
                'published_at' => $this->formatTimestamp((int)$row['published_at']),
                'published_by' => (string)$row['published_by'],
                'published_by_name' => $row['published_by'] !== ''
                    ? $publication->getUserDisplayName((string)$row['published_by'])
                    : '',
                'current' => !empty($row['is_current']) ? 'Yes' : 'No',
                'change_summary' => (string)$row['change_summary'],
                'acknowledge_audience' => (string)$row['acknowledge_spec'],
            ];

            if (!$row['required_users']) {
                $export[] = $base + [
                    'required_user' => '',
                    'required_name' => '',
                    'status' => $this->getLang('compliance_status_not_required'),
                    'acknowledged_at' => '',
                ];
                continue;
            }

            foreach ($row['required_users'] as $user) {
                $acknowledged = array_key_exists($user, $row['acknowledged_users']);
                $export[] = $base + [
                    'required_user' => (string)$user,
                    'required_name' => $publication->getUserDisplayName((string)$user),
                    'status' => $acknowledged
                        ? $this->getLang('compliance_status_complete')
                        : $this->getLang('compliance_status_outstanding'),
                    'acknowledged_at' => $acknowledged
                        ? $this->formatTimestamp((int)$row['acknowledged_users'][$user])
                        : '',
                ];
            }
        }

        $json = json_encode(
            $export,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if ($json === false) $json = '[]';

        return '<script type="application/json" class="docapproval-compliance-export-data">' .
            $json . '</script>';
    }

    protected function renderUserTable(array $rows): string
    {
        $html = '<section class="docapproval-compliance-section">';
        $html .= '<h3>' . hsc($this->getLang('compliance_user_table')) . ' (' . count($rows) . ')</h3>';

        if (!$rows) {
            return $html . '<p class="docapproval-compliance-none">' . hsc($this->getLang('compliance_no_users')) . '</p></section>';
        }

        $html .= '<div class="docapproval-compliance-tablewrap"><table class="inline">';
        $html .= '<thead><tr>';
        foreach (['compliance_user','ack_required_count','ack_complete_count','ack_outstanding_count','compliance_completion'] as $key) {
            $html .= '<th>' . hsc($this->getLang($key)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td><span title="' . hsc((string)$row['user']) . '">' . hsc((string)$row['name']) . '</span></td>';
            $html .= '<td>' . (int)$row['required'] . '</td>';
            $html .= '<td>' . (int)$row['acknowledged'] . '</td>';
            $html .= '<td>' . (int)$row['outstanding'] . '</td>';
            $html .= '<td>' . hsc($this->percent((int)$row['acknowledged'], (int)$row['required'])) . '</td>';
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div></section>';
    }

    protected function renderSummary(string $summary): string
    {
        $summary = trim($summary);
        return $summary === '' ? '<span class="docapproval-compliance-none">—</span>' : nl2br(hsc($summary));
    }

    protected function percent(int $complete, int $required): string
    {
        if ($required <= 0) return '—';
        $value = ($complete / $required) * 100;
        $rounded = round($value, 1);
        return (fmod($rounded, 1.0) === 0.0 ? (string)(int)$rounded : number_format($rounded, 1)) . '%';
    }

    protected function formatAge(int $timestamp): string
    {
        if ($timestamp <= 0) return '';
        $seconds = max(0, time() - $timestamp);

        if ($seconds < 3600) {
            $minutes = max(1, (int)floor($seconds / 60));
            return sprintf($this->getLang($minutes === 1 ? 'age_minute' : 'age_minutes'), $minutes);
        }
        if ($seconds < 86400) {
            $hours = max(1, (int)floor($seconds / 3600));
            return sprintf($this->getLang($hours === 1 ? 'age_hour' : 'age_hours'), $hours);
        }

        $days = max(1, (int)floor($seconds / 86400));
        return sprintf($this->getLang($days === 1 ? 'age_day' : 'age_days'), $days);
    }

    protected function formatTimestamp(int $timestamp): string
    {
        return $timestamp > 0 ? dformat($timestamp) : '';
    }
}
