$(document).ready(function(){
	$('#content').change(function(){
		var slide = $('#slide');
		var html = $(this).val();
		slide.html(html);
	});
	$(' .new button').click(function(){
	    var props = $(this).parents('#props');
	    var pnew = props.find('tr.new');
	    var prop = pnew.find('.prop');
	    var val = pnew.find('.val');
		var slide = $('#slide');

	    var html = '<tr>'+
	            '<td>'+prop.val()+'</td>'+
	            '<td>'+val.val()+'</td>'+
	            '<td><button class="btn btn-danger remove-prop"><i class="fa fa-times"></i></button></td>'+
	        '</tr>';

	    props.prepend(html);
	    prop.val('');
	    val.val('');

	    $('.remove-prop').off('click').click(function(){
	    	$(this).parents('tr').remove();
	    });

		slide.removeAttr('style');

	    $('#props').find('tr').not(pnew).each(function(){
			var prop = $(this).find('.prop');
		    var val = $(this).find('.val');
		    slide.css(prop, val);
	    });
	});
});