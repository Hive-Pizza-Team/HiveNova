<?php
/**
 * htmlspecialchars modifier — compiles {$var|htmlspecialchars} to htmlspecialchars($var)
 * Explicit registration avoids the Smarty 4 PHP-function deprecation warning.
 */
function smarty_modifiercompiler_htmlspecialchars($params, $compiler)
{
    return 'htmlspecialchars(' . $params[0] . ')';
}
