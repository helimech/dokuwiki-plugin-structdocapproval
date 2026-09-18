<?php

use dokuwiki\plugin\structdocapproval\meta\Constants;

class syntax_plugin_structdocapproval_history extends DokuWiki_Syntax_Plugin
{
    public function getType() { return 'substition'; }
    public function getPType() { return 'block'; }
    public function getSort() { return 156; }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *struct[_]?docapprovalhistory *-+\n.*?\n?----+', $mode, 'plugin_structdocapproval_history');
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
        /** @var helper_plugin_structdocapproval_db $dbHelper */
        $dbHelper = plugin_load('helper', 'structdocapproval_db');
        $db = $dbHelper ? $dbHelper->getDB() : null;
        if (!$db) return false;

        $pages = $db->queryAll('SELECT DISTINCT pid FROM data_struct_docapproval ORDER BY pid') ?: [];
        $selected = cleanID($INPUT->str('docapproval_history_page'));

        $html = '<div id="docapproval-history" class="plugin-structdocapproval-history">';
        $html .= '<form method="get" action="' . hsc(DOKU_BASE . DOKU_SCRIPT) . '">';
        $html .= '<input type="hidden" name="id" value="' . hsc($ID) . '" />';
        $html .= '<label>' . hsc($this->getLang('select_page')) . ' <select name="docapproval_history_page">';
        $html .= '<option value="">' . hsc($this->getLang('select_page_prompt')) . '</option>';
        foreach ($pages as $row) {
            $pid = $row['pid'];
            if (auth_quickaclcheck($pid) < AUTH_READ || !$dbHelper->canViewWorking($pid)) continue;
            $sel = $pid === $selected ? ' selected' : '';
            $html .= '<option value="' . hsc($pid) . '"' . $sel . '>' . hsc($pid) . '</option>';
        }
        $html .= '</select></label> <button type="submit">' . hsc($this->getLang('show_history')) . '</button></form>';

        if ($selected !== '') {
            if (auth_quickaclcheck($selected) < AUTH_READ || !$dbHelper->canViewWorking($selected)) {
                $html .= '<p class="docapproval-warning">' . hsc($this->getLang('history_denied')) . '</p>';
            } else {
                $rows = $db->queryAll('SELECT * FROM data_struct_docapproval WHERE pid=? ORDER BY rid ASC', [$selected]) ?: [];
                $html .= '<h3><a href="' . hsc(wl($selected)) . '">' . hsc($selected) . '</a></h3>';
                $html .= '<table class="inline"><thead><tr>';
                foreach (['date','action','actor','status','revision','assigned_reviewer','reviewed_by','training','version','comment'] as $k) {
                    $html .= '<th>' . hsc($this->getLang($k)) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    $revlink = wl($selected, ['rev'=>$row['col5']]);
                    $html .= '<tr>';
                    $html .= '<td>' . hsc($this->formatDate($row['col4'])) . '</td>';
                    $html .= '<td>' . hsc($this->getLang('action_' . $row['col2'])) . '</td>';
                    $html .= '<td>' . ($row['col3'] ? userlink($row['col3']) : '') . '</td>';
                    $html .= '<td>' . hsc($this->getLang('status_' . $row['col1'])) . '</td>';
                    $html .= '<td><a href="' . hsc($revlink) . '">' . hsc($row['col5']) . '</a></td>';
                    $html .= '<td>' . ($row['col9'] ? userlink($row['col9']) : '') . '</td>';
                    $html .= '<td>' . ($row['col10'] ? userlink($row['col10']) : '') . '</td>';
                    $training = $row['col14'] ? $this->getLang('training_' . $row['col14']) : '';
                    $html .= '<td>' . hsc($training) . ($row['col15'] ? '<br><small>' . hsc($row['col15']) . '</small>' : '') . '</td>';
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

    protected function formatDate($iso): string
    {
        if (!$iso) return '';
        $ts = strtotime($iso);
        return $ts ? dformat($ts) : $iso;
    }
}
