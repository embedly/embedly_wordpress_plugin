jQuery(document).ready(function($) {

  var allCheckboxes     = $('.embedly-service-generator li input');
  var photoCheckboxes   = $('.embedly-service-generator li.photo input');
  var videoCheckboxes   = $('.embedly-service-generator li.video input');
  var richCheckboxes    = $('.embedly-service-generator li.rich input');
  var productCheckboxes = $('.embedly-service-generator li.product input');
  var audioCheckboxes   = $('.embedly-service-generator li.audio input');

  $('.embedly-service-generator li').each(function() {
    var iconBox = $(this).children('.full-service-wrapper');
    var labelWidth = $(this).find('label').width() + 53;
    $(this).hover(function() {
      iconBox.addClass('overlay-expanded').stop().animate({width: labelWidth + 'px'}, 150);
    }, 
    function() {
      iconBox.stop().animate({width: '47px'}, 150, function() {$(this).removeClass('overlay-expanded');});
    }).click(function() {
      if($(this).find('input[type=checkbox]').attr('checked')) {
        $(this).find('input[type=checkbox]').removeAttr('checked').trigger('change');
      }
      else {
        $(this).find('input[type=checkbox]').attr('checked', 'checked').trigger('change');
      }
    });
  });

  $('.embedly-actions a').click(function(e) {
    e.preventDefault();
    var elem_class = $(this).attr('class');
    switch(elem_class) {
      case 'all':
        allCheckboxes.attr('checked', 'checked').trigger('change');
      break;
      case 'clearselection':
        allCheckboxes.removeAttr('checked').trigger('change');
      break;
      case 'videos':
        videoCheckboxes.attr('checked', 'checked').trigger('change');
      break;
      case 'photos':
        photoCheckboxes.attr('checked', 'checked').trigger('change');
      break;
      case 'products':
        productCheckboxes.attr('checked', 'checked').trigger('change');
      break;
      case 'audio':
        audioCheckboxes.attr('checked', 'checked').trigger('change');
      break;
      case 'rich':
        richCheckboxes.attr('checked', 'checked').trigger('change');
      break;
    }
  });

  function create_provider(obj) {
    var checked = (obj.selected == 1) ? 'checked="checked"' : '';
    var li  = '<li class="'+obj.type+'" id="'+obj.name+'"><div class="full-service-wrapper"><label for="'+obj.name+'-checkbox" class="embedly-icon-name">'+obj.displayname+'</label>';
        li += '<div class="embedly-icon-wrapper"><input type="checkbox" id="'+obj.name+'-checkbox" name="'+obj.name+'" '+checked+' />';
        li += '<img src="'+obj.favicon+'" title="'+obj.name+'" alt="'+obj.displayname+'"></div></div></li>';
    return li;
  }

  $('.embedly_submit').click(function() {
    $('#embedly-message.embedly-error').hide();
    $('#embedly-message.embedly-updated').hide();
  });
  
  $('#embedly_providers_form').submit(function(e) {
    e.preventDefault();
    var embedly_key = $('#embedly_key').val();
    var providers = [];
    $('.embedly-service-generator li input:checked').each(function(index, elem) {
      providers.push($(elem).attr('name'));
    });
    var data = {
      action:      'embedly_update',
      providers:   providers.join(','),
      embedly_key: embedly_key
    };
    jQuery.post(ajaxurl, data, function(json) {
      if(json.error) {
        $('.embedly-error').fadeIn();
      }
      else {
        $('.embedly-updated').fadeIn();
      }
    }, 'json');
  });

  $('#embedly_update_providers_form').submit(function(e) {
    e.preventDefault();
    var providers = [];
    $('.embedly-service-generator li input:checked').each(function(index, elem) {
      providers.push($(elem).attr('name'));
    });
    var data = {
      action: 'embedly_update_providers'
    };
    jQuery.post(ajaxurl, data, function(json) {
      if(json.hasOwnProperty('error')) {
        $('.embedly-error').fadeIn();
      }
      else {
        if($('.embedly-service-generator').length != 1) {
          window.location.reload();
        }
        $('.embedly-service-generator').html('');
        $.each(json, function(index, obj) {
          $('.embedly-service-generator').append(create_provider(obj));
        });
        $('.embedly-updated').fadeIn();
      }
    }, 'json');
  });

});