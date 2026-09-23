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

        if ($old === $new) return;

        // Struct already renames every data_<schema> table during a Move operation,
        // including data_struct_docapproval. Do not rename that Struct-owned table twice.
        //
        // Our materialized assignment table is plugin-owned. The destination PID can
        // already exist there (for example because it was materialized during the move),
        // so a blind UPDATE old -> new can violate the unique/primary-key constraint.
        // Preserve the source assignment with REPLACE, then remove the obsolete source
        // row. If the same move event is encountered again, there is no source row and
        // this block simply becomes a no-op.
        $source = $db->queryAll(
            'SELECT controlled, reviewer, training, publisher, resolved_at FROM struct_docapproval_pages WHERE pid=? LIMIT 1',
            [$old]
        ) ?: [];
        if ($source) {
            $row = $source[0];
            $db->query(
                'REPLACE INTO struct_docapproval_pages (pid, controlled, reviewer, training, publisher, resolved_at) VALUES (?,?,?,?,?,?)',
                [$new, $row['controlled'], $row['reviewer'], $row['training'], $row['publisher'], $row['resolved_at']]
            );
            $db->query('DELETE FROM struct_docapproval_pages WHERE pid=?', [$old]);
        }

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
