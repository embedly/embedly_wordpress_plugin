jQuery(document).ready(function($){
    $("a.info").bind("click", function(e){
        e.preventDefault();
        if($(this).siblings('input').attr("checked") == false)
            $(this).siblings('input').attr("checked", "checked");
        else
            $(this).siblings('input').removeAttr("checked");
        $(this).siblings('input').trigger('change');
    });
    $(".actions .all").bind("click", function(e){
        e.preventDefault();
        $('input').attr("checked", "checked");
        $('input').trigger('change');
    });

    $(".actions .clearselection").bind("click", function(e){
        e.preventDefault();
        $('input:checked').trigger('change');
        $('input').removeAttr("checked");
        
    });
    $(".actions .videos").bind("click", function(e){
        e.preventDefault();
        $('li.video input').attr("checked", "checked");
        $('li.video input').trigger('change');
    });
    
    $(".actions .photos").bind("click", function(e){
        e.preventDefault();
        $('li.photo input').attr("checked", "checked");
        $('li.photo input').trigger('change');
    });

    $(".actions .products").bind("click", function(e){
        e.preventDefault();
        $('li.product input').attr("checked", "checked");
        $('li.product input').trigger('change');
    });

    $(".actions .audio").bind("click", function(e){
        e.preventDefault();
        $('li.audio input').attr("checked", "checked");
        $('li.audio input').trigger('change');
    });
    
    $('#pro_embedly_form').bind("submit", function(e){
        e.preventDefault();
	$('#message').remove();
        $('#pro_message').remove();
        var providers = [];

	if ($('#pro_embedly_check').is(':checked')) {
	    if( $("#pro_embedly_key").val().length > 0 ) {
		providers.push($("#pro_embedly_key").attr('value'))
	        var data = {
	            action: 'pro_embedly_update',
	            pro_key: providers.join(',')
	        };
	        jQuery.post(ajaxurl, data, function(json) {
	            if (json.error){
		        $('h2#pro').after('<div class="error" id="message"><p><strong>Something went wrong. Try again later.</strong></p></div>');
		    } else {
        		$('h2#pro').after('<div class="updated" id="message"><p><strong>Pro Embedly settings saved successfully!</strong></p></div>');
		    }
		}, 'json');	
	    } else $('h2#pro').after('<div class="error" id="pro_message"><p><strong>That can\'t be a Pro Embedly Key. Plese enter a proper key before proceeding.</strong></p></div>');
	} else {
	$('h2#pro').after('<div class="error" id="pro_message"><p><strong>Please check the checkbox first before proceeding.</strong></p></div>');
	}
	
    });
    $('#embedly_providers_form').bind("submit", function(e){
        e.preventDefault();
        $('#message').remove();
        var providers = [];
        $('UL.generator LI INPUT:checked').each(function(index, elem){
            providers.push($(elem).attr('name'))
        });
        var data = {
            action: 'embedly_update',
            providers: providers.join(',')
        };
        jQuery.post(ajaxurl, data, function(json) {
        	if (json.error){
                $('h2').after('<div class="error" id="message"><p><strong>Something went wrong. Try again later.</strong></p></div>');
        	} else {
        		$('h2').after('<div class="updated" id="message"><p><strong>Providers Updated</strong></p></div>');
        	}
        }, 'json');
    });
    function create_provider(obj){
        var checked = (obj.selected ==1)? 'checked=checked':'';
        var li = '<li class="'+obj.type+'" id="'+obj.name+'"><input type="checkbox" name="'+obj.name+'" '+checked+' /> ';
        li += '<a href="#'+obj.name+'" class="info "><img src="'+obj.favicon+'" title="'+obj.name+'" alt="'+obj.displayname+'">'+obj.name+'</a></li>';
        return li;
    }
    $('#embedly_update_providers_form').bind("submit", function(e){
        e.preventDefault();
        $('#message').remove();
        var providers = [];
        $('UL.generator LI INPUT:checked').each(function(index, elem){
            providers.push($(elem).attr('name'))
        });
        var data = {
            action: 'embedly_update_providers'
        };
        jQuery.post(ajaxurl, data, function(json) {
            if (json.hasOwnProperty('error')){
                $('h2').after('<div class="error" id="message"><p><strong>Something went wrong. Try again later.</strong></div>');
            } else {
            	//If something went wrong before there might not be a ul. Reload the page.
            	if($('UL.generator').length != 1){
            		window.location.reload();
            	}
                $('UL.generator').html('');
                $.each(json, function(index, obj){
                    $('UL.generator').append(create_provider(obj));
                });
                $('h2').after('<div class="updated" id="message"><p><strong>Providers Updated.</strong></p></div>');
            }
        }, 'json');
    });
});

