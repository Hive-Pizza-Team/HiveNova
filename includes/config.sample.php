<?php
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

//### Database access ###//

$database					= array();
$database['host']			= '%s';
$database['port']			= '%s';
$database['user']			= '%s';
$database['userpw']			= '%s';
$database['databasename']	= '%s';
$database['tableprefix']	= '%s';
$salt						= '%s'; // 22 digits from the alphabet "./0-9A-Za-z"

// Optional: override encrypt-at-rest key for Hive WIFs (else $salt is used).
// Prefer env APP_KEY in production. Also: HIVE_INACTIVE_MEMO_ACTIVE_KEY,
// HIVE_SOCIAL_MEMO_MEMO_KEY, SEASON_WALLET_ACTIVE_KEY, SEASON_BLOG_POSTING_KEY.
$appKey						= '';

//### Do not change beyond here ###//
?>