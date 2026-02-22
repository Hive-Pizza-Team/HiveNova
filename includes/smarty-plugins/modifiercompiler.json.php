<?php
/**
 * 2Moons json modifier — compiles {$var|json} to json_encode($var)
 */
function smarty_modifiercompiler_json($params, $compiler)
{
    return 'json_encode(' . $params[0] . ')';
}
