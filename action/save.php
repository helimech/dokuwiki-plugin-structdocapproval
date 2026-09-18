<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class action_plugin_structdocapproval_save extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'AFTER', $this, 'handleSave');
    }

    public function handleSave(Event $event): void
    {
        global $INPUT;
        $id = cleanID((string)$event->data['id']);
        $newRevision = (int)($event->data['newRevision'] ?? 0);
        if (!$newRevision || !page_exists($id)) return;

        // New pages inside an assigned namespace are picked up immediately. Existing pages
        // are normally initialized through the admin Sync/Reconcile tools.
        $assignments = Assignments::getInstance(true);
        $oldAssignment = $assignments->getMaterialized($id);
        $wasControlled = $oldAssignment && (bool)$oldAssignment['controlled'];
        $resolved = $assignments->resolvePage($id);

        // Never silently de-control an already managed page during an ordinary page save.
        // Exclusions are applied explicitly from Sync/Reconcile, which can block removal when
        // an active workflow exists. This prevents a rule edit from unexpectedly exposing a
        // working draft to normal readers on the next save.
        if ($oldAssignment && (bool)$oldAssignment['controlled'] && !$resolved['controlled']) {
            // Keep the last materialized assignment until an administrator reconciles it.
        } else {
            $assignments->materializePage($id);
        }

        if (!$assignments->isControlled($id)) return;

        $actor = $INPUT->server->str('REMOTE_USER');
        if ($actor === '') $actor = 'unknown';
        $previous = WorkflowRecord::latest($id);
        $changeType = $event->data['changeType'] ?? null;
        $minor = defined('DOKU_CHANGE_TYPE_MINOR_EDIT') && $changeType === DOKU_CHANGE_TYPE_MINOR_EDIT;

        // If an existing, previously-unmanaged page is first encountered through a newly
        // matching rule before an administrator has run Sync/Reconcile, preserve its previous
        // live revision as the Published baseline before making the new edit a Draft.
        $initializedBaseline = false;
        $oldRevision = (int)($event->data['oldRevision'] ?? 0);
        $isCreate = defined('DOKU_CHANGE_TYPE_CREATE') && $changeType === DOKU_CHANGE_TYPE_CREATE;
        if (!$wasControlled && !$isCreate && $oldRevision > 0) {
            $baseline = new WorkflowRecord($id);
            $baseline->set('status', Constants::STATUS_PUBLISHED)
                ->set('action', Constants::ACTION_INITIALIZE)
                ->set('actor', $actor)
                ->set('datetime', date('Y-m-d\TH:i'))
                ->set('revision', $oldRevision)
                ->set('published_by', $actor)
                ->set('published_at', date('Y-m-d\TH:i'));
            $baseline->save();
            /** @var helper_plugin_structdocapproval_publish $publish */
            $publish = plugin_load('helper', 'structdocapproval_publish');
            $publish->publishStructData($id, $oldRevision);
            $previous = $baseline;
            $initializedBaseline = true;
        }

        // A minor edit may preserve Published only for an already-controlled Published page
        // and only when made by an administrator or assigned Publisher. The formal publisher
        // and published-at fields are deliberately retained; actor/datetime record the minor edit.
        if (
            !$initializedBaseline &&
            $minor &&
            $this->getConf('minor_edit_preserve_published') &&
            $previous &&
            $previous->get('status') === Constants::STATUS_PUBLISHED &&
            (auth_isadmin() || $assignments->userHasRole($id, Constants::ROLE_PUBLISHER))
        ) {
            $record = $previous->copyForRevision($newRevision)->stamp(Constants::ACTION_MINOR_EDIT, $actor);
            $record->set('status', Constants::STATUS_PUBLISHED)
                ->set('comment', '');
            $record->save();
            /** @var helper_plugin_structdocapproval_publish $publish */
            $publish = plugin_load('helper', 'structdocapproval_publish');
            $publish->publishStructData($id, $newRevision);
            return;
        }

        // Any other content change begins (or returns to) a fresh Draft workflow cycle.
        $record = new WorkflowRecord($id);
        $record->set('status', Constants::STATUS_DRAFT)
            ->set('action', $minor ? Constants::ACTION_MINOR_EDIT : Constants::ACTION_EDIT)
            ->set('actor', $actor)
            ->set('datetime', date('Y-m-d\TH:i'))
            ->set('revision', $newRevision);
        $record->save();
    }

}
