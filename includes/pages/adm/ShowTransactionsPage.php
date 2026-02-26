<?php

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowTransactionsPage()
{
	global $LNG, $USER;

	$perPage  = 50;
	$page     = max(1, HTTP::_GP('p', 1));
	$offset   = ($page - 1) * $perPage;
	$search   = HTTP::_GP('q', '', true);

	$where = '';
	$params = [];
	if ($search !== '') {
		$escaped = $GLOBALS['DATABASE']->sql_escape($search);
		$where = "WHERE u.username LIKE '%{$escaped}%' OR t.memo LIKE '%{$escaped}%'";
	}

	$totalRow = $GLOBALS['DATABASE']->getFirstRow(
		"SELECT COUNT(*) AS cnt
		 FROM " . DM_TRANSACTIONS . " AS t
		 LEFT JOIN " . USERS . " AS u ON u.id = t.user_id
		 {$where};"
	);
	$total = (int) ($totalRow['cnt'] ?? 0);

	$result = $GLOBALS['DATABASE']->query(
		"SELECT t.id, t.timestamp, t.user_id, u.username,
		        t.amount_spent, t.amount_received, t.item_purchased_id, t.memo
		 FROM " . DM_TRANSACTIONS . " AS t
		 LEFT JOIN " . USERS . " AS u ON u.id = t.user_id
		 {$where}
		 ORDER BY t.id DESC
		 LIMIT {$offset}, {$perPage};"
	);

	$rows = [];
	while ($row = $GLOBALS['DATABASE']->fetch_array($result)) {
		$rows[] = [
			'id'               => $row['id'],
			'timestamp'        => $row['timestamp'],
			'user_id'          => $row['user_id'],
			'username'         => $row['username'] ?? '—',
			'amount_spent'     => $row['amount_spent'],
			'amount_received'  => $row['amount_received'],
			'item_purchased_id'=> $row['item_purchased_id'],
			'memo'             => $row['memo'],
		];
	}
	$GLOBALS['DATABASE']->free_result($result);

	$pages = [];
	if ($total > $perPage) {
		$numPages = (int) ceil($total / $perPage);
		for ($i = 1; $i <= $numPages; $i++) {
			$pages[] = [
				'num'     => $i,
				'current' => ($i === $page),
				'url'     => '?page=transactions&p=' . $i . ($search !== '' ? '&q=' . urlencode($search) : ''),
			];
		}
	}

	$template = new template();
	$template->assign_vars([
		'rows'    => $rows,
		'total'   => $total,
		'pages'   => $pages,
		'search'  => $search,
		'page'    => $page,
	]);
	$template->show('TransactionsPage.tpl');
}
