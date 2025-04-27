function add(){
	$("#form").attr('action', 'game.php?page=battleSimulator&action=moreslots');
	$("#form").attr('method', 'POST');
	$("#form").submit();
	return true;
}

function check(){
	$.post('game.php?page=battleSimulator&mode=send', $('#form').serialize(), function(data){
		try{ 
			data	= $.parseJSON(data);
			window.open('_blank','game.php?page=raport&raport='+data).focus();
		} catch(e) {
			Dialog.alert(data);
		}
	});
	
	setTimeout(function(){$("#submit:hidden").removeAttr('style').hide().fadeIn();}, 10000);
	setTimeout(function(){$("#wait:visible").removeAttr('style').hide().fadeOut();}, 10000);
	return true;
}

$(function() {
	$("#tabs").tabs();

	var $tabs = $('#tabs').tabs({
		tabTemplate: '<li><a href="#{href}">#{label}</a></li>',
	});
	
	$('.reset').live('click', function(e) {
		e.preventDefault();
	
		var index = $(this).parent().index();
		
		
		$(this).parent().parent().nextAll().each(function() {
			$(this).children('td:eq('+index+')').children().val(0);
		});
		return false;
	});
});