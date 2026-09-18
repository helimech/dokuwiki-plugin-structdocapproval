<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;
use dokuwiki\plugin\structdocapproval\meta\Constants;

class action_plugin_structdocapproval_show extends ActionPlugin
{
    protected static $forcedPublishedRev = 0;

    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleShow');
        $controller->register_hook('HTML_SHOWREV_OUTPUT', 'BEFORE', $this, 'handleShowrev');
    }

    public function handleShow(Event $event): void
    {
        if ($event->data !== 'show') return;
        global $ID, $REV, $INFO;

        /** @var helper_plugin_structdocapproval_db $db */
        $db = plugin_load('helper', 'structdocapproval_db');
        if (!$db || !$db->isControlled($ID) || $db->canViewWorking($ID)) return;

        $latest = WorkflowRecord::latest($ID);
        if (!$latest) return;

        // Explicit old revisions are allowed only if that exact content revision was published.
        if ($REV) {
            if (WorkflowRecord::publishedForRevision($ID, (int)$REV)) return;
        } elseif ($latest->get('status') === Constants::STATUS_PUBLISHED) {
            return;
        }

        $published = WorkflowRecord::latestPublished($ID);
        if (!$published) {
            $event->data = 'denied';
            return;
        }

        self::$forcedPublishedRev = (int)$published->get('revision');
        $REV = self::$forcedPublishedRev;
        $INFO['rev'] = self::$forcedPublishedRev;
    }

    public function handleShowrev(Event $event): void
    {
        if (self::$forcedPublishedRev) $event->preventDefault();
    }
}
