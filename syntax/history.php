<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\Parsing\Handler;
use dokuwiki\plugin\structdocapproval\meta\Constants;

class syntax_plugin_structdocapproval_history extends SyntaxPlugin
{
    public function getType()
    {
        return 'substition';
    }
    public function getPType()
    {
        return 'block';
    }
    public function getSort()
    {
        return 156;
    }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern(
            '----+ *struct[_]?docapprovalhistory *-+\n.*?\n?----+',
            $mode,
            'plugin_structdocapproval_history'
        );
    }

    public function handle($match, $state, $pos, Handler $handler)
    {
        return [];
    }

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode !== 'xhtml') return false;
        if (method_exists($renderer, 'nocache')) $renderer->nocache();
        global $INPUT, $ID;
        /** @var helper_plugin_structdocapproval_db $dbHelper */
        $dbHelper = plugin_load('helper', 'structdocapproval_db');
        $db = $dbHelper ? $dbHelper->getDB() : null;
        if (!$db) return false;

        $pages = $db->queryAll('SELECT DISTINCT pid FROM data_struct_docapproval ORDER BY pid') ?: [];
        $rawSelected = trim($INPUT->str('docapproval_history_page'));

        // Build the accessible autocomplete set once. A user may select a page ID
        // from the datalist or type an exact document title; an exact title is
        // accepted only when it resolves uniquely.
        $availablePages = [];
        foreach ($pages as $row) {
            $pid = cleanID((string)($row['pid'] ?? ''));
            if ($pid === '') continue;
            if (auth_quickaclcheck($pid) < AUTH_READ || !$dbHelper->canViewWorking($pid)) continue;
            $title = $this->pageTitle($pid);
            $availablePages[] = [
                'pid' => $pid,
                'title' => $title,
                'label' => $title !== $pid ? $title . ' — ' . $pid : $pid,
            ];
        }
        $selected = $this->resolvePageSelection($rawSelected, $availablePages);

        $embedded = is_array($data) && !empty($data['embedded']);
        $html = '<div id="docapproval-history" class="plugin-structdocapproval-history' .
            ($embedded ? ' embedded' : '') . '">';
        $html .= '<form method="get" action="' . hsc(DOKU_BASE . DOKU_SCRIPT) . '">';
        $html .= '<input type="hidden" name="id" value="' . hsc($ID) . '" />';
        if ($INPUT->str('docapproval_dash_view') === 'history') {
            $html .= '<input type="hidden" name="docapproval_dash_view" value="history" />';
        }
        $html .= '<label>' . hsc($this->getLang('select_page')) . ' ';
        $html .= '<input type="search" class="edit docapproval-history-page-picker" ' .
            'name="docapproval_history_page" list="docapproval-history-pages" ' .
            'value="' . hsc($selected !== '' ? $selected : $rawSelected) . '" placeholder="' .
            hsc($this->getLang('select_page_prompt')) . '" autocomplete="off" /></label>';
        $html .= '<datalist id="docapproval-history-pages">';
        foreach ($availablePages as $page) {
            $html .= '<option value="' . hsc($page['pid']) . '" label="' .
                hsc($page['label']) . '"></option>';
        }
        $html .= '</datalist>';
        $html .= ' <button type="submit">' . hsc($this->getLang('show_history')) . '</button></form>';

        if ($rawSelected !== '' && $selected === '') {
            $html .= '<p class="docapproval-warning">' .
                hsc($this->getLang('history_page_not_found')) . '</p>';
        }

        if ($selected !== '') {
            if (auth_quickaclcheck($selected) < AUTH_READ || !$dbHelper->canViewWorking($selected)) {
                $html .= '<p class="docapproval-warning">' . hsc($this->getLang('history_denied')) . '</p>';
            } else {
                $rows = $db->queryAll(
                    'SELECT * FROM data_struct_docapproval WHERE pid=? ORDER BY rid ASC',
                    [$selected]
                ) ?: [];
                $title = $this->pageTitle($selected);
                $html .= '<h3><a href="' . hsc(wl($selected)) . '">' . hsc($title) . '</a>';
                if ($title !== $selected) {
                    $html .= ' <small class="docapproval-page-id">' . hsc($selected) . '</small>';
                }
                $html .= '</h3>';
                $html .= '<table class="inline"><thead><tr>';
                foreach (
                    [
                        'date',
                        'action',
                        'actor',
                        'status',
                        'revision',
                        'assigned_reviewer',
                        'reviewed_by',
                        'training',
                        'version',
                        'comment',
                    ] as $k
                ) {
                    $html .= '<th>' . hsc($this->getLang($k)) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    $revlink = wl($selected, ['rev' => $row['col5']]);
                    $html .= '<tr>';
                    $html .= '<td>' . hsc($this->formatDate($row['col4'])) . '</td>';
                    $html .= '<td>' . hsc($this->getLang('action_' . $row['col2'])) . '</td>';
                    $html .= '<td>' . ($row['col3'] ? userlink($row['col3']) : '') . '</td>';
                    $html .= '<td>' . hsc($this->getLang('status_' . $row['col1'])) . '</td>';
                    $html .= '<td><a href="' . hsc($revlink) . '">' . hsc($row['col5']) . '</a></td>';
                    $html .= '<td>' . ($row['col9'] ? userlink($row['col9']) : '') . '</td>';
                    $html .= '<td>' . ($row['col10'] ? userlink($row['col10']) : '') . '</td>';
                    $training = $row['col14'] ? $this->getLang('training_' . $row['col14']) : '';
                    $trainingNote = $row['col15']
                        ? '<br><small>' . hsc($row['col15']) . '</small>'
                        : '';
                    $html .= '<td>' . hsc($training) . $trainingNote . '</td>';
                    $html .= '<td>' . hsc(Constants::formatVersion((string)$row['col6'])) . '</td>';
                    $html .= '<td>' . hsc($row['col18']) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
            }
        }
        $html .= '</div>';
        $renderer->doc .= $html;
        return true;
    }

    protected function resolvePageSelection(string $input, array $pages): string
    {
        if ($input === '') return '';

        $clean = cleanID($input);
        foreach ($pages as $page) {
            if ($page['pid'] === $clean || strcasecmp($page['pid'], $input) === 0) {
                return $page['pid'];
            }
        }

        $matches = [];
        foreach ($pages as $page) {
            if (
                strcasecmp($page['title'], $input) === 0 ||
                strcasecmp($page['label'], $input) === 0
            ) {
                $matches[] = $page['pid'];
            }
        }

        return count($matches) === 1 ? $matches[0] : '';
    }

    protected function pageTitle(string $pid): string
    {
        $title = '';
        if (function_exists('p_get_first_heading')) {
            $title = trim((string)p_get_first_heading($pid));
        }
        return $title !== '' ? $title : $pid;
    }

    protected function formatDate($iso): string
    {
        if (!$iso) return '';
        $ts = strtotime($iso);
        return $ts ? dformat($ts) : $iso;
    }
}
