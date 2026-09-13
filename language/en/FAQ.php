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
// Translated into English by QwataKayean . All rights reversed (C) 2012
// 2Moons - Copyright (C) 2010-2012 Slaver
$LNG['faq_overview']	= "FAQ";
$LNG['faq_intro']		= 'Start with energy and mines, then unlock the Shipyard from Technologies (Gigafactory level 2). Open a topic below, or go to Technologies any time you need building or ship requirements.';

$LNG['questions']					= array();
$LNG['questions'][1]['category']	= 'Getting started';
$LNG['questions'][1][1]['title']	= 'Your first buildings';
$LNG['questions'][1][1]['body']		= <<<BODY
<p>New colonies start with a few core buildings. Build them from <a href="game.php?page=buildings">Buildings</a>.</p>
<h3>Solar Power Plant</h3>
<p>Mines need energy. The Solar Power Plant is available immediately and is the first building you should raise. If energy used by production is higher than energy generated, the top bar shows an energy deficit and mines slow down. Keep energy in surplus before you expand extractors.</p>
<h3>Ore Extractor</h3>
<p>Produces Metal, the resource used for almost every building, ship, and defense. Expand extractors early, but only after you have the energy to run them.</p>
<h3>Silicon Refinery</h3>
<p>Produces Silicon. Silicon is used heavily for research, electronics, and many ships, and it usually comes in slower than Metal.</p>
<h3>Uranium Centrifuge</h3>
<p>Produces Uranium. Uranium fuels ships, some research, and later energy options such as the Uranium Power Plant.</p>
<p>Exact costs and prerequisites for every building are on <a href="game.php?page=techtree">Technologies</a>. Do not guess from memory — open the tech tree when a building is locked.</p>
BODY;
$LNG['questions'][1][2]['title']	= 'How to unlock the Shipyard';
$LNG['questions'][1][2]['body']		= <<<BODY
<p>You cannot build ships or defenses until a Shipyard exists on that planet. The Shipyard page itself will tell you to check the tech tree if the building is missing.</p>
<h3>Requirement</h3>
<p>The Shipyard requires <strong>Gigafactory level 2</strong>. Gigafactory has no earlier building prerequisite, so you can start it as soon as you can afford it.</p>
<ol>
<li>Open <a href="game.php?page=buildings">Buildings</a> and raise <strong>Gigafactory</strong> to level 2.</li>
<li>When that finishes, build the <strong>Shipyard</strong>.</li>
<li>Then open <a href="game.php?page=shipyard&amp;mode=fleet">Shipyard</a> to queue ships and defenses.</li>
</ol>
<p>Individual ships and defenses have extra research and Shipyard-level requirements. Those are listed on <a href="game.php?page=techtree">Technologies</a>, not on this page. Gigafactory also speeds up other building construction as you upgrade it.</p>
BODY;
$LNG['questions'][1][3]['title']	= 'Research and the Technologies page';
$LNG['questions'][1][3]['body']		= <<<BODY
<p><a href="game.php?page=research">Research</a> unlocks buildings, ships, defenses, and fleet options. Start a <strong>Research Lab</strong> from Buildings as soon as you can — the lab has no earlier building prerequisite.</p>
<p>Each research level takes time. A higher Research Lab shortens that queue. Later buildings such as University sit much deeper in the tree; use Technologies to see the path instead of trying to memorize it.</p>
<h3>Technologies (tech tree)</h3>
<p><a href="game.php?page=techtree">Technologies</a> is the live requirement list for buildings, research, ships, and defenses. If something is greyed out on Buildings, Research, or Shipyard, open Technologies and follow the missing levels. That page is sorted so early unlocks (Shipyard) appear before late ones (University).</p>
BODY;
$LNG['questions'][1][4]['title']	= 'Resources and production';
$LNG['questions'][1][4]['body']		= <<<BODY
<p>HiveNova uses five resources:</p>
<ul>
<li><strong>Metal</strong> — Ore Extractor</li>
<li><strong>Silicon</strong> — Silicon Refinery</li>
<li><strong>Uranium</strong> — Uranium Centrifuge</li>
<li><strong>Energy</strong> — Solar Power Plant (and later Uranium Power Plant). Energy is not stored as cargo; it powers mines.</li>
<li><strong>Pizzabits</strong> — a premium currency. Buildings, ships, defenses, and research do not require it. See the Pizzabits topic.</li>
</ul>
<p><a href="game.php?page=resources">Resources</a> lets you change how hard each mine works. If energy is short, lower mine output there instead of sitting in a red deficit.</p>
<p>Storage buildings hold overflow Metal, Silicon, and Uranium. Production that exceeds storage is wasted. <a href="game.php?page=trader">Market</a> can swap one mined resource for another.</p>
BODY;
$LNG['questions'][1][5]['title']	= 'Fleet and Galaxy';
$LNG['questions'][1][5]['body']		= <<<BODY
<p><a href="game.php?page=galaxy">Galaxy</a> shows nearby systems. From a row you can spy, message a player, or send a fleet to those coordinates. <a href="game.php?page=fleetTable">Fleet</a> is the full send screen: pick ships, set coordinates and speed, then choose a mission.</p>
<p>Slower flights use less Uranium. Always leave enough fuel on the planet for the return trip.</p>
<p>Common missions include Attack, ACS, Transport, Deploy, Spying, Colonize, Recycle, Expedition, Transfer, Trade, and Salvage. Colony ships colonize empty slots. Recyclers collect debris. Expeditions fly to deep space. Transfer delivers ships and cargo to another player's planet.</p>
<p>What you can build, and which missions you can fly, still depends on research and Shipyard level. Check <a href="game.php?page=techtree">Technologies</a> before you plan a fleet.</p>
BODY;
$LNG['questions'][2]['category']	= 'Empire';
$LNG['questions'][2][1]['title']	= 'Alliances';
$LNG['questions'][2][1]['body']		= <<<BODY
<p>Open <a href="game.php?page=alliance">Alliance</a> to join one or create your own (tag plus name). Members can see internal text, receive alliance messages, and use alliance tools the founder enables.</p>
<p>Founders can edit the public and internal descriptions, ranks, members, and the alliance image. If you no longer want to lead, transfer ownership instead of dissolving the alliance when someone else can take it.</p>
<p>Discord is the community channel for finding groups and asking rules questions. Support tickets are for account or game issues.</p>
BODY;
$LNG['questions'][2][2]['title']	= 'Combat and ACS';
$LNG['questions'][2][2]['body']		= <<<BODY
<p>Spy from Galaxy before you attack. Combat reports show what happened; Hall of Fame lists notable battles.</p>
<p><strong>ACS</strong> (Alliance Combat System) lets invited players join the same attack. It is a fleet mission, not a building unlock. Alliance Depot is a separate building that supplies fuel to friendly fleets holding in orbit — it is not required to start ACS.</p>
<p>For testing ship and defense mixes, use the <a href="game.php?page=battleSimulator">Simulator</a>. Raid yields, debris, and moon chances are universe settings, so this FAQ does not quote fixed percentages. Ask on Discord if you need the current server rules.</p>
BODY;
$LNG['questions'][2][3]['title']	= 'Pizzabits';
$LNG['questions'][2][3]['body']		= <<<BODY
<p>Pizzabits is the fourth resource. You can spend it on Officers and related extras. Buildings, ships, defenses, and research do not require Pizzabits.</p>
<p>You can earn Pizzabits from expedition missions and from depositing PIZZA. A deposit of 1 PIZZA converts to 10 Pizzabits. Pizzabits cannot be transferred between players or converted back to PIZZA.</p>
<p>Pizzabit purchases last for the current universe or season only. A linked Hive account is required to deposit PIZZA and to earn on-chain rewards. Ask on Discord if you need help creating or linking a Hive account.</p>
BODY;
