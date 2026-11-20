<?php

use HiveNova\Core\GameElementImage;

/**
 * Smarty function: {element_image id=$ID} or {element_image id=$ID dpath=$dpath}
 *
 * @param array<string, mixed> $params
 * @param \Smarty_Internal_Template $template
 */
function smarty_function_element_image(array $params, $template): string
{
	$id = (int) ($params['id'] ?? 0);
	$dpath = $params['dpath'] ?? $template->getTemplateVars('dpath');
	if (!is_string($dpath)) {
		$dpath = '';
	}

	return (new GameElementImage())->url($dpath, $id);
}
