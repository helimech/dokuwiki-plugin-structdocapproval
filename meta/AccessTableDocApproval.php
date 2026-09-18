<?php

namespace dokuwiki\plugin\structdocapproval\meta;

use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\AccessTableSerial;

class AccessTableDocApproval extends AccessTableSerial
{
    protected $published = 0;

    public function setPublished($published): void
    {
        $this->published = (int)$published;
    }

    protected function getSingleSql()
    {
        $cols = array_merge($this->getSingleNoninputCols(), $this->singleCols);
        $cols = implode(',', $cols);
        $vals = array_merge($this->getSingleNoninputValues(), $this->singleValues);
        $rid = $this->getRid() ?: "(SELECT (COALESCE(MAX(rid), 0 ) + 1) FROM $this->stable)";

        return "REPLACE INTO $this->stable (rid, $cols) VALUES ($rid," .
            trim(str_repeat('?,', count($vals)), ',') . ');';
    }

    protected function getMultiSql()
    {
        return '';
    }

    protected function getSingleNoninputCols()
    {
        return ['pid', 'rev', 'latest', 'published'];
    }

    protected function getSingleNoninputValues()
    {
        return [$this->pid, AccessTable::DEFAULT_REV, AccessTable::DEFAULT_LATEST, $this->published];
    }

    protected function getLastRevisionTimestamp()
    {
        $table = 'data_struct_docapproval';
        $where = 'WHERE pid = ?';
        $opts = [$this->pid];
        if ($this->ts) {
            $where .= ' AND rev > 0 AND rev <= ?';
            $opts[] = $this->ts;
        }

        $ret = $this->sqlite->queryValue("SELECT rev FROM $table $where ORDER BY rev DESC LIMIT 1", $opts);
        if ($ret !== false) $ret = (int)$ret;
        return $ret;
    }

    protected function beforeSave()
    {
        return $this->sqlite->query(
            "UPDATE $this->stable SET latest = 0 WHERE latest = 1 AND pid = ?",
            [$this->pid]
        );
    }
}
