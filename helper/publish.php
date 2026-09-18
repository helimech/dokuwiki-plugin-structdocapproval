<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\struct\meta\Assignments as StructAssignments;

class helper_plugin_structdocapproval_publish extends Plugin
{
    /** Mark Struct data attached to a page revision as published. */
    public function publishStructData(string $pid, int $revision): void
    {
        /** @var helper_plugin_structdocapproval_db $dbHelper */
        $dbHelper = plugin_load('helper', 'structdocapproval_db');
        $sqlite = $dbHelper ? $dbHelper->getDB() : null;
        if (!$sqlite) return;

        $schemaAssignments = StructAssignments::getInstance();
        $tables = $schemaAssignments->getPageAssignments($pid);
        if (empty($tables)) return;

        foreach ($tables as $table) {
            $table = cleanID($table);
            $sqlite->query("UPDATE data_$table SET published = 0 WHERE pid = ?", [$pid]);
            $sqlite->query("UPDATE multi_$table SET published = 0 WHERE pid = ?", [$pid]);
            $sqlite->query("UPDATE data_$table SET published = 1 WHERE pid = ? AND rev = ?", [$pid, $revision]);
            $sqlite->query("UPDATE multi_$table SET published = 1 WHERE pid = ? AND rev = ?", [$pid, $revision]);
        }
    }
}
