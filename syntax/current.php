<?php

use dokuwiki\plugin\structdocapproval\meta\Constants;

class syntax_plugin_structdocapproval_current extends DokuWiki_Syntax_Plugin
{
    public function getType() { return 'substition'; }
    public function getPType() { return 'block'; }
    public function getSort() { return 155; }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *struct[_]?docapproval *-+\n.*?\n?----+', $mode, 'plugin_structdocapproval_current');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        return [];
    }

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode !== 'xhtml') return false;
        if (method_exists($renderer, 'nocache')) $renderer->nocache();
        /** @var helper_plugin_structdocapproval_db $dbHelper */
        $dbHelper = plugin_load('helper', 'structdocapproval_db');
        $db = $dbHelper ? $dbHelper->getDB() : null;
        if (!$db) return false;

        $rows = $db->queryAll(
            'SELECT d.*, p.reviewer AS rule_reviewer, p.training AS rule_training, p.publisher AS rule_publisher ' .
            'FROM data_struct_docapproval d JOIN struct_docapproval_pages p ON p.pid=d.pid ' .
            'WHERE d.latest=1 AND p.controlled=1 ORDER BY d.pid'
        ) ?: [];

        global $ID;
        $html = '<div class="plugin-structdocapproval-current"><table class="inline"><thead><tr>';
        foreach (['page','status','assigned_to','submitted_by','reviewed_by','training','version','updated','history'] as $k) {
            $html .= '<th>' . hsc($this->getLang($k)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $pid = $row['pid'];
            if (auth_quickaclcheck($pid) < AUTH_READ) continue;
            if (!$dbHelper->canViewWorking($pid) && $row['col1'] !== Constants::STATUS_PUBLISHED) continue;

            $assigned = '';
            if ($row['col1'] === Constants::STATUS_AWAITING_REVIEW) $assigned = $row['col9'];
            elseif ($row['col1'] === Constants::STATUS_AWAITING_TRAINING) $assigned = $row['rule_training'];
            elseif ($row['col1'] === Constants::STATUS_READY_TO_PUBLISH) $assigned = $row['rule_publisher'];

            $training = $row['col14'] ? $this->getLang('training_' . $row['col14']) : '';
            $history = wl($ID, ['docapproval_history_page' => $pid]) . '#docapproval-history';
            $html .= '<tr>';
            $html .= '<td><a href="' . hsc(wl($pid)) . '">' . hsc($pid) . '</a></td>';
            $html .= '<td>' . hsc($this->getLang('status_' . $row['col1'])) . '</td>';
            $html .= '<td>' . $this->formatSpec($assigned) . '</td>';
            $html .= '<td>' . $this->formatUser($row['col7']) . '</td>';
            $html .= '<td>' . $this->formatUser($row['col10']) . '</td>';
            $html .= '<td>' . hsc($training) . '</td>';
            $html .= '<td>' . hsc(Constants::formatVersion((string)$row['col6'])) . '</td>';
            $html .= '<td>' . hsc($this->formatDate($row['col4'])) . '</td>';
            $html .= '<td><a href="' . hsc($history) . '">' . hsc($this->getLang('view_history')) . '</a></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        $renderer->doc .= $html;
        return true;
    }

    protected function formatUser($user): string
    {
        return $user ? userlink($user) : '';
    }

    protected function formatSpec($spec): string
    {
        return $spec ? hsc($spec) : '';
    }

    protected function formatDate($iso): string
    {
        if (!$iso) return '';
        $ts = strtotime($iso);
        return $ts ? dformat($ts) : $iso;
    }
}
