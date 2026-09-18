<?php

namespace dokuwiki\plugin\structdocapproval\meta;

use dokuwiki\plugin\struct\meta\Schema;

class WorkflowRecord
{
    public const SCHEMA = 'struct_docapproval';
    public const TABLE = 'data_struct_docapproval';

    private const FIELD_COLS = [
        'status' => 'col1',
        'action' => 'col2',
        'actor' => 'col3',
        'datetime' => 'col4',
        'revision' => 'col5',
        'version' => 'col6',
        'submitted_by' => 'col7',
        'submitted_at' => 'col8',
        'assigned_reviewer' => 'col9',
        'reviewed_by' => 'col10',
        'reviewed_at' => 'col11',
        'training_by' => 'col12',
        'training_at' => 'col13',
        'training_disposition' => 'col14',
        'training_note' => 'col15',
        'published_by' => 'col16',
        'published_at' => 'col17',
        'comment' => 'col18',
    ];

    protected $pid;
    protected $rid = null;
    protected $data = [];

    public function __construct(string $pid, array $data = [], ?int $rid = null)
    {
        $this->pid = cleanID($pid);
        $this->rid = $rid;
        $defaults = array_fill_keys(array_keys(self::FIELD_COLS), '');
        $defaults['status'] = Constants::STATUS_DRAFT;
        $this->data = array_merge($defaults, $data);
    }

    public static function latest(string $pid): ?self
    {
        $db = self::db();
        if (!$db) return null;
        $rows = $db->queryAll(
            'SELECT * FROM ' . self::TABLE . ' WHERE pid = ? AND latest = 1 ORDER BY rid DESC LIMIT 1',
            [cleanID($pid)]
        ) ?: [];
        return $rows ? self::fromRow($rows[0]) : null;
    }

    public static function latestForRevision(string $pid, int $revision): ?self
    {
        $db = self::db();
        if (!$db) return null;
        $rows = $db->queryAll(
            'SELECT * FROM ' . self::TABLE . ' WHERE pid = ? AND col5 = ? ORDER BY rid DESC LIMIT 1',
            [cleanID($pid), $revision]
        ) ?: [];
        return $rows ? self::fromRow($rows[0]) : null;
    }

    public static function latestPublished(string $pid, ?int $beforeRevision = null): ?self
    {
        $db = self::db();
        if (!$db) return null;
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE pid = ? AND col1 = ?';
        $args = [cleanID($pid), Constants::STATUS_PUBLISHED];
        if ($beforeRevision !== null) {
            $sql .= ' AND col5 < ?';
            $args[] = $beforeRevision;
        }
        $sql .= ' ORDER BY col5 DESC, rid DESC LIMIT 1';
        $rows = $db->queryAll($sql, $args) ?: [];
        return $rows ? self::fromRow($rows[0]) : null;
    }

    public static function publishedForRevision(string $pid, int $revision): ?self
    {
        $db = self::db();
        if (!$db) return null;
        $rows = $db->queryAll(
            'SELECT * FROM ' . self::TABLE . ' WHERE pid = ? AND col5 = ? AND col1 = ? ORDER BY rid DESC LIMIT 1',
            [cleanID($pid), $revision, Constants::STATUS_PUBLISHED]
        ) ?: [];
        return $rows ? self::fromRow($rows[0]) : null;
    }

    public static function history(string $pid): array
    {
        $db = self::db();
        if (!$db) return [];
        $rows = $db->queryAll(
            'SELECT * FROM ' . self::TABLE . ' WHERE pid = ? ORDER BY rid ASC',
            [cleanID($pid)]
        );
        return array_map([self::class, 'fromRow'], $rows ?: []);
    }

    public static function eventsForRevision(string $pid, int $revision): array
    {
        $db = self::db();
        if (!$db) return [];
        $rows = $db->queryAll(
            'SELECT * FROM ' . self::TABLE . ' WHERE pid = ? AND col5 = ? ORDER BY rid ASC',
            [cleanID($pid), $revision]
        );
        return array_map([self::class, 'fromRow'], $rows ?: []);
    }

    public static function fromRow(array $row): self
    {
        $data = [];
        foreach (self::FIELD_COLS as $field => $col) {
            $data[$field] = $row[$col] ?? '';
        }
        return new self($row['pid'] ?? '', $data, isset($row['rid']) ? (int)$row['rid'] : null);
    }

    protected static function db()
    {
        /** @var \helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        return $helper ? $helper->getDB() : null;
    }

    public function copyForRevision(int $revision): self
    {
        $copy = new self($this->pid, $this->data);
        $copy->set('revision', $revision);
        return $copy;
    }

    public function save(): void
    {
        $schema = new Schema(self::SCHEMA, 0);
        $access = new AccessTableDocApproval($schema, $this->pid, 0, 0);
        $access->setPublished($this->get('status') === Constants::STATUS_PUBLISHED);
        if (!$access->saveData($this->data)) {
            throw new \RuntimeException('Could not save Struct DocApproval workflow record.');
        }
    }

    public function getPid(): string
    {
        return $this->pid;
    }

    public function getRid(): ?int
    {
        return $this->rid;
    }

    public function get(string $field)
    {
        return $this->data[$field] ?? '';
    }

    public function set(string $field, $value): self
    {
        if (!array_key_exists($field, self::FIELD_COLS)) {
            throw new \InvalidArgumentException('Unknown workflow field: ' . $field);
        }
        $this->data[$field] = $value === null ? '' : $value;
        return $this;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function clear(array $fields): self
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, self::FIELD_COLS)) $this->data[$field] = '';
        }
        return $this;
    }

    public function stamp(string $action, string $actor, ?int $timestamp = null): self
    {
        $timestamp = $timestamp ?: time();
        $this->set('action', $action);
        $this->set('actor', $actor);
        $this->set('datetime', date('Y-m-d\TH:i', $timestamp));
        return $this;
    }
}
