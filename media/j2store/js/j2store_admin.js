if(typeof(j2store) == 'undefined') {
	var j2store = {};
}
if(typeof(j2store.jQuery) == 'undefined') {
	j2store.jQuery = jQuery.noConflict();
}

if(typeof(J2Store) == 'undefined') {
	J2Store = jQuery.noConflict();
}

function removePAOption(pao_id) {
	(function($) {
	$.ajax({
			type : 'post',
			url :  'index.php?option=com_j2store&view=products&task=removeProductOption',
			data : 'pao_id=' + pao_id,
			dataType : 'json',
			success : function(data) {
				if(data.success) {
					$('#pao_current_option_'+pao_id).remove();
				}
			 }
		});
	})(j2store.jQuery);	
}