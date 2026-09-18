<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

/**
 * Keep working revisions out of alternate page views for ordinary readers.
 * The normal show action is handled by action/show.php; this component covers
 * revision/diff/source-style and export actions that could otherwise expose the
 * current working revision.
 */
class action_plugin_structdocapproval_visibility extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleVisibility');
    }

    public function handleVisibility(Event $event): void
    {
        global $ID, $REV, $INFO;
        if (!is_string($event->data)) return;

        /** @var helper_plugin_structdocapproval_db $db */
        $db = plugin_load('helper', 'structdocapproval_db');
        if (!$db || !$db->isControlled($ID) || $db->canViewWorking($ID)) return;

        $action = $event->data;
        if ($action === 'show') return; // action/show.php handles the normal reader view.

        $protected = in_array($action, ['edit', 'source', 'raw', 'revisions', 'diff'], true) ||
            strpos($action, 'export_') === 0;
        if (!$protected) return;

        $published = WorkflowRecord::latestPublished($ID);
        if (!$published) {
            $event->data = 'denied';
            return;
        }

        $publishedRev = (int)$published->get('revision');

        // If an explicitly requested revision was itself formally Published, it is safe.
        if ($REV && WorkflowRecord::publishedForRevision($ID, (int)$REV)) return;

        $REV = $publishedRev;
        $INFO['rev'] = $publishedRev;

        // Diffs and revision lists can expose non-published history. Ordinary readers are
        // sent back to the latest Published page instead; workflow users retain full access.
        if (in_array($action, ['revisions', 'diff'], true)) {
            $event->data = 'show';
            msg($this->getLang('working_history_restricted'), 0);
        }
    }
}
