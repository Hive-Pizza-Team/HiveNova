function number_format (number, decimals) {
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = '.',
        dec = ',',
        s = '',
        toFixedFix = function (n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
        };
    // Fix for IE parseFloat(0.55).toFixed(0) = 0;
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');    }
    return s.join(dec);
}

function NumberGetHumanReadable(value, dec) {
	if(typeof dec === "undefined") {
		dec = 0;
	}
	if(dec == 0)
	{
		value	= removeE(Math.floor(value));
	}
	var pref = (typeof numberFormat !== 'undefined') ? numberFormat : 'auto';
	if (pref !== 'eu') {
		return new Intl.NumberFormat(undefined, { maximumFractionDigits: dec, minimumFractionDigits: dec }).format(parseFloat(value));
	}
	return number_format(value, dec);
}

function shortly_number(number)
{
    var unit	= ["", "K", "M", "B", "T", "Q", "Q+", "S", "S+", "O", "N"];
	var negate	= number < 0 ? -1 : 1;
	var key		= 0;
	number		= Math.abs(number);
	
	if(number >= 1000000) {
		++key;
		while(number >= 1000000)
		{
			++key;
			number = number / 1000000;
		}
	} else if(number >= 1000) {
		++key;
		number = number / 1000;
	}
	
	decial	= key != 0 && number != 0 && number < 100;
	return NumberGetHumanReadable(negate * number, decial)+(key !== 0 ? '&nbsp;'+unit[key] : '');
}

function removeE(Number) {
	Number = String(Number);
	if (Number.search(/e\+/) == -1) 
		return Number;
	var e = parseInt(Number.replace(/\S+.?e\+/g, ''));
	if (isNaN(e) || e == 0) 
		return Number;
	else if ($.browser.webkit || $.browser.msie) 
		return parseFloat(Number).toPrecision(Math.min(e + 1, 21));
	else 
		return parseFloat(Number).toPrecision(e + 1);
}

(function() {
    function localizeNumbers() {
        var pref = (typeof numberFormat !== 'undefined') ? numberFormat : 'auto';
        if (pref === 'eu') return;

        var formatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 20 });

        $('.ln').each(function() {
            var raw = $(this).data('n');
            if (raw !== undefined && raw !== '') {
                var num = parseFloat(raw);
                if (!isNaN(num)) {
                    $(this).text(formatter.format(num));
                }
            }
        });
    }

    $(document).ready(localizeNumbers);
    $(document).ajaxComplete(localizeNumbers);
})();

function getFormatedDate(timestamp, format) {
	var currTime = new Date();
	currTime.setTime(timestamp + (ServerTimezoneOffset * 1000));
	str = format;
	str = str.replace('[d]', dezInt(currTime.getDate(), 2));
	str = str.replace('[D]', days[currTime.getDay()]);
	str = str.replace('[m]', dezInt(currTime.getMonth() + 1, 2));
	str = str.replace('[M]', months[currTime.getMonth()]);
	str = str.replace('[j]', parseInt(currTime.getDate()));
	str = str.replace('[Y]', currTime.getFullYear());
	str = str.replace('[y]', currTime.getFullYear().toString().substr(2, 4));
	str = str.replace('[G]', currTime.getHours());
	str = str.replace('[H]', dezInt(currTime.getHours(), 2));
	str = str.replace('[i]', dezInt(currTime.getMinutes(), 2));
	str = str.replace('[s]', dezInt(currTime.getSeconds(), 2));
	return str;
}
function dezInt(num, size, prefix) {
	prefix = (prefix) ? prefix : "0";
	var minus = (num < 0) ? "-" : "", 
	result = (prefix == "0") ? minus : "";
	num = Math.abs(parseInt(num, 10));
	size -= ("" + num).length;
	for (var i = 1; i <= size; i++) {
		result += "" + prefix;
	}
	result += ((prefix != "0") ? minus : "") + num;
	return result;
}

function getFormatedTime(time) {
	hours = Math.floor(time / 3600);
	timeleft = time % 3600;
	minutes = Math.floor(timeleft / 60);
	timeleft = timeleft % 60;
	seconds = timeleft;
	return dezInt(hours, 2) + ":" + dezInt(minutes, 2) + ":" + dezInt(seconds, 2);
}

function GetRestTimeFormat(Secs) {
	var s = Secs;
	var m = 0;
	var h = 0;
	if (s > 59) {
		m = Math.floor(s / 60);
		s = s - m * 60;
	}
	if (m > 59) {
		h = Math.floor(m / 60);
		m = m - h * 60;
	}
	return dezInt(h, 2) + ':' + dezInt(m, 2) + ":" + dezInt(s, 2);
}

function isMobileViewport() {
	return window.matchMedia('(max-width: 699px)').matches;
}

function getDialogDimensions(width, height) {
	if (isMobileViewport()) {
		var vw = window.innerWidth || document.documentElement.clientWidth;
		var vh = window.innerHeight || document.documentElement.clientHeight;
		return {
			width: Math.max(280, Math.min(width, Math.floor(vw * 0.95))),
			height: Math.max(200, Math.min(height, Math.floor(vh * 0.9)))
		};
	}
	return { width: width, height: height };
}

function normalizeGameUrl(target_url) {
	var url = target_url;
	if (url.indexOf('game.php') !== 0 && url.charAt(0) === '?') {
		url = 'game.php' + url;
	}
	if (url.indexOf('ajax=1') === -1) {
		url += (url.indexOf('?') >= 0 ? '&' : '?') + 'ajax=1';
	}
	return url;
}

function OpenPopup(target_url, win_name, width, height) {
	if (isMobileViewport()) {
		var dims = getDialogDimensions(width, height);
		return Dialog.open(normalizeGameUrl(target_url), dims.width, dims.height);
	}
	var new_win = window.open(normalizeGameUrl(target_url), win_name, 'scrollbars=yes,statusbar=no,toolbar=no,location=no,directories=no,resizable=no,menubar=no,width='+width+',height='+height+',screenX='+((screen.width-width) / 2)+",screenY="+((screen.height-height) / 2)+",top="+((screen.height-height) / 2)+",left="+((screen.width-width) / 2));
	new_win.focus();
	return false;
}

function DestroyMissiles() {
	$.getJSON('?page=information&mode=destroyMissiles&'+$('.missile').serialize(), function(data) {
		$('#missile_502').text(NumberGetHumanReadable(data[0]));
		$('#missile_503').text(NumberGetHumanReadable(data[1]));
		$('.missile').val('');
	});
}

function handleErr(errMessage, url, line) 
{ 
	error = "There is an error at this page.\n";
	error += "Error: " + errMessage+ "\n";
	error += "URL: " + url + "\n";
	error += "Line: " + line + "\n\n";
	error += "Click OK to continue viewing this page,\n";
	alert(error);
	if(typeof console == "object")
		console.log(error);
 
	return true; 
}

var Dialog	= {	
	_fancyboxReady: null,

	ensureFancybox: function() {
		if (typeof $.fancybox === 'function') {
			return $.Deferred().resolve().promise();
		}
		if (Dialog._fancyboxReady) {
			return Dialog._fancyboxReady;
		}
		var versionMatch = (document.querySelector('script[src*="scripts/game/base.js"]') || {src: ''}).src.match(/[?&]v=([^&]+)/);
		var versionQuery = versionMatch ? ('?v=' + versionMatch[1]) : '';
		var cssHref = './styles/resource/css/base/jquery.fancybox.css' + versionQuery;
		var jsHref = './scripts/base/jquery.fancybox.js' + versionQuery;
		if (!$('link[data-fancybox-css]').length) {
			$('<link rel="stylesheet" data-fancybox-css>').attr('href', cssHref).appendTo('head');
		}
		Dialog._fancyboxReady = $.getScript(jsHref);
		return Dialog._fancyboxReady;
	},

	info: function(ID){
		var height = (ID > 600 && ID < 800 || ID > 900 && ID < 930) ? 210 : ((ID > 100 && ID < 200) ? 300 : 620);
		if (ID === 921) {
			height = 380;
		}
		return Dialog.open('game.php?page=information&id='+ID, 590, height);
	},
	
	alert: function(msg, callback){
		alert(msg);
		if(typeof callback === "function") {
			callback();
		}
	},
	
	PM: function(ID, Subject, Message) {
		if(typeof Subject !== 'string')
			Subject	= '';

		return Dialog.open('game.php?page=messages&mode=write&id='+ID+'&subject='+encodeURIComponent(Subject)+'&message='+encodeURIComponent(Subject), 650, 350);
	},
	
	Playercard: function(ID) {
		return isPlayerCardActive && Dialog.open('game.php?page=playerCard&id='+ID, 650, 650);
	},
	
	Buddy: function(ID) {
		return Dialog.open('game.php?page=buddyList&mode=request&id='+ID, 650, 300);
	},
	
	PlanetAction: function(tab) {
		var url = 'game.php?page=overview&mode=actions';
		if (tab === 'delete') {
			url += '&tab=delete';
		}
		return Dialog.open(url, 400, 210);
	},
	
	AllianceChat: function() {
	    return OpenPopup('game.php?page=chat&action=alliance', "alliance_chat", 960, 900);
	},
	
	open: function(url, width, height) {
		var dims = getDialogDimensions(width, height);
		Dialog.ensureFancybox().done(function() {
			$.fancybox({
				width: dims.width,
				padding: 0,
				height: dims.height,
				type: 'iframe',
				href: url,
				onStart: function() {
					if (isMobileViewport()) {
						$('#fancybox-wrap').addClass('mobile-dialog');
					}
				},
				onClosed: function() {
					$('#fancybox-wrap').removeClass('mobile-dialog');
				}
			});
		});
		
		return false;
	}
}

function showGameToast(text, className, durationMs) {
	var tip = $('#tooltip');
	if (!tip.length) {
		return;
	}
	var extra = className || 'notify-toast';
	var isError = extra.indexOf('notify-error') !== -1;
	tip.stop(true, true);
	tip.text(String(text == null ? '' : text))
		.removeClass('tooltip-mobile-active tooltip_sticky_div notify notify-error notify-toast notify-success')
		.addClass('notify ' + extra)
		.css({
			position: 'fixed',
			top: isError ? '50%' : 'auto',
			left: '50%',
			right: 'auto',
			bottom: isError ? 'auto' : 'calc(56px + env(safe-area-inset-bottom, 0px) + 12px)',
			transform: isError ? 'translate(-50%, -50%)' : 'translateX(-50%)',
			zIndex: 400,
			maxWidth: 'calc(100vw - 32px)',
			textAlign: 'center'
		})
		.show();
	window.setTimeout(function () {
		tip.fadeOut(400, function () {
			if (typeof resetTooltipOverlay === 'function') {
				resetTooltipOverlay(tip);
			} else {
				tip.removeClass('notify notify-error notify-toast notify-success').css({
					position: '',
					top: '',
					left: '',
					right: '',
					bottom: '',
					transform: '',
					zIndex: '',
					maxWidth: '',
					textAlign: ''
				});
			}
			tip.hide();
		});
	}, durationMs || 2500);
}

function NotifyBox(text) {
	showGameToast(text, 'notify-toast', 2500);
}

/** Build/research queue bar — replaces jQuery UI progressbar (removed in #362). */
function initQueueProgressBar(selector, time, resttime) {
	if (time <= 0) {
		return;
	}
	var $bar = $(selector);
	if (!$bar.length || $bar.children('.ui-progressbar-value').length) {
		return;
	}
	var startPct = Math.max(100 - (resttime / time) * 100, 0.01);
	$bar.addClass('ui-progressbar ui-widget ui-widget-content ui-corner-all');
	var $fill = $('<div class="ui-progressbar-value ui-widget-header ui-corner-left">').appendTo($bar);
	$fill.css('width', startPct + '%');
	$fill.addClass('ui-corner-right').animate({ width: '100%' }, resttime * 1000, 'linear');
}


function UhrzeitAnzeigen() {
   $(".servertime").text(getFormatedDate(serverTime.getTime(), tdformat));
}

(function() {
	function initPlanetSelector() {
		var selector = document.getElementById('planetSelector');
		if (!selector) {
			return;
		}
		selector.addEventListener('change', function() {
			document.location = '?' + queryString + '&cp=' + selector.value;
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPlanetSelector);
	} else {
		initPlanetSelector();
	}
})();

$(function() {
	// Let PHP skip mobile-hidden work on the next request (galaxy system control, etc.).
	if (typeof $.cookie === 'function') {
		var compact = isMobileViewport() ? '1' : '0';
		if ($.cookie('hn_compact') !== compact) {
			$.cookie('hn_compact', compact, { path: '/', expires: 7 });
		}
	}

	$('#drop-admin').on('click', function() {
		$.get('admin.php?page=logout', function() {
			$('.globalWarning').animate({
				'height' :0,
				'padding' :0,
				'opacity' :0
			}, function() {
				$(this).hide();
			});
		});
	});
	
	
	window.setInterval(function() {
		$('.countdown').each(function() {
			var s		= $(this).data('time') - (serverTime.getTime() - startTime) / 1000;
			if(s <= 0) {
				$(this).text('-');
			} else {
				$(this).text(GetRestTimeFormat(s));
			}
		});
	}, 1000);

	UhrzeitAnzeigen();
	setInterval(UhrzeitAnzeigen, 1000);
	
	$("button#create_new_alliance_rank").on('click', function(e) {
		e.preventDefault();
		var dialog = document.getElementById('new_alliance_rank');
		if (!dialog) {
			return false;
		}
		if (typeof dialog.showModal === 'function') {
			dialog.showModal();
		} else {
			dialog.setAttribute('open', 'open');
			dialog.style.display = 'block';
		}
		return false;
	});

	$(document).on('click', '#new_alliance_rank [data-close-dialog]', function(e) {
		e.preventDefault();
		var dialog = document.getElementById('new_alliance_rank');
		if (!dialog) {
			return;
		}
		if (typeof dialog.close === 'function') {
			dialog.close();
		} else {
			dialog.removeAttribute('open');
			dialog.style.display = 'none';
		}
	});
});

const HiveKeychainLogin = async () => {
	if (typeof(hive_keychain) == "undefined") {
		alert('You must install HiveKeychain extension first');
		return;
	}

	try
  	{
		const entered = prompt("Enter hive account name: ");
		if (entered === null) {
			return;
		}
		const hiveaccount = entered.toLowerCase().trim();
		if (!hiveaccount) {
			return;
		}
		await hive_keychain.requestSignBuffer(
			hiveaccount,
			`${hiveaccount} is my account.`,
			"Posting",
			(response) => {
				if (response.success) {
					document.querySelector('input#hiveAccount').value = hiveaccount;
					document.querySelector('input#hivesign').value = response.result;
					document.getElementById('saveChanges').click();
				} else {
					console.error('Keychain error', response.error);
				}
			},
			null,
			'Moon Login'
		);
	} catch (error) {
		alert(error.message);
	}
}

const DepositPizzaTokens = async (hiveaccount, universe, wallet) => {
	if (!hiveaccount) {
		alert(typeof needHiveForDeposit !== 'undefined' ? needHiveForDeposit : 'Link a Hive account in Settings before you can deposit PIZZA.');
		return;
	}
	if (typeof(hive_keychain) == "undefined") {
		alert('You must install HiveKeychain extension first');
		return;
	}

	try
	{
		const amount = parseFloat(prompt("Enter $PIZZA amount: "));
		if (isNaN(amount) || amount <= 0) {
			return;
		}
		const depositWallet = (wallet && String(wallet).trim()) ? String(wallet).trim() : 'moon.deposit';
		const memo = 'u' + universe;
		const tokenSymbol = 'PIZZA';
		hive_keychain.requestSendToken(hiveaccount, depositWallet, amount.toFixed(3), memo, tokenSymbol, (response) => {
			console.debug(JSON.stringify(response));
		});
	} catch (error) {
		alert(error.message);
	}
}

const extractHiveTxId = (response) => {
	if (!response || !response.success) {
		return '';
	}
	const result = response.result;
	if (typeof result === 'string') {
		return result;
	}
	if (result && typeof result === 'object') {
		return result.tx_id || result.id || result.txid || '';
	}
	return '';
}

const DepositSeasonPizza = async (hiveaccount, wallet, amount, memo) => {
	if (typeof(hive_keychain) == "undefined") {
		alert('You must install HiveKeychain extension first');
		return;
	}

	try {
		const qty = parseFloat(amount);
		if (isNaN(qty) || qty <= 0 || !wallet) {
			return;
		}
		hive_keychain.requestSendToken(hiveaccount, wallet, qty.toFixed(3), memo, 'PIZZA', (response) => {
			console.debug(JSON.stringify(response));
			const txid = extractHiveTxId(response);
			if (!txid) {
				return;
			}
			window.setTimeout(() => {
				const form = document.createElement('form');
				form.method = 'post';
				form.action = 'game.php?page=season';
				const mode = document.createElement('input');
				mode.name = 'mode';
				mode.value = 'confirm';
				form.appendChild(mode);
				const tx = document.createElement('input');
				tx.name = 'txid';
				tx.value = txid;
				form.appendChild(tx);
				document.body.appendChild(form);
				form.submit();
			}, 2000);
		});
	} catch (error) {
		alert(error.message);
	}
}

const buildBattleShareCommentOptions = (author, permlink) => ({
	author,
	permlink,
	max_accepted_payout: '1000000.000 HBD',
	percent_steem_dollars: 10000,
	allow_votes: true,
	allow_curation_rewards: true,
	extensions: [[0, { beneficiaries: [] }]],
});

const parseBattleShareMetadata = (draft, tags) => {
	let meta = draft.json_metadata;
	if (typeof meta === 'string') {
		try {
			meta = JSON.parse(meta);
		} catch (e) {
			meta = null;
		}
	}
	if (!meta || typeof meta !== 'object') {
		meta = { tags, app: 'hivenova/battle-share', format: 'markdown' };
	}
	if (!Array.isArray(meta.tags) || meta.tags.length === 0) {
		meta.tags = tags;
	}
	if (!meta.format) {
		meta.format = 'markdown';
	}
	if (!meta.app) {
		meta.app = 'hivenova/battle-share';
	}
	return meta;
};

const HiveKeychainShareBattle = (draft, destination, callback) => {
	if (typeof hive_keychain === 'undefined') {
		if (typeof callback === 'function') {
			callback('Keychain missing');
		}
		return;
	}
	if (!draft || !draft.hive_account || !draft.permlink || !draft.body) {
		if (typeof callback === 'function') {
			callback('Invalid draft');
		}
		return;
	}

	const tags = Array.isArray(draft.tags) && draft.tags.length ? draft.tags.slice() : ['moon', 'hivenova', 'gaming'];
	let parentPerm = tags[0] || 'moon';
	let parentAccount = null;

	if (destination && destination.type === 'community') {
		parentPerm = (destination.parent_permlink || '').trim();
		const parentAuthor = (destination.parent_author || '').trim();
		if (!parentPerm) {
			if (typeof callback === 'function') {
				callback('Community required');
			}
			return;
		}
		parentAccount = parentAuthor || null;
		if (!tags.includes(parentPerm)) {
			tags.unshift(parentPerm);
		}
	}

	const metadata = parseBattleShareMetadata(draft, tags);
	const commentOptions = buildBattleShareCommentOptions(draft.hive_account, draft.permlink);

	if (typeof hive_keychain.requestPost !== 'function') {
		if (typeof callback === 'function') {
			callback('Keychain requestPost unavailable');
		}
		return;
	}

	hive_keychain.requestPost(
		draft.hive_account,
		draft.title || '',
		draft.body,
		parentPerm,
		parentAccount,
		metadata,
		draft.permlink,
		commentOptions,
		(response) => {
			if (!response || !response.success) {
				const err = (response && response.message) ? response.message : (response && response.error) ? response.error : 'Broadcast failed';
				if (typeof callback === 'function') {
					callback(err);
				}
				return;
			}
			const txid = extractHiveTxId(response);
			if (typeof callback === 'function') {
				callback(null, txid);
			}
		}
	);
};