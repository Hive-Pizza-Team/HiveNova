<?php

use HiveNova\Core\HTTP;
use HiveNova\Core\HiveAccountCreateRequestService;
use HiveNova\Core\Template;
use HiveNova\Core\Universe;

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) {
	throw new Exception('Permission error!');
}

function ShowHiveCreateRequestsPage()
{
	global $LNG, $USER;

	$universe = (int) Universe::getEmulated();
	$service = new HiveAccountCreateRequestService();
	$action = HTTP::_GP('action', '');
	$requestId = HTTP::_GP('id', 0);
	$message = '';

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && $requestId > 0) {
		$adminId = (int) ($USER['id'] ?? 0);
		if ($action === 'approve') {
			$result = $service->approve($requestId, $adminId);
			$message = $result['ok']
				? ($LNG['hive_create_admin_approved'] ?? 'Request approved (claim-ticket broadcast is a follow-up).')
				: ($LNG['hive_create_admin_failed'] ?? 'Could not approve that request.');
		} elseif ($action === 'reject') {
			$reason = HTTP::_GP('reason', '', true);
			$result = $service->reject($requestId, $adminId, $reason);
			if ($result['ok']) {
				$message = $LNG['hive_create_admin_rejected'] ?? 'Request rejected.';
			} elseif ($result['code'] === HiveAccountCreateRequestService::CODE_EMPTY_REASON) {
				$message = $LNG['hive_create_admin_need_reason'] ?? 'A reject reason is required.';
			} else {
				$message = $LNG['hive_create_admin_failed'] ?? 'Could not reject that request.';
			}
		}
	}

	$pending = $service->listPending($universe);
	foreach ($pending as &$row) {
		$row['created_at_fmt'] = _date(
			$LNG['php_tdformat'],
			(int) ($row['created_at'] ?? 0),
			$USER['timezone'] ?? null
		);
	}
	unset($row);

	$template = new Template();
	$template->assign_vars([
		'pendingRequests' => $pending,
		'message' => $message,
		'universe' => $universe,
	]);
	$template->show('page.hive.create.requests.tpl');
}
