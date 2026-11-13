<?php

use HiveNova\Core\GamePlanetImage;

/**
 * {planet_image texture=$image}        — HD/classic HQ when present
 * {planet_image texture=$image lite=1} — HD/classic standard (no _hq)
 *
 * @param array<string, mixed> $params
 * @param \Smarty_Internal_Template $template
 */
function smarty_function_planet_image(array $params, $template): string
{
	$texture = (string) ($params['texture'] ?? '');
	$liteRaw = $params['lite'] ?? false;
	$lite = $liteRaw === true || $liteRaw === 1 || $liteRaw === '1' || $liteRaw === 'true';
	$dpath = $params['dpath'] ?? $template->getTemplateVars('dpath');
	if (!is_string($dpath)) {
		$dpath = '';
	}

	return (new GamePlanetImage())->url($dpath, $texture, !$lite);
}
