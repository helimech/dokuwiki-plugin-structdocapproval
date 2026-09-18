<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;

class helper_plugin_structdocapproval_db extends Plugin
{
    protected $db;

    /** @return SQLiteDB|null */
    public function getDB()
    {
        if ($this->db) return $this->db;
        /** @var helper_plugin_struct_db $struct */
        $struct = plugin_load('helper', 'struct_db');
        if (!$struct) return null;
        $this->db = $struct->getDB(false);
        return $this->db ?: null;
    }

    public function isControlled(?string $pid = null): bool
    {
        global $ID;
        $pid = $pid ?: $ID;
        if (!$this->getDB()) return false;
        return Assignments::getInstance()->isControlled($pid);
    }

    public function hasRole(string $pid, string $role): bool
    {
        return Assignments::getInstance()->userHasRole($pid, $role);
    }

    public function hasAnyRole(string $pid): bool
    {
        return Assignments::getInstance()->userHasAnyRole($pid);
    }

    public function canViewWorking(string $pid): bool
    {
        if (auth_isadmin()) return true;
        if (auth_quickaclcheck($pid) >= AUTH_EDIT) return true;
        return $this->hasAnyRole($pid);
    }

    public function canSubmit(string $pid): bool
    {
        return auth_isadmin() || auth_quickaclcheck($pid) >= AUTH_EDIT;
    }

    public function canReview(string $pid, string $assignedReviewer): bool
    {
        global $INPUT;
        if (auth_isadmin()) return true;
        return $assignedReviewer !== '' && $INPUT->server->str('REMOTE_USER') === $assignedReviewer;
    }

    public function canTrain(string $pid): bool
    {
        return auth_isadmin() || $this->hasRole($pid, Constants::ROLE_TRAINING);
    }

    public function canPublish(string $pid): bool
    {
        return auth_isadmin() || $this->hasRole($pid, Constants::ROLE_PUBLISHER);
    }
}
