<?php
/**
 * number_plain modifier — always outputs plain EU-formatted text, never HTML.
 * Use this in HTML attribute contexts (data-tooltip-content, title, etc.)
 * where pretty_number()'s <span> output would break the attribute.
 */
function smarty_modifiercompiler_number_plain($params, $compiler)
{
    return 'pretty_number_plain(' . $params[0] . ')';
}
