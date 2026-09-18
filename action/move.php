<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_structdocapproval_move extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PLUGIN_MOVE_PAGE_RENAME', 'AFTER', $this, 'handleMove', true);
    }

    public function handleMove(Event $event, $isPage): void
    {
        if (!$isPage) return;
        $old = cleanID($event->data['src_id'] ?? '');
        $new = cleanID($event->data['dst_id'] ?? '');
        if ($old === '' || $new === '') return;

        /** @var helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        $db = $helper ? $helper->getDB() : null;
        if (!$db) return;

        $db->query('UPDATE data_struct_docapproval SET pid=? WHERE pid=?', [$new, $old]);
        $db->query('UPDATE struct_docapproval_pages SET pid=? WHERE pid=?', [$new, $old]);

        // Exact page rules follow a page rename. Wildcard and regex rules remain unchanged.
        $rows = $db->queryAll('SELECT id,pattern FROM struct_docapproval_rules') ?: [];
        foreach ($rows as $row) {
            $pattern = trim((string)$row['pattern']);
            if ($pattern === '' || $pattern[0] === '/' || substr($pattern, -1) === '*') continue;
            if (cleanID($pattern) === $old) {
                $db->query('UPDATE struct_docapproval_rules SET pattern=? WHERE id=?', [$new, (int)$row['id']]);
            }
        }
    }
}
