var Gate	= {
	max: function(ID) {
		var $el = $('#ship'+ID+'_value');
		var amount = $el.attr('data-n');
		if (amount == null || amount === '') {
			amount = $el.text().replace(/[.,]/g, '');
		}
		$('#ship'+ID+'_input').val(amount);
	},
	
	submit: function() {
		$.getJSON('?page=information&mode=sendFleet&'+$('.jumpgate').serialize(), function(data) {
			alert(data.message);
			if(!data.error)
			{
				parent.$.fancybox.close();
			}
		});		
	}
}
