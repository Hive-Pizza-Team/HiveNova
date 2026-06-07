$(function() {
	var activeTab = window.location.search.indexOf('tab=delete') !== -1 ? 1 : 0;
	$('#tabs').tabs({ active: activeTab });
});

function checkrename()
{
	if($.trim($('#name').val()) == '') {
		return false;
	} else {
		$.getJSON('game.php?page=overview&mode=rename&name='+$('#name').val(), function(response){
			if(!response.error) {
				parent.location.reload();
			} else {
				alert(response.message);
			}
		});
	}
}

function checkcancel()
{
	var password = $('#password').val();
	if(password == '') {
		return false;
	} else {
		$.post('game.php?page=overview', {'mode' : 'delete', 'password': password}, function(response) {
			if(response.ok){
				parent.location.reload();
			} else {
				alert(response.message);
			}
		}, "json");
	}
}