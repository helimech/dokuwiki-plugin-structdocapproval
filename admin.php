<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\plugin\structdocapproval\meta\Assignments;

class admin_plugin_structdocapproval extends AdminPlugin
{
    public function getMenuSort()
    {
        return 555;
    }

    public function forAdminOnly()
    {
        return true;
    }

    public function handle()
    {
        global $INPUT, $ID;
        $action = $INPUT->str('action');
        if ($action === '') return;
        if (!checkSecurityToken()) return;

        try {
            $assignments = Assignments::getInstance(true);
            /** @var helper_plugin_structdocapproval_assignments $matcher */
            $matcher = plugin_load('helper', 'structdocapproval_assignments');
            /** @var helper_plugin_structdocapproval_sync $sync */
            $sync = plugin_load('helper', 'structdocapproval_sync');
            $actor = $INPUT->server->str('REMOTE_USER') ?: 'admin';

            if ($action === 'add_rule') {
                $rule = $INPUT->arr('newrule');
                $pattern = trim((string)($rule['pattern'] ?? ''));
                if (!$matcher->validPattern($pattern)) throw new RuntimeException($this->getLang('invalid_pattern'));
                $assignments->addRule(
                    ((string)($rule['include'] ?? '+')) === '+',
                    $pattern,
                    (string)($rule['reviewer'] ?? ''),
                    (string)($rule['training'] ?? ''),
                    (string)($rule['publisher'] ?? '')
                );
                msg($this->getLang('rule_added'), 1);
            } elseif ($action === 'save_rules') {
                foreach ($INPUT->arr('rules') as $id => $rule) {
                    $pattern = trim((string)($rule['pattern'] ?? ''));
                    if (!$matcher->validPattern($pattern)) throw new RuntimeException($this->getLang('invalid_pattern') . ': ' . $pattern);
                    $assignments->updateRule(
                        (int)$id,
                        ((string)($rule['include'] ?? '+')) === '+',
                        $pattern,
                        (string)($rule['reviewer'] ?? ''),
                        (string)($rule['training'] ?? ''),
                        (string)($rule['publisher'] ?? '')
                    );
                }
                msg($this->getLang('rules_saved'), 1);
            } elseif (preg_match('/^move_(up|down):(\d+)$/', $action, $m)) {
                $assignments->moveRule((int)$m[2], $m[1] === 'up' ? -1 : 1);
                msg($this->getLang('rule_moved'), 1);
            } elseif (preg_match('/^remove:(\d+)$/', $action, $m)) {
                $id = (int)$m[1];
                $old = $assignments->getRule($id);
                if (!$old) throw new RuntimeException($this->getLang('rule_not_found'));
                $assignments->deleteRule($id);
                // Removing a rule can change the final resolved assignment for every page
                // that used to match it, so recalculate that old pattern immediately.
                $stats = $sync->syncPatterns([(string)$old['pattern']], $actor);
                $this->reportSync($stats, 'remove_done');
            } elseif (preg_match('/^sync:(\d+)$/', $action, $m)) {
                $id = (int)$m[1];
                $old = $assignments->getRule($id);
                if (!$old) throw new RuntimeException($this->getLang('rule_not_found'));

                // Sync Rule doubles as Save & Sync for this row. This prevents an admin
                // from editing a rule and accidentally syncing the stale database value.
                $postedRules = $INPUT->arr('rules');
                $rule = $postedRules[$id] ?? null;
                if (is_array($rule)) {
                    $pattern = trim((string)($rule['pattern'] ?? ''));
                    if (!$matcher->validPattern($pattern)) throw new RuntimeException($this->getLang('invalid_pattern') . ': ' . $pattern);
                    $assignments->updateRule(
                        $id,
                        ((string)($rule['include'] ?? '+')) === '+',
                        $pattern,
                        (string)($rule['reviewer'] ?? ''),
                        (string)($rule['training'] ?? ''),
                        (string)($rule['publisher'] ?? '')
                    );
                }
                $stats = $sync->syncRule($id, $actor, [(string)$old['pattern']]);
                $this->reportSync($stats, 'sync_done');
            } elseif ($action === 'reconcile') {
                $stats = $sync->reconcileAll($actor);
                $this->reportSync($stats, 'reconcile_done');
            }
        } catch (Throwable $e) {
            msg($e->getMessage(), -1);
        }

        send_redirect(wl($ID, ['do'=>'admin','page'=>'structdocapproval'], true, '&'));
    }

    protected function reportSync(array $s, string $langKey): void
    {
        $text = sprintf(
            $this->getLang($langKey),
            $s['matched'], $s['initialized'], $s['updated'], $s['excluded'], $s['conflicts'], $s['errors']
        );
        msg($text, ($s['errors'] || $s['conflicts']) ? 0 : 1);
    }

    public function html()
    {
        global $ID;
        echo '<h1>' . hsc($this->getLang('menu')) . '</h1>';
        echo '<p>' . hsc($this->getLang('admin_intro')) . '</p>';

        try {
            $assignments = Assignments::getInstance(true);
            $rules = $assignments->getRules();
        } catch (Throwable $e) {
            msg($e->getMessage(), -1);
            return;
        }

        echo '<form method="post" action="' . hsc(wl($ID)) . '">';
        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="structdocapproval" />';
        echo '<input type="hidden" name="sectok" value="' . hsc(getSecurityToken()) . '" />';
        echo '<table class="inline plugin-structdocapproval-rules">';
        echo '<tr><th>#</th><th>+/-</th><th>' . hsc($this->getLang('pattern')) . '</th><th>' . hsc($this->getLang('reviewer')) . '</th><th>' . hsc($this->getLang('training')) . '</th><th>' . hsc($this->getLang('publisher')) . '</th><th>' . hsc($this->getLang('actions')) . '</th></tr>';

        foreach ($rules as $idx => $rule) {
            $id = (int)$rule['id'];
            echo '<tr>';
            echo '<td>' . ($idx + 1) . '</td>';
            echo '<td><select name="rules[' . $id . '][include]"><option value="+"' . ((int)$rule['include_rule'] ? ' selected' : '') . '>+</option><option value="-"' . (!(int)$rule['include_rule'] ? ' selected' : '') . '>-</option></select></td>';
            echo '<td><input class="edit" type="text" name="rules[' . $id . '][pattern]" value="' . hsc($rule['pattern']) . '" /></td>';
            echo '<td><input class="edit" type="text" name="rules[' . $id . '][reviewer]" value="' . hsc($rule['reviewer']) . '" /></td>';
            echo '<td><input class="edit" type="text" name="rules[' . $id . '][training]" value="' . hsc($rule['training']) . '" /></td>';
            echo '<td><input class="edit" type="text" name="rules[' . $id . '][publisher]" value="' . hsc($rule['publisher']) . '" /></td>';
            echo '<td class="rule-actions">';
            echo '<button type="submit" name="action" value="move_up:' . $id . '" title="' . hsc($this->getLang('move_up')) . '">↑</button>';
            echo '<button type="submit" name="action" value="move_down:' . $id . '" title="' . hsc($this->getLang('move_down')) . '">↓</button>';
            echo '<button type="submit" name="action" value="sync:' . $id . '">' . hsc($this->getLang('sync_rule')) . '</button>';
            echo '<button type="submit" name="action" value="remove:' . $id . '" class="danger">' . hsc($this->getLang('remove_rule')) . '</button>';
            echo '</td></tr>';
        }
        echo '</table>';
        echo '<p><button type="submit" name="action" value="save_rules">' . hsc($this->getLang('save_rules')) . '</button></p>';
        echo '<p class="docapproval-help">' . hsc($this->getLang('rules_help')) . '</p>';
        echo '</form>';

        echo '<h2>' . hsc($this->getLang('add_rule')) . '</h2>';
        echo '<form method="post" action="' . hsc(wl($ID)) . '">';
        echo '<input type="hidden" name="do" value="admin" /><input type="hidden" name="page" value="structdocapproval" /><input type="hidden" name="sectok" value="' . hsc(getSecurityToken()) . '" />';
        echo '<table class="inline plugin-structdocapproval-rules"><tr>';
        echo '<td><select name="newrule[include]"><option value="+">+</option><option value="-">-</option></select></td>';
        echo '<td><input class="edit" type="text" name="newrule[pattern]" placeholder="policy:**" /></td>';
        echo '<td><input class="edit" type="text" name="newrule[reviewer]" placeholder="user or @group" /></td>';
        echo '<td><input class="edit" type="text" name="newrule[training]" placeholder="user or @group" /></td>';
        echo '<td><input class="edit" type="text" name="newrule[publisher]" placeholder="user or @group" /></td>';
        echo '<td><button type="submit" name="action" value="add_rule">' . hsc($this->getLang('add_rule')) . '</button></td>';
        echo '</tr></table></form>';

        echo '<h2>' . hsc($this->getLang('reconcile_heading')) . '</h2>';
        echo '<p>' . hsc($this->getLang('reconcile_intro')) . '</p>';
        echo '<form method="post" action="' . hsc(wl($ID)) . '">';
        echo '<input type="hidden" name="do" value="admin" /><input type="hidden" name="page" value="structdocapproval" /><input type="hidden" name="sectok" value="' . hsc(getSecurityToken()) . '" />';
        echo '<button type="submit" name="action" value="reconcile">' . hsc($this->getLang('reconcile_run')) . '</button>';
        echo '</form>';
    }
}
