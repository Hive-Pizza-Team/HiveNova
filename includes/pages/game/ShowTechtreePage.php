<?php

namespace HiveNova\Page\Game;

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

        foreach ($elementIDs as $elementId) {
            if (!isset($resource[$elementId])) {
                continue;
            }

            $requirementsList = array();
            if (isset($requirements[$elementId])) {
                foreach ($requirements[$elementId] as $requireID => $RedCount) {
                    $requirementsList[(string) $requireID] = array(
                        'count' => $RedCount,
                        'own'   => isset($PLANET[$resource[$requireID]]) ? $PLANET[$resource[$requireID]] : $USER[$resource[$requireID]],
                    );
                    $names[(string) $requireID] = $LNG['tech'][$requireID] ?? (string) $requireID;
                }
            }

            // Keep empty-req techs out of the payload — expand only shows requirement rows historically when requireList truthy.
            if ($requirementsList === array()) {
                continue;
            }

            $items[(string) $elementId] = $requirementsList;
            $names[(string) $elementId] = $LNG['tech'][$elementId] ?? (string) $elementId;
            $ext[(string) $elementId] = ($elementId >= 600 && $elementId <= 699) ? 'jpg' : 'gif';
        }

        $dpath = $THEME->getTheme();
        $techTreeJson = json_encode(array(
            'dpath' => $dpath,
            'ttRequirements' => $LNG['tt_requirements'],
            'ttLvl' => $LNG['tt_lvl'],
            'names' => $names,
            'ext' => $ext,
            'items' => $items,
        ), JSON_UNESCAPED_UNICODE);

        $this->assign(array(
            'TechCategories' => array(0, 100, 200, 400, 500, 600),
            'techTreeJson'   => $techTreeJson,
            'messages'       => ($Messages > 0) ? (($Messages == 1) ? $LNG['ov_have_new_message'] : sprintf($LNG['ov_have_new_messages'], $Messages)) : false,
        ));

        $this->display('page.techTree.default.tpl');
    }
}
