<?php
/**
 * 2Moons time modifier — compiles {$var|time} to pretty_time($var)
 */
function smarty_modifiercompiler_time($params, $compiler)
{
    return 'pretty_time(' . $params[0] . ')';
}
