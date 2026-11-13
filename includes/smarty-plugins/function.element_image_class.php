<?php

use HiveNova\Core\GameElementImage;

/**
 * @param array<string, mixed> $params
 * @param \Smarty_Internal_Template $template
 */
function smarty_function_element_image_class(array $params, $template): string
{
	$id = (int) ($params['id'] ?? 0);
	$dpath = $params['dpath'] ?? $template->getTemplateVars('dpath');
	if (!is_string($dpath)) {
		$dpath = '';
	}

	return (new GameElementImage())->cssClass($dpath, $id);
}
