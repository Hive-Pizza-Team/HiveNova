<?php

namespace HiveNova\Core;

class AllianceNameDisplay
{
	public static function plain(?string $name): string
	{
		if ($name === null || $name === '') {
			return '';
		}

		return htmlspecialchars(strip_tags($name), ENT_QUOTES, 'UTF-8');
	}
}
