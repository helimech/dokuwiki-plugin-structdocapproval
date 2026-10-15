<?php

use dokuwiki\plugin\structdocapproval\meta\Constants;

class syntax_plugin_structdocapproval_my extends DokuWiki_Syntax_Plugin
{
    public function getType() { return 'substition'; }
    public function getPType() { return 'block'; }
    public function getSort() { return 157; }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern(
            '----+ *struct[_]?docapproval[_]?my *-+\n.*?\n?----+',
            $mode,
            'plugin_structdocapproval_my'
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

        /** @var helper_plugin_structdocapproval_tasks $tasksHelper */
        $tasksHelper = plugin_load('helper', 'structdocapproval_tasks');
        if (!$tasksHelper) return false;

        $tasks = $tasksHelper->getCurrentUserTasks();
        if (($tasks['user'] ?? '') === '') {
            $renderer->doc .= '<div class="plugin-structdocapproval-my">' .
                '<p class="docapproval-warning">' . hsc($this->getLang('my_login_required')) . '</p>' .
                '</div>';
            return true;
        }

        $counts = [
            'ack' => count($tasks['acknowledgements']),
            'review' => count($tasks['reviews']),
            'training' => count($tasks['training']),
            'publish' => count($tasks['publish']),
        ];

        $html = '<div class="plugin-structdocapproval-my">';
        $html .= '<h2>' . hsc($this->getLang('my_tasks_title')) . '</h2>';
        $html .= '<div class="docapproval-my-counts">';
        $html .= $this->countCard('docapproval-my-ack', $counts['ack'], $this->getLang('my_ack_required'));
        $html .= $this->countCard('docapproval-my-review', $counts['review'], $this->getLang('my_reviews_required'));
        $html .= $this->countCard('docapproval-my-training', $counts['training'], $this->getLang('my_training_required'));
        $html .= $this->countCard('docapproval-my-publish', $counts['publish'], $this->getLang('my_publish_required'));
        $html .= '</div>';

        $html .= $this->renderAcknowledgements($tasks['acknowledgements']);
        $html .= $this->renderWorkflowSection(
            'docapproval-my-review',
            $this->getLang('my_reviews_required'),
            $tasks['reviews'],
            $this->getLang('my_open_review')
        );
        $html .= $this->renderWorkflowSection(
            'docapproval-my-training',
            $this->getLang('my_training_required'),
            $tasks['training'],
            $this->getLang('my_open_training')
        );
        $html .= $this->renderWorkflowSection(
            'docapproval-my-publish',
            $this->getLang('my_publish_required'),
            $tasks['publish'],
            $this->getLang('my_open_publish')
        );
        $html .= $this->renderRecentAcknowledgements($tasks['recent_acknowledgements']);
        $html .= '</div>';

        $renderer->doc .= $html;
        return true;
    }

    protected function countCard(string $anchor, int $count, string $label): string
    {
        return '<a class="docapproval-my-count" href="#' . hsc($anchor) . '">' .
            '<strong>' . $count . '</strong><span>' . hsc($label) . '</span></a>';
    }

    protected function renderAcknowledgements(array $rows): string
    {
        $html = '<section id="docapproval-my-ack" class="docapproval-my-section">';
        $html .= '<h3>' . hsc($this->getLang('my_ack_required')) .
            ' <span class="docapproval-my-heading-count">(' . count($rows) . ')</span></h3>';

        if (!$rows) {
            return $html . '<p class="docapproval-my-none">' .
                hsc($this->getLang('my_none_outstanding')) . '</p></section>';
        }

        $html .= '<div class="docapproval-my-tablewrap"><table class="inline">';
        $html .= '<thead><tr>';
        foreach (['page', 'version', 'my_published', 'change_summary', 'actions'] as $key) {
            $html .= '<th>' . hsc($this->getLang($key)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $pid = (string)$row['pid'];
            $revision = (int)$row['content_revision'];
            $version = Constants::formatVersion((string)$row['version']);
            if ($version === '') $version = $this->getLang('version_unset');
            $url = wl($pid, ['rev' => $revision]);

            $html .= '<tr>';
            $html .= '<td><a href="' . hsc($url) . '">' . hsc($pid) . '</a></td>';
            $html .= '<td>' . hsc($version) . '</td>';
            $html .= '<td>' . hsc($this->formatTimestamp((int)$row['published_at'])) . '</td>';
            $html .= '<td>' . $this->renderSummary((string)$row['change_summary']) . '</td>';
            $html .= '<td><a href="' . hsc($url) . '">' . hsc($this->getLang('my_open_ack')) . '</a></td>';
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div></section>';
    }

    protected function renderWorkflowSection(string $id, string $title, array $rows, string $openLabel): string
    {
        $html = '<section id="' . hsc($id) . '" class="docapproval-my-section">';
        $html .= '<h3>' . hsc($title) .
            ' <span class="docapproval-my-heading-count">(' . count($rows) . ')</span></h3>';

        if (!$rows) {
            return $html . '<p class="docapproval-my-none">' .
                hsc($this->getLang('my_none_outstanding')) . '</p></section>';
        }

        $html .= '<div class="docapproval-my-tablewrap"><table class="inline">';
        $html .= '<thead><tr>';
        foreach (['page', 'my_waiting_since', 'change_summary', 'actions'] as $key) {
            $html .= '<th>' . hsc($this->getLang($key)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $pid = (string)$row['pid'];
            $url = wl($pid);
            $html .= '<tr>';
            $html .= '<td><a href="' . hsc($url) . '">' . hsc($pid) . '</a></td>';
            $html .= '<td>' . hsc($this->formatIsoDate((string)$row['updated'])) . '</td>';
            $html .= '<td>' . $this->renderSummary((string)$row['change_summary']) . '</td>';
            $html .= '<td><a href="' . hsc($url) . '">' . hsc($openLabel) . '</a></td>';
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div></section>';
    }

    protected function renderRecentAcknowledgements(array $rows): string
    {
        if (!$rows) return '';

        $html = '<details class="docapproval-my-recent">';
        $html .= '<summary><strong>' . hsc($this->getLang('my_recent_acknowledgements')) .
            '</strong> (' . count($rows) . ')</summary>';
        $html .= '<div class="docapproval-my-tablewrap"><table class="inline">';
        $html .= '<thead><tr>';
        foreach (['page', 'version', 'my_acknowledged_on', 'change_summary', 'actions'] as $key) {
            $html .= '<th>' . hsc($this->getLang($key)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $pid = (string)$row['pid'];
            $revision = (int)$row['content_revision'];
            $url = $revision ? wl($pid, ['rev' => $revision]) : wl($pid);
            $version = Constants::formatVersion((string)$row['version']);
            if ($version === '') $version = $this->getLang('version_unset');

            $html .= '<tr>';
            $html .= '<td><a href="' . hsc($url) . '">' . hsc($pid) . '</a></td>';
            $html .= '<td>' . hsc($version) . '</td>';
            $html .= '<td>' . hsc($this->formatTimestamp((int)$row['acknowledged_at'])) . '</td>';
            $html .= '<td>' . $this->renderSummary((string)$row['change_summary']) . '</td>';
            $html .= '<td><a href="' . hsc($url) . '">' . hsc($this->getLang('my_view')) . '</a></td>';
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div></details>';
    }

    protected function renderSummary(string $summary): string
    {
        $summary = trim($summary);
        if ($summary === '') {
            return '<span class="docapproval-my-summary-empty">—</span>';
        }
        return nl2br(hsc($summary));
    }

    protected function formatIsoDate(string $iso): string
    {
        if ($iso === '') return '';
        $timestamp = strtotime($iso);
        return $timestamp ? dformat($timestamp) : $iso;
    }

    protected function formatTimestamp(int $timestamp): string
    {
        return $timestamp > 0 ? dformat($timestamp) : '';
    }
}
