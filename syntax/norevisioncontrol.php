<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\Parsing\Handler;

/**
 * Page-local revision-control exclusion directive.
 *
 * The directive is intentionally invisible in rendered output. Its workflow
 * effect is enforced by Assignments and the save/reconcile handlers.
 */
class syntax_plugin_structdocapproval_norevisioncontrol extends SyntaxPlugin
{
    public function getType()
    {
        return 'substition';
    }

    public function getPType()
    {
        return 'normal';
    }

    public function getSort()
    {
        return 35;
    }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern(
            '~~NOREVISIONCONTROL~~',
            $mode,
            'plugin_structdocapproval_norevisioncontrol'
        );
    }

    public function handle($match, $state, $pos, Handler $handler)
    {
        return [];
    }

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        // Consume the directive in every renderer without producing output.
        return true;
    }
}
