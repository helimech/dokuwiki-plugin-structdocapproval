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

            // SchemaImporter used by migration 1 owns its own transaction. Later
            // migrations are plain SQL and can atomically commit both the schema
            // change and our version marker, preventing a successful ALTER followed
            // by a failed version write from making the next request rerun the ALTER.
            if ($version === 1) {
                if (!$this->$method($sqlite)) return false;
                if (
                    !$sqlite->query(
                        'REPLACE INTO opts (val,opt) VALUES (?,?)',
                        [$version, 'dbversion_struct_docapproval']
                    )
                ) {
                    return false;
                }
                continue;
            }

            $pdo = $sqlite->getPdo();
            $started = !$pdo->inTransaction();
            if ($started) $pdo->beginTransaction();

            try {
                if (!$this->$method($sqlite)) {
                    throw new RuntimeException(
                        'Struct DocApproval database migration ' . $version . ' failed.'
                    );
                }
                if (
                    !$sqlite->query(
                        'REPLACE INTO opts (val,opt) VALUES (?,?)',
                        [$version, 'dbversion_struct_docapproval']
                    )
                ) {
                    throw new RuntimeException(
                        'Could not record Struct DocApproval database version ' . $version . '.'
                    );
                }
                if ($started) $pdo->commit();
            } catch (Throwable) {
                if ($started && $pdo->inTransaction()) $pdo->rollBack();
                return false;
            }
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

        return $this->runSqlMigration($sqlite, 1);
    }

    protected function migration2(SQLiteDB $sqlite): bool
    {
        return $this->runSqlMigration($sqlite, 2);
    }

    protected function migration3(SQLiteDB $sqlite): bool
    {
        return $this->runSqlMigration($sqlite, 3);
    }

    protected function migration4(SQLiteDB $sqlite): bool
    {
        return $this->runSqlMigration($sqlite, 4);
    }

    protected function migration5(SQLiteDB $sqlite): bool
    {
        return $this->runSqlMigration($sqlite, 5);
    }

    protected function migration6(SQLiteDB $sqlite): bool
    {
        return $this->runSqlMigration($sqlite, 6);
    }

    protected function runSqlMigration(SQLiteDB $sqlite, int $version): bool
    {
        $file = sprintf(DOKU_PLUGIN . 'structdocapproval/db/update%04d.sql', $version);
        $sql = io_readFile($file, false);
        $statements = Tools::SQLstring2array($sql);

        // A version must be all-or-nothing. This is especially important for
        // migrations containing multiple ALTER/CREATE statements: without a
        // transaction, a failure midway leaves the schema half-upgraded while the
        // stored version still points at the previous migration.
        $pdo = $sqlite->getPdo();
        $started = !$pdo->inTransaction();
        if ($started) $pdo->beginTransaction();

        try {
            foreach ($statements as $statement) {
                if (!$sqlite->query($statement)) {
                    throw new RuntimeException(
                        'Struct DocApproval database migration ' . $version . ' failed.'
                    );
                }
            }
            if ($started) $pdo->commit();
            return true;
        } catch (Throwable) {
            if ($started && $pdo->inTransaction()) $pdo->rollBack();
            return false;
        }
    }
}
