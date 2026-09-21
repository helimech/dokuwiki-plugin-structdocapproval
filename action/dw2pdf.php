<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

/**
 * Adds Struct DocApproval placeholders to DW2PDF templates.
 *
 * Available placeholders:
 *   @DOCREV@           Published document revision, or DRAFT for a working revision
 *   @DOCDATE@          Publication date for a Published revision
 *   @DOCPUBLISHEDBY@   Publisher display name
 *   @DOCSTATUS@        Published or WORKING DRAFT - NOT APPROVED
 *   @DOCLASTPUBREV@    Previous/current Published document revision
 *   @DOCLASTPUBDATE@   Previous/current Published date
 */
class action_plugin_structdocapproval_dw2pdf extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PLUGIN_DW2PDF_REPLACE', 'BEFORE', $this, 'replacePlaceholders');
    }

    public function replacePlaceholders(Event $event): void
    {
        if (!is_array($event->data) || !isset($event->data['replace'])) return;

        $replace =& $event->data['replace'];
        foreach ([
            '@DOCREV@',
            '@DOCDATE@',
            '@DOCPUBLISHEDBY@',
            '@DOCSTATUS@',
            '@DOCLASTPUBREV@',
            '@DOCLASTPUBDATE@',
        ] as $token) {
            if (!array_key_exists($token, $replace)) $replace[$token] = '';
        }

        $pid = cleanID((string)($event->data['id'] ?? ''));
        if ($pid === '') return;

        /** @var helper_plugin_structdocapproval_db $db */
        $db = plugin_load('helper', 'structdocapproval_db');
        if (!$db || !$db->isControlled($pid)) return;

        $context = is_array($event->data['context'] ?? null) ? $event->data['context'] : [];
        $revision = (int)($context['rev'] ?? 0);

        if (!$revision) {
            global $REV;
            $revision = (int)$REV;
        }
        if (!$revision) $revision = (int)@filemtime(wikiFN($pid));

        $record = $revision ? WorkflowRecord::latestForRevision($pid, $revision) : null;
        if (!$record) $record = WorkflowRecord::latest($pid);
        if (!$record) return;

        if ($record->get('status') === Constants::STATUS_PUBLISHED) {
            $version = Constants::formatVersion((string)$record->get('version'));
            if ($version === '') $version = $this->getLang('version_unset');
            $date = $this->formatDate((string)($record->get('published_at') ?: $record->get('datetime')));

            $replace['@DOCREV@'] = hsc($version);
            $replace['@DOCDATE@'] = hsc($date);
            $replace['@DOCPUBLISHEDBY@'] = hsc($this->displayName((string)($record->get('published_by') ?: $record->get('actor'))));
            $replace['@DOCSTATUS@'] = hsc($this->getLang('dw2pdf_published'));
            $replace['@DOCLASTPUBREV@'] = hsc($version);
            $replace['@DOCLASTPUBDATE@'] = hsc($date);
            return;
        }

        $replace['@DOCREV@'] = 'DRAFT';
        $replace['@DOCSTATUS@'] = hsc($this->getLang('dw2pdf_working'));

        $previous = WorkflowRecord::latestPublished($pid, (int)$record->get('revision'));
        if ($previous) {
            $prevVersion = Constants::formatVersion((string)$previous->get('version'));
            if ($prevVersion === '') $prevVersion = $this->getLang('version_unset');
            $replace['@DOCLASTPUBREV@'] = hsc($prevVersion);
            $replace['@DOCLASTPUBDATE@'] = hsc($this->formatDate((string)($previous->get('published_at') ?: $previous->get('datetime'))));
        }
    }

    protected function displayName(string $user): string
    {
        if ($user === '') return '';
        global $auth;
        if ($auth) {
            $data = $auth->getUserData($user);
            if ($data && !empty($data['name'])) return (string)$data['name'];
        }
        return $user;
    }

    protected function formatDate(string $iso): string
    {
        if ($iso === '') return '';
        $ts = strtotime($iso);
        return $ts ? dformat($ts) : $iso;
    }
}
