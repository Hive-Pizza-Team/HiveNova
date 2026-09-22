<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\Database;
use HiveNova\Core\Config;
use HiveNova\Core\EmailRegistrationService;
use HiveNova\Core\HTTP;
use HiveNova\Core\ReferralCaptureService;
use HiveNova\Core\Session;
use HiveNova\Core\Universe;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\PasswordPolicy;
use HiveNova\Core\RegisterValidation;
use HiveNova\Core\RegisterUsernameAvailability;
use HiveNova\Core\RegisterUsernameCheckAccess;
use HiveNova\Core\HiveUtil;
use HiveNova\Core\LoginUniverseDefaults;
use HiveNova\Core\Mail;

/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

class ShowRegisterPage extends AbstractLoginPage
{
	function __construct()
	{
		$this->defaultWindow = 'light';
		parent::__construct();
	}
	
	function show()
	{
		global $LNG;
		$universeSelect	= array();
		$universeSeasonal	= array();
		$referralData	= array('id' => 0, 'name' => '');
		$accountName	= "";
		
		$externalAuth	= HTTP::_GP('externalAuth', array());
		$referralCapture	= new ReferralCaptureService();
		$referralRequest	= ReferralCaptureService::requestBag();
		$referralPublicCode	= ReferralCaptureService::publicCodeFrom($referralRequest, $_COOKIE);
		$referralByUniverse	= $referralCapture->resolveByUniverse(
			Database::get(),
			$referralPublicCode
		);
		// Refresh cookies / validate public code without locking the universe dropdown.
		$referralCapture->resolveForRegister(
			Database::get(),
			$referralRequest,
			$_COOKIE
		);

		foreach(array_reverse(Universe::availableUniverses()) as $uniId)
		{
			$config = Config::get($uniId);
			$closed = (int) $config->game_disable === 0 || (int) $config->reg_closed === 1;
			$universeSelect[$uniId]	= LoginUniverseDefaults::selectOptionLabel(
				(string) $config->uni_name,
				LoginUniverseDefaults::isSeasonal($config),
				$closed ? (string) $LNG['uni_closed'] : '',
				(string) ($LNG['uni_option_keychain_pizza'] ?? 'Needs Hive Keychain + PIZZA entry')
			);
			$universeSeasonal[$uniId]	= LoginUniverseDefaults::isSeasonal($config) ? 1 : 0;
		}
		
		if(!isset($externalAuth['account'], $externalAuth['method']))
		{
			$externalAuth['account']	= 0;
			$externalAuth['method']		= '';
		}
		else
		{
			$externalAuth['method']		= strtolower(str_replace(array('_', '\\', '/', '.', "\0"), '', $externalAuth['method']));
		}
		
		if(!empty($externalAuth['account']) && class_exists('HiveNova\\Auth\\'.ucwords($externalAuth['method']).'Auth'))
		{
			$methodClass	= 'HiveNova\\Auth\\'.ucwords($externalAuth['method']).'Auth';
			/** @var $authObj externalAuth */
			$authObj		= new $methodClass;
			
			if(!$authObj->isActiveMode())
			{
				$this->redirectTo('index.php?code=5');
			}
			
			if(!$authObj->isValid())
			{
				$this->redirectTo('index.php?code=4');
			}
			
			$accountData	= $authObj->getAccountData();
			$accountName	= $accountData['name'];
		}

		$defaultEmailUniverse = $this->getDefaultEmailUniverseId(true);
		$defaultHiveUniverse = $this->getDefaultHiveUniverseId(true);
		$referralSeed = $referralByUniverse[$defaultEmailUniverse] ?? array('id' => 0, 'name' => '');
		if ((int) ($referralSeed['id'] ?? 0) > 0 && ($referralSeed['name'] ?? '') !== '')
		{
			$referralData = array(
				'id'   => (int) $referralSeed['id'],
				'name' => (string) $referralSeed['name'],
			);
		}
		
		$this->assign(array(
			'referralData'		=> $referralData,
			'referralByUniverse'	=> $referralByUniverse,
			'accountName'		=> $accountName,
			'externalAuth'		=> $externalAuth,
			'universeSelect'	=> $universeSelect,
			'universeSeasonal'	=> $universeSeasonal,
			'defaultUniverse'		=> $defaultEmailUniverse,
			'defaultEmailUniverse'	=> $defaultEmailUniverse,
			'defaultHiveUniverse'	=> $defaultHiveUniverse,
			'registerPasswordDesc'		=> sprintf($LNG['registerPasswordDesc'], PasswordPolicy::minLength()),
			'registerRulesDesc'			=> sprintf($LNG['registerRulesDesc'], '<a href="index.php?page=rules">'.$LNG['menu_rules'].'</a>'),
			'registerTabEmail'			=> $LNG['registerTabEmail'],
			'registerTabHive'			=> $LNG['registerTabHive'],
			'registerHiveKeychainInfo'	=> $LNG['registerHiveKeychainInfo'],
			'registerUsernameCheckConfig'	=> array(
				'url'         => 'index.php?page=register&mode=checkUsername&ajax=1',
				'debounceMs'  => 300,
				'i18n'        => array(
					'available'   => $LNG['registerUsernameCheckAvailable'],
					'suggestions' => $LNG['registerUsernameCheckSuggestions'],
				),
			),
		));
		
		$this->display('page.register.default.tpl');
	}

	/**
	 * Live username availability for the register form.
	 * index.php?page=register&mode=checkUsername&ajax=1
	 */
	function checkUsername()
	{
		global $LNG;

		$sessionId = session_id();
		if ($sessionId === '' && isset($_COOKIE[session_name()])) {
			$sessionId = (string) $_COOKIE[session_name()];
		}

		$userName = HTTP::_GP('username', '', UTF8_SUPPORT);
		$hiveSignup = HTTP::_GP('hiveSignup', 0) === 1;

		$gate = RegisterUsernameCheckAccess::runLookup(
			static function (int $universeId) use ($userName, $hiveSignup) {
				return RegisterUsernameAvailability::fromDefaults(Database::get())->check(
					$userName,
					$universeId,
					$hiveSignup
				);
			},
			Session::getClientIp(),
			HTTP::_GP('uni', 0),
			(int) Universe::current(),
			$sessionId !== '' ? $sessionId : null
		);
		if (!$gate['allow']) {
			if ($gate['httpStatus'] === RegisterUsernameCheckAccess::HTTP_TOO_MANY_REQUESTS) {
				HTTP::sendHeader('HTTP/1.1 429 Too Many Requests');
				HTTP::sendHeader('Retry-After', (string) $gate['retryAfter']);
			}
			$message = $gate['reason'] === RegisterUsernameCheckAccess::REASON_CLOSED
				? ($LNG['registerErrorUniClosed'] ?? '')
				: '';
			$this->sendJSON(RegisterUsernameCheckAccess::denyPayload(
				(string) $gate['reason'],
				$message
			));
		}

		$result = $gate['result'];

		$message = '';
		if ($result['available']) {
			$message = !empty($result['hiveOwn'])
				? $LNG['registerUsernameCheckHiveOwn']
				: $LNG['registerUsernameCheckAvailable'];
		} else {
			$message = match ($result['reason']) {
				RegisterUsernameAvailability::REASON_TAKEN_GAME => $LNG['registerUsernameCheckTakenGame'],
				RegisterUsernameAvailability::REASON_TAKEN_HIVE => $LNG['registerUsernameCheckTakenHive'],
				RegisterUsernameAvailability::REASON_MISSING_HIVE => $LNG['registerUsernameCheckMissingHive'],
				default => $this->invalidUsernameMessage($userName, $hiveSignup),
			};
		}

		$this->sendJSON(array(
			'ok'          => true,
			'available'   => $result['available'],
			'reason'      => $result['reason'],
			'suggestions' => $result['suggestions'],
			'message'     => $message,
			'hiveOwn'     => !empty($result['hiveOwn']),
		));
	}

	private function invalidUsernameMessage(string $userName, bool $hiveSignup): string
	{
		global $LNG;

		if ($hiveSignup) {
			return $LNG['registerErrorHiveAccountInvalid'] ?? $LNG['registerErrorUsernameChar'];
		}

		$key = RegisterValidation::usernameErrorKey($userName);
		if ($key !== null && isset($LNG[$key])) {
			return $LNG[$key];
		}

		return $LNG['registerErrorUsernameChar'];
	}
	
	function send() 
	{
		global $LNG;
		$config		= Config::get();

		if($config->game_disable == 0 || $config->reg_closed == 1)
		{
			$this->printMessage($LNG['registerErrorUniClosed'], array(array(
				'label'	=> $LNG['registerBack'],
				'url'	=> 'javascript:window.history.back()',
			)));
		}

		$userName 		= HTTP::_GP('username', '', UTF8_SUPPORT);
		$password 		= HTTP::_GP('password', '', true);
		$password2 		= HTTP::_GP('passwordReplay', '', true);
		$mailAddress 	= HTTP::_GP('email', '');
		$mailAddress2	= HTTP::_GP('emailReplay', '');
		$rulesChecked	= HTTP::_GP('rules', 0);
		$language 		= HTTP::_GP('lang', '');
		
		$referralResolved	= (new ReferralCaptureService())->resolveForRegister(
			Database::get(),
			ReferralCaptureService::requestBag(),
			$_COOKIE,
			(int) Universe::current()
		);
		$referralID 	= (int) $referralResolved['id'];

		$externalAuth	= HTTP::_GP('externalAuth', array());
		if(!isset($externalAuth['account'], $externalAuth['method']))
		{
			$externalAuthUID	= 0;
			$externalAuthMethod	= '';
		}
		else
		{
			$externalAuthUID	= $externalAuth['account'];
			$externalAuthMethod	= strtolower(str_replace(array('_', '\\', '/', '.', "\0"), '', $externalAuth['method']));
		}
		$hiveAccount   = HTTP::_GP('hiveAccount', '');

		$errors 	= array();

		if ($hiveAccount !== '') {
			if (!HiveUtil::isSignValid($hiveAccount, $password)) {
				$errors[]	= $LNG['registerErrorHiveSignature'] ?? $LNG['registerErrorHiveAccountInvalid'];
			}
		}
		
		$usernameFormatKey = RegisterValidation::usernameErrorKey($userName);
		if($usernameFormatKey !== null) {
			$errors[]	= $LNG[$usernameFormatKey];
		}

		if(!PasswordPolicy::isLongEnough((string) $password)) {
			$errors[]	= sprintf($LNG['registerErrorPasswordLength'], PasswordPolicy::minLength());
		}
			
		if($password != $password2) {
			$errors[]	= $LNG['registerErrorPasswordSame'];
		}

		$mailErrorKey = RegisterValidation::mailErrorKey($mailAddress);
		if($mailErrorKey !== null) {
			$errors[]	= $LNG[$mailErrorKey];
		}
		
		if($mailAddress != $mailAddress2) {
			$errors[]	= $LNG['registerErrorMailSame'];
		}
		
		if($rulesChecked != 1) {
			$errors[]	= $LNG['registerErrorRules'];
		}

		if(!empty($hiveAccount) && !HiveUtil::isAccountValid($hiveAccount)) {
			$errors[]	= $LNG['registerErrorHiveAccountInvalid'];
		}

		$db = Database::get();

		$sql = "SELECT (
				SELECT COUNT(*)
				FROM %%USERS%%
				WHERE universe = :universe
				AND username = :userName
			) + (
				SELECT COUNT(*)
				FROM %%USERS_VALID%%
				WHERE universe = :universe
				AND username = :userName
			) as count;";

		$countUsername = $db->selectSingle($sql, array(
			':universe'	=> Universe::current(),
			':userName'	=> $userName,
		), 'count');

		$sql = "SELECT (
			SELECT COUNT(*)
			FROM %%USERS%%
			WHERE universe = :universe
			AND (
				email = :mailAddress
				OR email_2 = :mailAddress
			)
		) + (
			SELECT COUNT(*)
			FROM %%USERS_VALID%%
			WHERE universe = :universe
			AND email = :mailAddress
		) as count;";

		$countMail = $db->selectSingle($sql, array(
			':universe'		=> Universe::current(),
			':mailAddress'	=> $mailAddress,
		), 'count');

		$sql = "SELECT (
			SELECT COUNT(*)
			FROM %%USERS%%
			WHERE universe = :universe
			AND hive_account = :hiveAccount
		) + (
			SELECT COUNT(*)
			FROM %%USERS_VALID%%
			WHERE universe = :universe
			AND hive_account = :hiveAccount
		) as count;";

		$countHiveAccount = $db->selectSingle($sql, array(
			':universe'		=> Universe::current(),
			':hiveAccount'	=> $hiveAccount,
		), 'count');
		
		if($countUsername != 0) {
			$errors[]	= $LNG['registerErrorUsernameExist'];
		}

		if(HiveUtil::accountExists($userName) && empty($hiveAccount)) {
			// disallow registering a non-hive account with same name as an existing hive account
			// to avoid collisions
			$errors[]	= $LNG['registerErrorUsernameExist'];
		}
			
		if($countMail != 0) {
			$errors[]	= $LNG['registerErrorMailExist'];
		}

		if(!empty($hiveAccount) && $countHiveAccount != 0) {
			$errors[]	= $LNG['registerErrorHiveAccountExist'];
		}
		
		if ($config->capaktiv === '1')
		{
            $recaptcha = new \ReCaptcha\ReCaptcha($config->capprivate);
            $resp = $recaptcha->verify(HTTP::_GP('g-recaptcha-response', ''), Session::getClientIp());
            if (!$resp->isSuccess())
            {
                $errors[]	= $LNG['registerErrorCaptcha'];
            }
		}
						
		if (!empty($errors)) {
			$this->printMessage(implode("<br>\r\n", $errors), array(array(
				'label'	=> $LNG['registerBack'],
				'url'	=> 'javascript:window.history.back()',
			)));
		}

		$methodClass		= 'HiveNova\\Auth\\'.ucwords($externalAuthMethod).'Auth';

		if(!empty($externalAuth['account']) && class_exists($methodClass))
		{
			/** @var $authObj externalAuth */
			$authObj			= new $methodClass;
			$externalAuthUID	= 0;
			if($authObj->isActiveMode() && $authObj->isValid()) {
				$externalAuthUID	= $authObj->getAccount();
			}
		}

		// resolveForRegister already requires ref_active on Universe::current().
		if ((int) $config->ref_active !== 1)
		{
			$referralID	= 0;
		}
		
		$validationKey	= md5(uniqid('2m'));

		$sql = "INSERT INTO %%USERS_VALID%% SET
				`userName` = :userName,
				`validationKey` = :validationKey,
				`password` = :password,
				`email` = :mailAddress,
				`date` = :timestamp,
				`ip` = :remoteAddr,
				`language` = :language,
				`universe` = :universe,
				`referralID` = :referralID,
				`externalAuthUID` = :externalAuthUID,
				`externalAuthMethod` = :externalAuthMethod,
				`hive_account` = :hiveAccount;";


		$db->insert($sql, array(
			':userName'				=> $userName,
			':validationKey'		=> $validationKey,
			':password'				=> PlayerUtil::cryptPassword($password),
			':mailAddress'			=> $mailAddress,
			':timestamp'			=> TIMESTAMP,
			':remoteAddr'			=> '127.0.0.1',
			':language'				=> $language,
			':universe'				=> Universe::current(),
			':referralID'			=> $referralID,
			':externalAuthUID'		=> $externalAuthUID,
			':externalAuthMethod'	=> $externalAuthMethod,
			':hiveAccount'          => $hiveAccount
		));

		$validationID	= $db->lastInsertId();
		$universeId	= (int) Universe::current();
		// Relative redirect stays on the current /uniN/ rewrite context.
		$verifyPath	= 'index.php?page=vertify&i='.$validationID.'&k='.$validationKey.'&uni='.$universeId;
		$verifyURL	= EmailRegistrationService::buildVerifyUrl($universeId, (int) $validationID, $validationKey);
		
		if($config->user_valid == 0 || !empty($externalAuthUID))
		{
			$this->redirectTo($verifyPath);
		}
		else
		{
			
			$MailRAW		= $LNG->getTemplate('email_vaild_reg');
			$MailContent	= str_replace(array(
				'{USERNAME}',
				'{PASSWORD}',
				'{GAMENAME}',
				'{VERTIFYURL}',
				'{GAMEMAIL}',
			), array(
				$userName,
				$password,
				$config->game_name.' - '.$config->uni_name,
				$verifyURL,
				$config->smtp_sendmail,
			), $MailRAW);

			$subject	= sprintf($LNG['registerMailVertifyTitle'], $config->game_name);
			Mail::send($mailAddress, $userName, $subject, $MailContent);
			
			$this->printMessage($LNG['registerSendComplete']);
		}
	}
}