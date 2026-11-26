<?php
/**
 * alliance_plain modifier — decode stored htmlspecialchars() layers, then
 * escape once. Use for alliance names and tags, which HTTP::_GP() already
 * encodes on save.
 */
function smarty_modifiercompiler_alliance_plain($params, $compiler)
{
    return '\\HiveNova\\Core\\AllianceNameDisplay::plain(' . $params[0] . ')';
}
