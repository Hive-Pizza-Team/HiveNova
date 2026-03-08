<?php

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowBuildLogPage()
{
	global $LNG;

	$perPage = 50;
	$page    = max(1, HTTP::_GP('p', 1));
	$offset  = ($page - 1) * $perPage;
	$search  = HTTP::_GP('q', '', true);
	$type    = HTTP::_GP('type', 'all');  // 'buildings', 'research', 'shipyard', 'all'

	$where = '';
	if ($search !== '') {
		$escaped = $GLOBALS['DATABASE']->sql_escape($search);
		$where = "AND u.username LIKE '%{$escaped}%'";
	}

	$buildQ    = "SELECT 'building' AS log_type, l.id, l.owner_id, l.planet_id, l.universe, l.element_id, 1 AS count, l.metal, l.crystal, l.deuterium, l.queued_at, u.username
	              FROM " . LOG_BUILDINGS . " AS l
	              LEFT JOIN " . USERS . " AS u ON u.id = l.owner_id
	              WHERE 1=1 {$where}";

	$researchQ = "SELECT 'research' AS log_type, l.id, l.owner_id, l.planet_id, l.universe, l.element_id, 1 AS count, l.metal, l.crystal, l.deuterium, l.queued_at, u.username
	              FROM " . LOG_RESEARCH . " AS l
	              LEFT JOIN " . USERS . " AS u ON u.id = l.owner_id
	              WHERE 1=1 {$where}";

	$shipyardQ = "SELECT 'shipyard' AS log_type, l.id, l.owner_id, l.planet_id, l.universe, l.element_id, l.count, l.metal, l.crystal, l.deuterium, l.queued_at, u.username
	              FROM " . LOG_SHIPYARD . " AS l
	              LEFT JOIN " . USERS . " AS u ON u.id = l.owner_id
	              WHERE 1=1 {$where}";

	if ($type === 'buildings') {
		$unionQ = $buildQ;
		$countQ = "SELECT COUNT(*) AS cnt FROM " . LOG_BUILDINGS . " AS l LEFT JOIN " . USERS . " AS u ON u.id = l.owner_id WHERE 1=1 {$where}";
	} elseif ($type === 'research') {
		$unionQ = $researchQ;
		$countQ = "SELECT COUNT(*) AS cnt FROM " . LOG_RESEARCH . " AS l LEFT JOIN " . USERS . " AS u ON u.id = l.owner_id WHERE 1=1 {$where}";
	} elseif ($type === 'shipyard') {
		$unionQ = $shipyardQ;
		$countQ = "SELECT COUNT(*) AS cnt FROM " . LOG_SHIPYARD . " AS l LEFT JOIN " . USERS . " AS u ON u.id = l.owner_id WHERE 1=1 {$where}";
	} else {
		$unionQ = "({$buildQ}) UNION ALL ({$researchQ}) UNION ALL ({$shipyardQ})";
		$countQ = "SELECT (
			SELECT COUNT(*) FROM " . LOG_BUILDINGS . " AS l LEFT JOIN " . USERS . " AS u ON u.id = l.owner_id WHERE 1=1 {$where}
		) + (
			SELECT COUNT(*) FROM " . LOG_RESEARCH . " AS l LEFT JOIN " . USERS . " AS u ON u.id = l.owner_id WHERE 1=1 {$where}
		) + (
			SELECT COUNT(*) FROM " . LOG_SHIPYARD . " AS l LEFT JOIN " . USERS . " AS u ON u.id = l.owner_id WHERE 1=1 {$where}
		) AS cnt";
	}

	$totalRow = $GLOBALS['DATABASE']->getFirstRow("{$countQ};");
	$total    = (int) ($totalRow['cnt'] ?? 0);

	$result = $GLOBALS['DATABASE']->query(
		"SELECT * FROM ({$unionQ}) AS combined ORDER BY queued_at DESC LIMIT {$offset}, {$perPage};"
	);

	$techNames  = $LNG['tech'] ?? [];
	$shortNames = $LNG['shortNames'] ?? [];

	$rows = [];
	while ($row = $GLOBALS['DATABASE']->fetch_array($result)) {
		$elementId   = (int) $row['element_id'];
		$elementName = $techNames[$elementId] ?? $shortNames[$elementId] ?? "#{$elementId}";
		$rows[] = [
			'log_type'    => $row['log_type'],
			'owner_id'    => $row['owner_id'],
			'username'    => $row['username'] ?? '—',
			'planet_id'   => $row['planet_id'],
			'universe'    => $row['universe'],
			'element_id'  => $elementId,
			'element_name'=> $elementName,
			'count'       => (int) $row['count'],
			'metal'       => number_format((int) $row['metal']),
			'crystal'     => number_format((int) $row['crystal']),
			'deuterium'   => number_format((int) $row['deuterium']),
			'queued_at'   => _date($LNG['php_tdformat'], $row['queued_at'], 0),
		];
	}
	$GLOBALS['DATABASE']->free_result($result);

	$pages = [];
	if ($total > $perPage) {
		$numPages = (int) ceil($total / $perPage);
		$baseUrl  = '?page=buildlog&type=' . urlencode($type) . ($search !== '' ? '&q=' . urlencode($search) : '');
		for ($i = 1; $i <= $numPages; $i++) {
			$pages[] = [
				'num'     => $i,
				'current' => ($i === $page),
				'url'     => $baseUrl . '&p=' . $i,
			];
		}
	}

	$template = new template();
	$template->assign_vars([
		'rows'   => $rows,
		'total'  => $total,
		'pages'  => $pages,
		'search' => $search,
		'type'   => $type,
		'page'   => $page,
	]);
	$template->show('BuildLogPage.tpl');
}
