<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\ElementRequirementService;
use HiveNova\Core\TechTreeGuideService;
use HiveNova\Core\TechTreeNudgeService;
use HiveNova\Core\TechTreeOrderService;

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


class ShowTechtreePage extends AbstractGamePage
{
    public static $requireModule = MODULE_TECHTREE;

    function __construct()
    {
        parent::__construct();
    }

    function show()
    {
        global $resource, $requirements, $reslist, $USER, $PLANET, $LNG, $THEME;

        $elementIDs		= array_merge(
            $reslist['build'],
            $reslist['tech'],
            $reslist['fleet'],
            $reslist['defense'],
            $reslist['missile'],
            $reslist['officier']
        );

        $items = array();
        $names = array();
        $ext = array();
        $Messages = $USER['messages'];
        $requirementService = new ElementRequirementService();
        $techNames = is_array($LNG['tech'] ?? null) ? $LNG['tech'] : array();
        $requirementMap = is_array($requirements) ? $requirements : array();

        foreach ($elementIDs as $elementId) {
            if (!isset($resource[$elementId])) {
                continue;
            }

            $requirementsList = $requirementService->listForElement(
                (int) $elementId,
                $USER,
                $PLANET,
                $requirementMap,
                $resource,
                $techNames
            );

            // Keep empty-req techs out of the payload — expand only shows requirement rows historically when requireList truthy.
            if ($requirementsList === array()) {
                continue;
            }

            foreach ($requirementsList as $row) {
                $names[(string) $row['id']] = $row['name'];
            }

            $items[(string) $elementId] = $requirementsList;
            $names[(string) $elementId] = $techNames[$elementId] ?? (string) $elementId;
            $ext[(string) $elementId] = ($elementId >= 600 && $elementId <= 699) ? 'jpg' : 'gif';
        }

        $orderService = new TechTreeOrderService($requirementMap);
        $order = $orderService->orderByCategory(array_map('intval', array_keys($items)));
        $guide = new TechTreeGuideService($requirementMap);
        $nextUnlocks = $guide->nextUnlocks(
            is_array($USER) ? $USER : array(),
            is_array($PLANET) ? $PLANET : array(),
            is_array($resource) ? $resource : array(),
            is_array($LNG['tech'] ?? null) ? $LNG['tech'] : array(),
            array(
                'need'  => $LNG['tt_need_level'] ?? 'Need %s %d (%d/%d)',
                'ready' => $LNG['tt_ready'] ?? 'Requirements met — go start it.',
                'sep'   => $LNG['tt_need_join'] ?? '; ',
            ),
            3
        );

        if (!TechTreeNudgeService::isSeen($_COOKIE)) {
            setcookie(
                TechTreeNudgeService::COOKIE,
                TechTreeNudgeService::COOKIE_VALUE,
                TechTreeNudgeService::cookieOptions(TIMESTAMP, TechTreeNudgeService::isSecureRequest())
            );
        }

        $dpath = $THEME->getTheme();
        $techTreeJson = json_encode(array(
            'dpath' => $dpath,
            'ttRequirements' => $LNG['tt_requirements'],
            'ttLvl' => $LNG['tt_lvl'],
            'names' => $names,
            'ext' => $ext,
            'items' => $items,
            'order' => $order,
            'starterIds' => $guide->startHereIds(),
            'defaultFilter' => 'start',
            'seenCookie' => TechTreeNudgeService::COOKIE,
        ), JSON_UNESCAPED_UNICODE);

        $this->assign(array(
            'TechCategories' => array(0, 100, 200, 400, 500, 600),
            'techTreeJson'   => $techTreeJson,
            'nextUnlocks'    => $nextUnlocks,
            'messages'       => ($Messages > 0) ? (($Messages == 1) ? $LNG['ov_have_new_message'] : sprintf($LNG['ov_have_new_messages'], $Messages)) : false,
        ));

        $this->display('page.techTree.default.tpl');
    }
}
