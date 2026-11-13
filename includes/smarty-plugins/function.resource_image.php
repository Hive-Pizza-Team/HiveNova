<?php

use HiveNova\Core\GameResourceIcon;

/**
 * @param array<string, mixed> $params
 * @param \Smarty_Internal_Template $template
 */
function smarty_function_resource_image(array $params, $template): string
{
	$name = (string) ($params['name'] ?? '');
	$dpath = $params['dpath'] ?? $template->getTemplateVars('dpath');
	if (!is_string($dpath)) {
		$dpath = '';
	}

	return (new GameResourceIcon())->url($dpath, $name);
}
