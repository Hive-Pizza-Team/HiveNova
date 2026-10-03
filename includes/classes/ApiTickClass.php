<?php

namespace HiveNova\Core;

enum ApiTickClass: string
{
	case Poll = 'poll';
	case Read = 'read';
	case Mutate = 'mutate';

	public function runsEconomy(): bool
	{
		return $this !== self::Poll;
	}
}
