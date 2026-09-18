<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class helper_plugin_structdocapproval_sync extends Plugin
{
    /**
     * Synchronize pages affected by one rule. Extra patterns are useful when a rule's
     * pattern has just changed; pages matching the old pattern need recalculation too.
     */
    public function syncRule(int $ruleId, string $actor, array $extraPatterns = []): array
    {
        $assignments = Assignments::getInstance(true);
        $rule = $assignments->getRule($ruleId);
        if (!$rule) throw new RuntimeException('Assignment rule not found.');
        $patterns = array_merge([(string)$rule['pattern']], $extraPatterns);
        return $this->syncPatterns($patterns, $actor);
    }

    /** Synchronize all existing pages matching any supplied pattern. */
    public function syncPatterns(array $patterns, string $actor): array
    {
        /** @var helper_plugin_structdocapproval_assignments $matcher */
        $matcher = plugin_load('helper', 'structdocapproval_assignments');
        $patterns = array_values(array_unique(array_filter(array_map('trim', $patterns), static function ($p) {
            return $p !== '';
        })));

        $stats = $this->emptyStats();
        if (!$patterns) return $stats;

        foreach ($this->getAllWikiPages() as $pid) {
            $matches = false;
            foreach ($patterns as $pattern) {
                if ($matcher->matchPagePattern($pattern, $pid)) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) continue;
            $stats['matched']++;
            $this->syncPage($pid, $actor, $stats);
        }
        return $stats;
    }

    public function reconcileAll(string $actor): array
    {
        $stats = $this->emptyStats();
        foreach ($this->getAllWikiPages() as $pid) {
            $stats['matched']++;
            $this->syncPage($pid, $actor, $stats);
        }
        return $stats;
    }

    protected function syncPage(string $pid, string $actor, array &$stats): void
    {
        try {
            $assignments = Assignments::getInstance();
            $old = $assignments->getMaterialized($pid);
            $resolved = $assignments->resolvePage($pid);
            $current = WorkflowRecord::latest($pid);

            if (!$resolved['controlled'] && $old && (bool)$old['controlled'] && $current && $current->get('status') !== Constants::STATUS_PUBLISHED) {
                $stats['conflicts']++;
                return;
            }

            $changed = !$old ||
                (bool)$old['controlled'] !== (bool)$resolved['controlled'] ||
                (string)($old['reviewer'] ?? '') !== (string)$resolved[Constants::ROLE_REVIEWER] ||
                (string)($old['training'] ?? '') !== (string)$resolved[Constants::ROLE_TRAINING] ||
                (string)($old['publisher'] ?? '') !== (string)$resolved[Constants::ROLE_PUBLISHER];

            $wasControlled = $old && (bool)$old['controlled'];
            $assignments->materializePage($pid);

            // A page entering control is initialized from the revision that is live *now*.
            // This also covers a page that used to be controlled, was later excluded and
            // edited while outside the workflow, then becomes controlled again. Historical
            // workflow rows are retained, but the current live content becomes the new
            // Published baseline.
            if ($resolved['controlled'] && !$wasControlled) {
                $rev = (int)@filemtime(wikiFN($pid));
                $alreadyCurrentPublished = $current &&
                    $current->get('status') === Constants::STATUS_PUBLISHED &&
                    (int)$current->get('revision') === $rev;
                if ($rev && !$alreadyCurrentPublished) {
                    $record = new WorkflowRecord($pid);
                    $record->set('status', Constants::STATUS_PUBLISHED)
                        ->set('action', Constants::ACTION_INITIALIZE)
                        ->set('actor', $actor)
                        ->set('datetime', date('Y-m-d\TH:i'))
                        ->set('revision', $rev)
                        ->set('published_by', $actor)
                        ->set('published_at', date('Y-m-d\TH:i'));
                    $record->save();
                    /** @var helper_plugin_structdocapproval_publish $publish */
                    $publish = plugin_load('helper', 'structdocapproval_publish');
                    $publish->publishStructData($pid, $rev);
                    $stats['initialized']++;
                    return;
                }
            }

            if ($old && (bool)$old['controlled'] && !$resolved['controlled']) {
                $stats['excluded']++;
            } elseif ($changed) {
                $stats['updated']++;
            } else {
                $stats['unchanged']++;
            }
        } catch (Throwable $e) {
            $stats['errors']++;
            msg(sprintf($this->getLang('sync_page_error'), hsc($pid), hsc($e->getMessage())), -1);
        }
    }

    protected function emptyStats(): array
    {
        return ['matched'=>0,'initialized'=>0,'updated'=>0,'excluded'=>0,'conflicts'=>0,'unchanged'=>0,'errors'=>0];
    }

    public function getAllWikiPages(): array
    {
        global $conf;
        $base = $conf['datadir'];
        $pages = [];
        if (!is_dir($base)) return [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'txt') continue;
            $path = substr($file->getPathname(), strlen($base) + 1, -4);
            $pages[] = cleanID(str_replace(DIRECTORY_SEPARATOR, ':', $path));
        }
        sort($pages, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values(array_unique($pages));
    }
}
