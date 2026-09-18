<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\struct\meta\SchemaImporter;
use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\plugin\sqlite\Tools;

class action_plugin_structdocapproval_migration extends ActionPlugin
{
    public const MIN_DB_STRUCT = 19;

    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleMigrations');
    }

    public function handleMigrations(Event $event): bool
    {
        /** @var helper_plugin_struct_db $helper */
        $helper = plugin_load('helper', 'struct_db');
        if (!$helper) throw new RuntimeException('Struct DocApproval requires the Struct plugin.');
        $sqlite = $helper->getDB();

        [$structVersion, $ourVersion] = $this->getDbVersions($sqlite);
        if ((int)$structVersion < self::MIN_DB_STRUCT) {
            throw new RuntimeException('Struct is outdated. Minimum Struct database version is ' . self::MIN_DB_STRUCT . '.');
        }
        $latest = (int)trim(file_get_contents(DOKU_PLUGIN . 'structdocapproval/db/latest.version'));
        if ($ourVersion !== null && (int)$ourVersion >= $latest) return true;

        $start = ($ourVersion === null ? 0 : (int)$ourVersion) + 1;
        for ($version = $start; $version <= $latest; $version++) {
            $method = 'migration' . $version;
            if (!$this->$method($sqlite)) return false;
            $sqlite->query('REPLACE INTO opts (val,opt) VALUES (?,?)', [$version, 'dbversion_struct_docapproval']);
        }
        return true;
    }

    protected function getDbVersions(SQLiteDB $sqlite): array
    {
        $struct = null;
        $ours = null;
        $rows = $sqlite->queryAll('SELECT opt,val FROM opts WHERE opt=? OR opt=?', ['dbversion', 'dbversion_struct_docapproval']) ?: [];
        foreach ($rows as $row) {
            if ($row['opt'] === 'dbversion') $struct = $row['val'];
            if ($row['opt'] === 'dbversion_struct_docapproval') $ours = $row['val'];
        }
        return [$struct, $ours];
    }

    protected function migration1(SQLiteDB $sqlite): bool
    {
        $json = file_get_contents(DOKU_PLUGIN . 'structdocapproval/db/json/struct_docapproval0001.struct.json');
        $importer = new SchemaImporter('struct_docapproval', $json);
        $ok = (bool)$importer->build();
        if (!$ok) return false;

        $sql = io_readFile(DOKU_PLUGIN . 'structdocapproval/db/update0001.sql', false);
        foreach (Tools::SQLstring2array($sql) as $statement) {
            if (!$sqlite->query($statement)) return false;
        }
        return true;
    }
}
