<?php

use dokuwiki\Extension\Plugin;

class helper_plugin_structdocapproval_assignments extends Plugin
{
    public function matchPagePattern(string $pattern, string $page, ?string $pns = null): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') return false;
        if (trim($pattern, ':') === '**') return true;

        if ($pattern[0] === '/') {
            return @preg_match($pattern, ':' . $page) === 1;
        }

        if ($pns === null) $pns = ':' . getNS($page) . ':';
        $ans = ':' . cleanID($pattern) . ':';

        if (substr($pattern, -2) === '**') {
            return strpos($pns, $ans) === 0;
        }
        if (substr($pattern, -1) === '*') {
            return $ans === $pns;
        }
        return cleanID($pattern) === $page;
    }

    public function validPattern(string $pattern): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') return false;
        if ($pattern[0] !== '/') return true;
        return @preg_match($pattern, '') !== false;
    }
}
