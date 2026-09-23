'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '../..');
const tokens = fs.readFileSync(path.join(root, 'styles/resource/css/tokens.css'), 'utf8');
const mainCss = fs.readFileSync(path.join(root, 'styles/resource/css/ingame/main.css'), 'utf8');
const hiveCss = fs.readFileSync(path.join(root, 'styles/theme/hive/formate.css'), 'utf8');
const topnav = fs.readFileSync(path.join(root, 'styles/templates/game/main.topnav.tpl'), 'utf8');
const nav = fs.readFileSync(path.join(root, 'styles/templates/game/main.navigation.tpl'), 'utf8');
const faqTpl = fs.readFileSync(path.join(root, 'styles/templates/game/page.questions.default.tpl'), 'utf8');
const resourcesTpl = fs.readFileSync(path.join(root, 'styles/templates/game/page.resources.default.tpl'), 'utf8');
const buildingsTpl = fs.readFileSync(path.join(root, 'styles/templates/game/page.buildings.default.tpl'), 'utf8');
const ingameEn = fs.readFileSync(path.join(root, 'language/en/INGAME.php'), 'utf8');
const varsPhp = fs.readFileSync(path.join(root, 'includes/vars.php'), 'utf8');
const bottomnav = fs.readFileSync(path.join(root, 'styles/templates/game/main.bottomnav.tpl'), 'utf8');

const mobileIdx = mainCss.indexOf('@media screen and (max-width: 699px)');
assert.notEqual(mobileIdx, -1, 'mobile breakpoint exists');
const desktopCss = mainCss.slice(0, mobileIdx);
const mobileCss = mainCss.slice(mobileIdx);

describe('chrome-v1.2 tokens', () => {
	it('declares locked top-bar and sidebar colors', () => {
		assert.match(tokens, /--color-chrome-res-label:\s*#8aa4b8/);
		assert.match(tokens, /--color-chrome-res-amount:\s*#e8eef5/);
		assert.match(tokens, /--color-chrome-res-over:\s*#e85d5d/);
		assert.match(tokens, /--color-chrome-res-max:\s*#50c878/);
		assert.match(tokens, /--color-chrome-menu-item:\s*#dce3ec/);
		assert.match(tokens, /--color-chrome-menu-hover-bg:\s*#252a33/);
		assert.match(tokens, /--color-chrome-menu-hover-text:\s*#ffffff/);
		assert.match(tokens, /--color-chrome-menu-active-bar:\s*#5eb0d4/);
		assert.match(tokens, /--color-chrome-menu-active-bg:\s*#1e2430/);
		assert.match(tokens, /--color-chrome-menu-section:\s*#78b0c8/);
		assert.match(tokens, /--color-chrome-menu-section-bg:\s*#1c2129/);
		assert.match(tokens, /--color-chrome-menu-footer:\s*#6a7380/);
	});
});

describe('chrome-v1.2 top resource bar', () => {
	it('keeps Metal · Silicon · Uranium · Energy · Pizzabits order', () => {
		assert.match(varsPhp, /resstype'\]\[1\]\s*=\s*array\(\s*RESOURCE_METAL,\s*RESOURCE_CRYSTAL,\s*RESOURCE_DEUTERIUM\s*\)/);
		assert.match(varsPhp, /resstype'\]\[2\]\s*=\s*array\(\s*RESOURCE_ENERGY\s*\)/);
		assert.match(varsPhp, /resstype'\]\[3\]\s*=\s*array\(\s*RESOURCE_DARKMATTER\s*\)/);
	});

	it('styles labels steel and keeps over-cap / energy-deficit red', () => {
		assert.match(desktopCss, /\.resource_name\s*\{[^}]*var\(--color-chrome-res-label\)/);
		assert.match(desktopCss, /\.is-over-capacity/);
		assert.match(desktopCss, /\.is-energy-deficit/);
		assert.match(desktopCss, /var\(--color-chrome-res-over\)/);
		assert.match(desktopCss, /var\(--color-chrome-res-max\)/);
		assert.match(hiveCss, /#resources_mobile \.resource_name/);
		assert.equal(desktopCss.includes('.resource_name {\n    color: var(--color-res-name)'), false);
	});

	it('uses classes instead of inline red in the live topnav cells', () => {
		const live = topnav.split('<!--')[0];
		assert.match(live, /resource-cell--pizzabits/);
		assert.match(live, /is-over-capacity/);
		assert.match(live, /is-energy-deficit/);
		assert.equal(live.includes('style="color:red"'), false);
		assert.equal(live.includes('Energy!'), false);
		assert.equal(topnav.includes('id="resources_mobile"'), true);
	});
});

describe('chrome-v1.2 sidebar', () => {
	it('scrolls inside the fixed sidebar so short viewports reach Logout', () => {
		assert.match(desktopCss, /menu \.fixed \{[\s\S]*?position:\s*fixed/);
		assert.match(desktopCss, /menu \.fixed \{[\s\S]*?bottom:\s*0/);
		assert.match(desktopCss, /menu \.fixed \{[\s\S]*?overflow-y:\s*auto/);
		assert.match(desktopCss, /menu \.fixed \{[\s\S]*?overscroll-behavior:\s*contain/);
		assert.match(mobileCss, /menu \{[\s\S]*?overflow-y:\s*auto/);
		assert.match(mobileCss, /menu \.fixed \{[\s\S]*?overflow:\s*visible/);
	});

	it('left-aligns items with 8–10px pad, section headers, and active bar', () => {
		assert.match(desktopCss, /#menu a \{[\s\S]*?padding:\s*8px 10px/);
		assert.match(desktopCss, /#menu a \{[\s\S]*?text-align:\s*left/);
		assert.match(desktopCss, /#menu \.menu-section/);
		assert.match(desktopCss, /#menu a\.active/);
		assert.match(desktopCss, /var\(--color-chrome-menu-active-bar\)/);
		assert.match(desktopCss, /var\(--color-chrome-menu-footer\)/);
		assert.equal(desktopCss.includes('accordion'), false);
		assert.equal(nav.includes('details') && nav.includes('accordion'), false);
	});

	it('labels Basics / Advanced / Administration splits and marks the active page', () => {
		assert.match(nav, /lm_menu_section_overview/);
		assert.match(nav, /lm_menu_section_empire/);
		assert.match(nav, /lm_menu_section_account/);
		assert.match(nav, /class="active"/);
		assert.equal((nav.match(/menu-section/g) || []).length >= 3, true);
		const ingameEn = fs.readFileSync(path.join(root, 'language/en/INGAME.php'), 'utf8');
		assert.match(ingameEn, /lm_menu_section_overview'\]\s*=\s*'Basics'/);
		assert.match(ingameEn, /lm_menu_section_empire'\]\s*=\s*'Advanced'/);
		assert.match(ingameEn, /lm_menu_section_account'\]\s*=\s*'Administration'/);
	});

	it('uses one Shipyard item and keeps Map plus Universe Feed under Advanced', () => {
		assert.equal((nav.match(/page=shipyard/g) || []).length, 1);
		assert.match(nav, /lm_shipshard/);
		assert.equal(nav.includes('lm_defenses'), false);
		const advanced = nav.indexOf('lm_menu_section_empire');
		const admin = nav.indexOf('lm_menu_section_account');
		const viz = nav.indexOf('page=viz');
		const feed = nav.indexOf('page=eventFirehose');
		assert.ok(advanced !== -1 && admin !== -1 && viz !== -1 && feed !== -1);
		assert.ok(viz > advanced && viz < admin);
		assert.ok(feed > advanced && feed < admin);
	});

	it('keeps Resources, Market, and Ship Merchant under Advanced', () => {
		const advanced = nav.indexOf('lm_menu_section_empire');
		const admin = nav.indexOf('lm_menu_section_account');
		const resources = nav.indexOf('page=resources');
		const market = nav.indexOf('page=trader');
		const shipMerchant = nav.indexOf('page=fleetDealer');
		assert.ok(advanced !== -1 && admin !== -1);
		assert.ok(resources > advanced && resources < admin);
		assert.ok(market > advanced && market < admin);
		assert.ok(shipMerchant > advanced && shipMerchant < admin);
		assert.ok(resources < market && market < shipMerchant);
	});

	it('pins Tech Tree next to Research and Shipyard in Basics', () => {
		const basics = nav.indexOf('lm_menu_section_overview');
		const advanced = nav.indexOf('lm_menu_section_empire');
		const shipyard = nav.indexOf('page=shipyard');
		const research = nav.indexOf('page=research');
		const techtree = nav.indexOf('page=techtree');
		const fleet = nav.indexOf('page=fleetTable');
		assert.ok(basics !== -1 && advanced !== -1);
		assert.ok(shipyard > basics && research > shipyard && techtree > research);
		assert.ok(techtree < fleet && techtree < advanced);
		assert.equal((nav.match(/page=techtree/g) || []).length, 1);
		const ingameEn = fs.readFileSync(path.join(root, 'language/en/INGAME.php'), 'utf8');
		assert.match(ingameEn, /lm_technology'\]\s*=\s*'Tech Tree'/);
	});

	it('puts FAQ in Basics, drops Forum, and moves community/admin tools to Administration', () => {
		const basics = nav.indexOf('lm_menu_section_overview');
		const advanced = nav.indexOf('lm_menu_section_empire');
		const admin = nav.indexOf('lm_menu_section_account');
		const faq = nav.indexOf('page=questions');
		const discord = nav.indexOf('{$discordUrl}');
		const support = nav.indexOf('page=ticket');
		const search = nav.indexOf('page=search');
		const banned = nav.indexOf('page=banList');
		assert.ok(basics !== -1 && advanced !== -1 && admin !== -1);
		assert.ok(faq > basics && faq < advanced);
		assert.ok(discord > admin && support > admin && search > admin && banned > admin);
		assert.ok(discord < support && support < search && search < banned);
		assert.equal(nav.includes('page=board'), false);
		assert.equal(nav.includes('lm_forums'), false);
		assert.equal(nav.includes('TODO(nav-ia)'), false);
		assert.match(faqTpl, /faq_intro/);
	});

	it('orders Advanced by the explicit product ranking', () => {
		const advanced = nav.indexOf('lm_menu_section_empire');
		const admin = nav.indexOf('lm_menu_section_account');
		const order = [
			'page=resources',
			'page=battleSimulator',
			'page=statistics',
			'page=records',
			'page=battleHall',
			'page=achievements',
			'page=viz',
			'page=eventFirehose',
			'page=trader',
			'page=fleetDealer',
			'page=alliance',
		].map((needle) => nav.indexOf(needle));
		assert.ok(advanced !== -1 && admin !== -1);
		assert.match(nav, /Advanced product order applied/);
		let previous = advanced;
		for (const pos of order) {
			assert.ok(pos > previous && pos < admin);
			previous = pos;
		}
	});
});

describe('chrome-v1.2 mobile', () => {
	it('snap-scrolls the top strip instead of shrinking type unreadably', () => {
		assert.match(mobileCss, /#resources_mobile \{[\s\S]*?scroll-snap-type:\s*x mandatory/);
		assert.match(mobileCss, /\.resource-cell \{[\s\S]*?min-height:\s*44px/);
		assert.match(mobileCss, /\.resource-cell \{[\s\S]*?min-width:\s*56px/);
	});

	it('reuses desktop sidebar tokens in the hamburger drawer', () => {
		assert.match(mobileCss, /#menu a \{[\s\S]*?text-align:\s*left/);
		assert.match(mobileCss, /#menu a \{[\s\S]*?min-height:\s*44px/);
		assert.match(mobileCss, /#menu \.menu-section/);
	});

	it('stacks #414 Resources at mobile width and keeps Apply reachable', () => {
		assert.match(mobileCss, /body#resources \.resources-table__row \{[\s\S]*?display:\s*block/);
		assert.match(mobileCss, /body#resources[\s\S]*overflow-x:\s*hidden/);
		assert.match(mobileCss, /body#resources \.resources-apply-bar \{[\s\S]*?display:\s*block/);
		assert.match(resourcesTpl, /class="resources-form"/);
		assert.match(resourcesTpl, /class="resources-table"/);
		assert.match(resourcesTpl, /resources-apply-bar/);
		assert.match(resourcesTpl, /data-label="\{\$LNG\.tech\.901\}"/);
	});

	it('collapses extra Buildings queue rows on mobile so the list can scroll', () => {
		assert.match(buildingsTpl, /id="buildlist"/);
		assert.match(buildingsTpl, /buildlist--collapsible/);
		assert.match(buildingsTpl, /buildlist-item--queued/);
		assert.match(buildingsTpl, /id="buildlistMoreToggle"/);
		assert.match(buildingsTpl, /bd_queue_more/);
		assert.match(ingameEn, /bd_queue_more'\]\s*=\s*'Show remaining queue'/);
		assert.match(ingameEn, /bd_queue_less'\]\s*=\s*'Hide remaining queue'/);
		assert.match(mobileCss, /#buildlist\.infos1 \{[\s\S]*?overflow:\s*visible/);
		assert.match(mobileCss, /#buildlist\.infos1 \{[\s\S]*?max-height:\s*none/);
		assert.match(mobileCss, /#buildlist\.buildlist--collapsible:not\(\.is-expanded\) \.buildlist-item--queued/);
		assert.match(mobileCss, /#buildlist \.buildlist-more-toggle \{[\s\S]*?min-height:\s*44px/);
		assert.match(desktopCss, /\.buildlist-more-toggle \{[\s\S]*?display:\s*none/);
	});

	it('keeps bottom-nav IA and 44px tap targets', () => {
		assert.match(bottomnav, /id="bottom-nav"/);
		assert.match(bottomnav, /page=overview/);
		assert.match(bottomnav, /page=buildings/);
		assert.match(bottomnav, /page=techtree/);
		assert.match(bottomnav, /lm_technology/);
		assert.match(bottomnav, /page=fleetTable/);
		assert.match(bottomnav, /page=galaxy/);
		assert.match(mobileCss, /#bottom-nav a,[\s\S]*?min-height:\s*44px/);
		assert.match(mobileCss, /#bottom-nav a,[\s\S]*?min-width:\s*44px/);
	});
});
