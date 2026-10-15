<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class action_plugin_structdocapproval_acknowledge extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handlePost');
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'appendBox');
    }

    public function handlePost(Event $event): void
    {
        if ($event->data !== 'show') return;
        global $INPUT, $ID, $USERINFO;

        $in = $INPUT->arr('structdocapproval_ack');
        if (($in['action'] ?? '') !== 'acknowledge') return;
        if (!checkSecurityToken()) return;

        $user = $INPUT->server->str('REMOTE_USER');
        $publicationRevision = (int)($in['publication_revision'] ?? 0);
        $contentRevision = (int)($in['content_revision'] ?? 0);
        if (empty($in['confirm'])) {
            msg($this->getLang('ack_confirm_required'), -1);
            send_redirect(wl($ID, '', true, '&'));
            return;
        }

        try {
            /** @var helper_plugin_structdocapproval_acknowledge $helper */
            $helper = plugin_load('helper', 'structdocapproval_acknowledge');
            if (!$helper) throw new RuntimeException('Acknowledgement helper is unavailable.');
            $helper->acknowledge(
                $ID,
                $user,
                $publicationRevision,
                $contentRevision,
                $USERINFO['grps'] ?? []
            );
            msg($this->getLang('ack_saved'), 1);
        } catch (Throwable $e) {
            msg($e->getMessage(), -1);
        }

        send_redirect(wl($ID, '', true, '&'));
    }

    public function appendBox(Event $event): void
    {
        global $ACT, $ID, $REV, $INPUT, $USERINFO;
        if ($ACT !== 'show') return;

        $user = $INPUT->server->str('REMOTE_USER');
        if ($user === '' || auth_quickaclcheck($ID) < AUTH_READ) return;

        /** @var helper_plugin_structdocapproval_db $db */
        $db = plugin_load('helper', 'structdocapproval_db');
        if (!$db || !$db->isControlled($ID)) return;

        $currentPublished = WorkflowRecord::latestPublished($ID);
        if (!$currentPublished) return;
        $publishedRevision = (int)$currentPublished->get('revision');

        // Only append acknowledgement/report UI when the page being displayed is the
        // authoritative current Published content. This also works for ordinary readers
        // who have been forced to the last Published revision while a newer Draft exists.
        if ($REV) {
            if ((int)$REV !== $publishedRevision) return;
        } else {
            $latest = WorkflowRecord::latest($ID);
            if (!$latest || $latest->get('status') !== Constants::STATUS_PUBLISHED) return;
            if ((int)$latest->get('revision') !== $publishedRevision) return;
        }

        /** @var helper_plugin_structdocapproval_acknowledge $helper */
        $helper = plugin_load('helper', 'structdocapproval_acknowledge');
        if (!$helper) return;

        $requirementRevision = $helper->getCurrentRequirementRevision($ID);
        if (!$requirementRevision) return;

        if ($helper->isRequired($ID, $requirementRevision, $user)) {
            $ack = $helper->getAcknowledgement($ID, $user, $requirementRevision);
            $event->data .= $ack
                ? $this->renderDone($currentPublished, $ack)
                : $this->renderRequired($currentPublished, $requirementRevision, $helper, $user);
        }

        if ($this->canViewReport($ID, $user, $db)) {
            $report = $this->renderReport($ID, $requirementRevision);
            if ($report !== '') $event->data .= $report;
        }
    }

    protected function canViewReport(string $pid, string $user, helper_plugin_structdocapproval_db $db): bool
    {
        $mode = (string)$this->getConf('ack_report_visibility');
        if ($mode === '') $mode = 'managers';

        switch ($mode) {
            case 'off':
                return false;
            case 'admins':
                return auth_isadmin();
            case 'managers':
                return auth_ismanager();
            case 'publishers':
                return $db->canPublish($pid);
            case 'workflow':
                return $db->canViewWorking($pid);
            case 'authenticated':
                return $user !== '';
            default:
                return false;
        }
    }

    protected function renderReport(string $pid, int $requirementRevision): string
    {
        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        if (!$publication) return '';

        $data = $publication->getComplianceForPage($pid, $requirementRevision);
        if (!$data || !count($data['required_users'])) return '';

        $mode = (string)$this->getConf('ack_report_content');
        if (!in_array($mode, ['acknowledged', 'pending', 'both'], true)) $mode = 'both';

        $required = count($data['required_users']);
        $complete = count($data['acknowledged_users']);
        $outstanding = count($data['outstanding_users']);
        $version = trim((string)$data['version']);
        if ($version === '') $version = $this->getLang('version_unset');

        $html = '<div class="plugin-structdocapproval-ack-report">';
        $html .= '<div class="ack-report-title"><strong>' . hsc($this->getLang('ack_report_title')) . '</strong></div>';
        $html .= '<p>' . hsc(sprintf(
            $this->getLang('ack_report_summary'),
            $version,
            $complete,
            $required,
            $outstanding
        )) . '</p>';

        $html .= '<div class="ack-report-sections">';

        if ($mode === 'acknowledged' || $mode === 'both') {
            $html .= '<div class="ack-report-section acknowledged">';
            $html .= '<strong>' . hsc($this->getLang('ack_report_acknowledged_heading')) . '</strong>';
            if (!$data['acknowledged_users']) {
                $html .= '<p><em>' . hsc($this->getLang('ack_report_none')) . '</em></p>';
            } else {
                $html .= '<ul>';
                foreach ($data['acknowledged_users'] as $ackUser => $timestamp) {
                    $html .= '<li>' . userlink($ackUser);
                    if ($timestamp) $html .= ' — ' . hsc(dformat((int)$timestamp));
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div>';
        }

        if ($mode === 'pending' || $mode === 'both') {
            $html .= '<div class="ack-report-section pending">';
            $html .= '<strong>' . hsc($this->getLang('ack_report_pending_heading')) . '</strong>';
            if (!$data['outstanding_users']) {
                $html .= '<p><em>' . hsc($this->getLang('ack_report_none')) . '</em></p>';
            } else {
                $html .= '<ul>';
                foreach ($data['outstanding_users'] as $pendingUser) {
                    $html .= '<li>' . userlink($pendingUser) . '</li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    protected function renderRequired(
        WorkflowRecord $published,
        int $requirementRevision,
        helper_plugin_structdocapproval_acknowledge $helper,
        string $user
    ): string {
        $pid = $published->getPid();
        $revision = (int)$published->get('revision');
        $version = Constants::formatVersion((string)$published->get('version'));
        if ($version === '') $version = $this->getLang('version_unset');
        $publishedAt = $this->formatDate((string)($published->get('published_at') ?: $published->get('datetime')));
        $previous = $helper->getLatestAcknowledgement($pid, $user, $requirementRevision);

        $html = '<div class="plugin-structdocapproval-ack required">';
        $html .= '<div class="ack-title"><strong>' . hsc($this->getLang('ack_required_title')) . '</strong></div>';
        $html .= '<p>' . hsc(sprintf($this->getLang('ack_required_context'), $version, $publishedAt)) . '</p>';

        if ($previous && (int)$previous['content_revision'] < $revision) {
            $diff = wl($pid, [
                'do' => 'diff',
                'rev2[0]' => (int)$previous['content_revision'],
                'rev2[1]' => $revision,
            ], true, '&');
            $html .= '<p><a href="' . hsc($diff) . '">' . hsc($this->getLang('ack_view_changes')) . '</a></p>';
        }

        $html .= '<form method="post" class="docapproval-ack-form">';
        $html .= '<input type="hidden" name="sectok" value="' . hsc(getSecurityToken()) . '" />';
        $html .= '<input type="hidden" name="structdocapproval_ack[action]" value="acknowledge" />';
        $html .= '<input type="hidden" name="structdocapproval_ack[publication_revision]" value="' . $requirementRevision . '" />';
        $html .= '<input type="hidden" name="structdocapproval_ack[content_revision]" value="' . $revision . '" />';
        $html .= '<label><input type="checkbox" name="structdocapproval_ack[confirm]" value="1" /> ' .
            hsc($this->getLang('ack_confirm_label')) . '</label>';
        $html .= '<button type="submit">' . hsc($this->getLang('ack_button')) . '</button>';
        $html .= '</form></div>';
        return $html;
    }

    protected function renderDone(WorkflowRecord $published, array $ack): string
    {
        $version = Constants::formatVersion((string)$published->get('version'));
        if ($version === '') $version = $this->getLang('version_unset');
        $date = !empty($ack['acknowledged_at']) ? dformat((int)$ack['acknowledged_at']) : '';

        return '<div class="plugin-structdocapproval-ack done">' .
            '<div class="ack-title"><strong>' . hsc($this->getLang('ack_complete_title')) . '</strong></div>' .
            '<p>' . hsc(sprintf($this->getLang('ack_complete_context'), $version, $date)) . '</p>' .
            '</div>';
    }

    protected function formatDate(string $iso): string
    {
        if ($iso === '') return '';
        $ts = strtotime($iso);
        return $ts ? dformat($ts) : $iso;
    }
}
