function searchQuery() {
	return $.trim($('#searchtext').val());
}

function fetchResults() {
	var term = searchQuery();
	if (!term) {
		return;
	}

	$('#searchEmpty').prop('hidden', true);
	$('#loading').show();
	$.get('game.php?page=search&mode=result&type='+$('#type').val()+'&search='+encodeURIComponent(term)+'&ajax=1', function(data) {
		$('#resulttable').remove();
		$('content > table:not(.hack)').after(data);
		$('#loading').hide();
	});
}

function submitSearch(event) {
	if (event) {
		event.preventDefault();
	}

	if (!searchQuery()) {
		$('#resulttable').remove();
		$('#searchEmpty').prop('hidden', false);
		return;
	}

	fetchResults();
}

function instant(event) {
	if (event.keyCode == 13) {
		event.preventDefault();
		submitSearch();
		return;
	}

	if ($.inArray(event.keyCode, [
		91, // WINDOWS
		18, // ALT
		20, // CAPS_LOCK
		188, // COMMA
		91, // COMMAND
		91, // COMMAND_LEFT
		93, // COMMAND_RIGHT
		17, // CONTROL
		40, // DOWN
		35, // END
		27, // ESCAPE
		36, // HOME
		45, // INSERT
		37, // LEFT
		93, // MENU
		107, // NUMPAD_ADD
		110, // NUMPAD_DECIMAL
		111, // NUMPAD_DIVIDE
		108, // NUMPAD_ENTER
		106, // NUMPAD_MULTIPLY
		109, // NUMPAD_SUBTRACT
		34, // PAGE_DOWN
		33, // PAGE_UP
		190, // PERIOD
		39, // RIGHT
		16, // SHIFT
		32, // SPACE
		9, // TAB
		38, // UP
		91 // WINDOWS
	]) !== -1) {
		return;
	}

	$('#searchEmpty').prop('hidden', true);
	if (!searchQuery()) {
		$('#resulttable').remove();
		return;
	}

	fetchResults();
}

$(document).ready(function() {
	$('#searchtext').on('keyup', instant);
	$('#searchbutton').on('click', submitSearch);
	$('#type').on('change', function() {
		if (searchQuery()) {
			fetchResults();
		}
	});
});
