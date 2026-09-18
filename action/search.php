<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

/**
 * Prevent search snippets from exposing working document text to ordinary readers.
 * DokuWiki's core search index follows the current page revision, so during an active
 * workflow a result can still be a false positive for a term present only in the draft;
 * however the snippet is generated from the Published revision and clicking the result
 * is likewise forced to Published by action/show.php.
 */
class action_plugin_structdocapproval_search extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('FULLTEXT_SNIPPET_CREATE', 'BEFORE', $this, 'publishedSnippet');
        $controller->register_hook('SEARCH_QUERY_FULLPAGE', 'AFTER', $this, 'filterUnpublishedPages');
    }

    public function publishedSnippet(Event $event): void
    {
        $pid = cleanID((string)($event->data['id'] ?? ''));
        if ($pid === '') return;

        /** @var helper_plugin_structdocapproval_db $db */
        $db = plugin_load('helper', 'structdocapproval_db');
        if (!$db || !$db->isControlled($pid) || $db->canViewWorking($pid)) return;

        $published = WorkflowRecord::latestPublished($pid);
        if (!$published) {
            $event->data['text'] = '';
            return;
        }

        $event->data['text'] = rawWiki($pid, (int)$published->get('revision'));
    }

    public function filterUnpublishedPages(Event $event): void
    {
        if (!is_array($event->result)) return;
        /** @var helper_plugin_structdocapproval_db $db */
        $db = plugin_load('helper', 'structdocapproval_db');
        if (!$db) return;

        foreach (array_keys($event->result) as $pid) {
            if (!$db->isControlled($pid) || $db->canViewWorking($pid)) continue;
            if (!WorkflowRecord::latestPublished($pid)) unset($event->result[$pid]);
        }
    }
}
