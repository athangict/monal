<?php
namespace Accounts\Support;

final class AccountLabel
{
	public static function html($name, $code = null): string
	{
		$label = $code === null ? (string)$name : (string)$code . '-' . (string)$name;
		return htmlspecialchars(html_entity_decode($label, ENT_QUOTES, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
