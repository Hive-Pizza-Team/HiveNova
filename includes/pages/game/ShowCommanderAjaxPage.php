<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\DirectiveService;
use HiveNova\Core\ExpeditionChoiceService;
use HiveNova\Core\HTTP;
use HiveNova\Core\Universe;
use RuntimeException;
use Smarty;

class ShowCommanderAjaxPage extends AbstractGamePage
{
	public static $requireModule = 0;

	protected $disableEcoSystem = true;

	public function __construct()
	{
		parent::__construct();
	}

	public function show()
	{
		$this->status();
	}

	public function status()
	{
		global $USER, $PLANET;

		if (!isModuleAvailable(MODULE_COMMANDER)) {
			$this->respondJSON([
				'ok' => true,
				'enabled' => false,
			]);
			return;
		}

		$briefing = DirectiveService::getBriefingData(
			(int) $USER['id'],
			(int) Universe::current(),
			$USER,
			$PLANET
		);
		$this->respondJSON([
			'ok' => true,
			'enabled' => true,
			'briefing' => $briefing,
			'html' => $this->renderBriefingHtml($briefing),
		]);
	}

	public function selectDirective()
	{
		global $USER, $PLANET, $LNG;

		if (!$this->guardMutation()) {
			return;
		}

		$key = HTTP::_GP('directive_key', '');
		try {
			$row = DirectiveService::selectDirective(
				(int) $USER['id'],
				(int) Universe::current(),
				$key,
				$USER,
				$PLANET
			);
			$this->respondJSON(array_merge([
				'ok' => true,
				'directive_key' => $row['directive_key'],
			], $this->briefingRefresh()));
		} catch (RuntimeException $e) {
			$this->sendDirectiveError($e->getMessage(), $LNG);
		}
	}

	public function claimReward()
	{
		global $USER, $PLANET, $LNG;

		if (!$this->guardMutation()) {
			return;
		}

		try {
			$reward = DirectiveService::claimReward(
				(int) $USER['id'],
				(int) Universe::current(),
				(int) $PLANET['id']
			);
			$this->respondJSON(array_merge([
				'ok' => true,
				'reward' => $reward,
			], $this->briefingRefresh()));
		} catch (RuntimeException $e) {
			$this->sendDirectiveError($e->getMessage(), $LNG);
		}
	}

	public function resolveBranch()
	{
		global $USER, $LNG;

		if (!$this->guardMutation()) {
			return;
		}

		$fleetId = HTTP::_GP('fleet_id', 0);
		$branchKey = HTTP::_GP('branch_key', '');
		try {
			$choice = ExpeditionChoiceService::resolveBranch((int) $fleetId, (int) $USER['id'], $branchKey);
			$this->respondJSON(array_merge([
				'ok' => true,
				'choice' => $choice,
			], $this->briefingRefresh()));
		} catch (RuntimeException $e) {
			$code = $e->getMessage();
			$status = match ($code) {
				ExpeditionChoiceService::ERROR_FORBIDDEN => '403 Forbidden',
				ExpeditionChoiceService::ERROR_NOT_FOUND => '404 Not Found',
				default => '400 Bad Request',
			};
			HTTP::sendHeader('HTTP/1.1 ' . $status);
			$this->respondJSON([
				'ok' => false,
				'error' => $code,
				'message' => $this->errorMessage($code, $LNG),
			]);
		}
	}

	/**
	 * @return array{briefing: array<string, mixed>, html: string}
	 */
	protected function briefingRefresh(): array
	{
		global $USER, $PLANET;

		$briefing = DirectiveService::getBriefingData(
			(int) $USER['id'],
			(int) Universe::current(),
			$USER,
			$PLANET
		);

		return [
			'briefing' => $briefing,
			'html' => $this->renderBriefingHtml($briefing),
		];
	}

	/**
	 * @param array<string, mixed> $briefing
	 */
	protected function renderBriefingHtml(array $briefing): string
	{
		global $LNG;

		$this->initTemplate();
		$this->tplObj->setCaching(Smarty::CACHING_OFF);
		$this->assign([
			'commanderBriefing' => $briefing,
			'LNG' => $LNG,
		]);
		if (is_object($LNG) && method_exists($LNG, 'getLanguage')) {
			$this->tplObj->compile_id = $LNG->getLanguage();
		}

		return (string) $this->tplObj->fetch('partials/commander.briefing.tpl');
	}

	/**
	 * @param array<string, mixed> $data
	 */
	protected function respondJSON(array $data): void
	{
		HTTP::sendHeader('Content-Type', 'application/json; charset=UTF-8');
		$this->sendJSON($data);
	}

	private function guardMutation(): bool
	{
		global $LNG;

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			HTTP::sendHeader('HTTP/1.1 405 Method Not Allowed');
			$this->respondJSON(['ok' => false, 'error' => 'method_not_allowed']);
			return false;
		}
		if (!DirectiveService::isSameOriginRequest()) {
			HTTP::sendHeader('HTTP/1.1 403 Forbidden');
			$this->respondJSON([
				'ok' => false,
				'error' => 'cross_origin',
				'message' => $LNG['cm_cross_origin'] ?? 'Cross-origin request rejected',
			]);
			return false;
		}
		$token = HTTP::_GP('token', '');
		if (!DirectiveService::validateCsrfToken($token)) {
			HTTP::sendHeader('HTTP/1.1 403 Forbidden');
			$this->respondJSON([
				'ok' => false,
				'error' => 'invalid_token',
				'message' => $LNG['cm_invalid_token'] ?? 'Invalid token',
			]);
			return false;
		}
		if (!isModuleAvailable(MODULE_COMMANDER)) {
			HTTP::sendHeader('HTTP/1.1 403 Forbidden');
			$this->respondJSON([
				'ok' => false,
				'error' => DirectiveService::ERROR_DISABLED,
				'message' => $LNG['cm_module_disabled'] ?? 'Commander module disabled',
			]);
			return false;
		}

		return true;
	}

	private function sendDirectiveError(string $code, mixed $LNG): void
	{
		HTTP::sendHeader('HTTP/1.1 400 Bad Request');
		$this->respondJSON([
			'ok' => false,
			'error' => $code,
			'message' => $this->errorMessage($code, $LNG),
		]);
	}

	private function errorMessage(string $code, mixed $LNG): string
	{
		return match ($code) {
			DirectiveService::ERROR_LOCKED => $LNG['cm_already_selected'] ?? 'Directive already selected',
			DirectiveService::ERROR_UNKNOWN => $LNG['cm_unknown_directive'] ?? 'Unknown directive',
			DirectiveService::ERROR_REQUIREMENTS => $LNG['cm_directive_requirements'] ?? 'Required ships or research not unlocked',
			DirectiveService::ERROR_DISABLED => $LNG['cm_module_disabled'] ?? 'Commander module disabled',
			DirectiveService::ERROR_CLAIMED => $LNG['cm_reward_claimed'] ?? 'Reward already claimed',
			DirectiveService::ERROR_NOT_COMPLETE => $LNG['cm_not_complete'] ?? 'Directive is not complete',
			ExpeditionChoiceService::ERROR_FORBIDDEN => $LNG['cm_branch_forbidden'] ?? 'Not your expedition',
			ExpeditionChoiceService::ERROR_INVALID_BRANCH => $LNG['cm_branch_invalid'] ?? 'Invalid branch',
			ExpeditionChoiceService::ERROR_ALREADY_RESOLVED => $LNG['cm_branch_gone'] ?? 'Already resolved',
			ExpeditionChoiceService::ERROR_NOT_FOUND => $LNG['cm_branch_gone'] ?? 'No pending choice',
			default => $code,
		};
	}
}
