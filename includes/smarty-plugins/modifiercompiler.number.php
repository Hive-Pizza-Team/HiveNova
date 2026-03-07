<?php
/**
 * 2Moons number modifier — compiles {$var|number} to pretty_number($var)
 */
function smarty_modifiercompiler_number($params, $compiler)
{
    return 'pretty_number(' . $params[0] . ')';
}
