<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\Form\CheckableElement;
use dokuwiki\Form\HTMLElement;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;
use dokuwiki\plugin\structdocapproval\meta\Constants;

class action_plugin_structdocapproval_revisions extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('FORM_REVISIONS_OUTPUT', 'BEFORE', $this, 'handleRevisions');
    }

    public function handleRevisions(Event $event): void
    {
        global $INFO;
        /** @var helper_plugin_structdocapproval_db $db */
        $db = plugin_load('helper', 'structdocapproval_db');
        if (!$db || !$db->isControlled($INFO['id']) || !$db->canViewWorking($INFO['id'])) return;

        $form = $event->data;
        $rev = null;
        $count = $form->elementCount();
        for ($i = 0; $i < $count; $i++) {
            $el = $form->getElementAt($i);
            if ($el instanceof CheckableElement && $el->attr('name') === 'rev2[]') {
                $rev = (int)$el->attr('value');
                continue;
            }
            if (!$rev || !($el instanceof HTMLElement) || trim((string)$el->val()) === '') continue;
            $record = WorkflowRecord::latestForRevision($INFO['id'], $rev);
            if (!$record) continue;

            $label = $this->getLang('status_' . $record->get('status'));
            if ($record->get('version')) $label .= ' · ' . $this->getLang('version') . ' ' . Constants::formatVersion((string)$record->get('version'));
            if ($record->get('reviewed_by')) $label .= ' · ' . $this->getLang('reviewed_by') . ' ' . hsc(userlink($record->get('reviewed_by'), true));
            if ($record->get('training_by')) $label .= ' · ' . $this->getLang('training') . ': ' . hsc(userlink($record->get('training_by'), true));
            $el->val($el->val() . ' <span class="plugin-structdocapproval-revision-status">' . hsc($label) . '</span>');
        }
    }
}
