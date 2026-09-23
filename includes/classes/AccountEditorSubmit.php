<?php

namespace HiveNova\Core;

/**
 * Which Account Editor submit button was pressed.
 *
 * HTML only sends the clicked submit name, so the other key is absent from $_POST.
 */
final class AccountEditorSubmit
{
	public static function isAdd(array $post): bool
	{
		return !empty($post['add']);
	}

	public static function isDelete(array $post): bool
	{
		return !empty($post['delete']);
	}
}
