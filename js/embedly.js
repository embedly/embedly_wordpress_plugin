/**
  * Quicksand 1.2.2
  * Reorder and filter items with a nice shuffling animation.
  * Copyright (c) 2010 Jacek Galanciak (razorjack.net) and agilope.com
  * Big thanks for Piotr Petrus (riddle.pl) for deep code review and wonderful docs & demos.
  * Dual licensed under the MIT and GPL version 2 licenses.
  * http://github.com/jquery/jquery/blob/master/MIT-LICENSE.txt
  * http://github.com/jquery/jquery/blob/master/GPL-LICENSE.txt
  * Project site: http://razorjack.net/quicksand
  * Github site: http://github.com/razorjack/quicksand
**/
(function($) {
  $.fn.quicksand = function(collection, customOptions) {
    var options = {
      duration: 750,
      easing: 'swing',
      attribute: 'data-id',
      adjustHeight: 'auto',
      useScaling: true,
      enhancement: function(c) {},
      selector: '> *',
      dx: 0,
      dy: 0
    };
    $.extend(options, customOptions);
    if($.browser.msie || (typeof($.fn.scale) == 'undefined')) {
      options.useScaling = false;
    }
    var callbackFunction;
    if(typeof(arguments[1]) == 'function') {
      var callbackFunction = arguments[1];
    }
    else if(typeof(arguments[2] == 'function')) {
      var callbackFunction = arguments[2];
    }
    return this.each(function(i) {
      var val;
      var animationQueue = [];
      var $collection = $(collection).clone();
      var $sourceParent = $(this);
      var sourceHeight = $(this).css('height');
      var destHeight;
      var adjustHeightOnCallback = false;
      var offset = $($sourceParent).offset();
      var offsets = [];        
      var $source = $(this).find(options.selector);
      if($.browser.msie && $.browser.version.substr(0,1)<7) {
        $sourceParent.html('').append($collection);
        return;
      }
      var postCallbackPerformed = 0;
      var postCallback = function() {
        if(!postCallbackPerformed) {
          postCallbackPerformed = 1;
          $toDelete = $sourceParent.find('> *');
          $sourceParent.prepend($dest.find('> *'));
          $toDelete.remove();
          if(adjustHeightOnCallback) {
            $sourceParent.css('height', destHeight);
          }
          options.enhancement($sourceParent);
          if(typeof callbackFunction == 'function') {
            callbackFunction.call(this);
          }                    
        }
      };
      var $correctionParent = $sourceParent.offsetParent();
      var correctionOffset = $correctionParent.offset();
      if($correctionParent.css('position') == 'relative') {
        if($correctionParent.get(0).nodeName.toLowerCase() == 'body') {

        }
        else {
          correctionOffset.top  += (parseFloat($correctionParent.css('border-top-width')) || 0);
          correctionOffset.left += (parseFloat($correctionParent.css('border-left-width')) || 0);
        }
      }
      else {
        correctionOffset.top  -= (parseFloat($correctionParent.css('border-top-width')) || 0);
        correctionOffset.left -= (parseFloat($correctionParent.css('border-left-width')) || 0);
        correctionOffset.top  -= (parseFloat($correctionParent.css('margin-top')) || 0);
        correctionOffset.left -= (parseFloat($correctionParent.css('margin-left')) || 0);
      }
      if(isNaN(correctionOffset.left)) {
        correctionOffset.left = 0;
      }
      if(isNaN(correctionOffset.top)) {
        correctionOffset.top = 0;
      }
      correctionOffset.left -= options.dx;
      correctionOffset.top  -= options.dy;
      $sourceParent.css('height', $(this).height());
      $source.each(function(i) {
        offsets[i] = $(this).offset();
      });
      $(this).stop();
      var dx = 0; var dy = 0;
      $source.each(function(i) {
        $(this).stop();
        var rawObj = $(this).get(0);
        if(rawObj.style.position == 'absolute') {
          dx = -options.dx;
          dy = -options.dy;
        }
        else {
          dx = options.dx;
          dy = options.dy;                    
        }
        rawObj.style.position = 'absolute';
        rawObj.style.margin = '0';
        rawObj.style.top = (offsets[i].top - parseFloat(rawObj.style.marginTop) - correctionOffset.top + dy) + 'px';
        rawObj.style.left = (offsets[i].left - parseFloat(rawObj.style.marginLeft) - correctionOffset.left + dx) + 'px';
      });
      var $dest = $($sourceParent).clone();
      var rawDest = $dest.get(0);
      rawDest.innerHTML = '';
      rawDest.setAttribute('id', '');
      rawDest.style.height = 'auto';
      rawDest.style.width = $sourceParent.width() + 'px';
      $dest.append($collection);      
      $dest.insertBefore($sourceParent);
      $dest.css('opacity', 0.0);
      rawDest.style.zIndex = -1;
      rawDest.style.margin = '0';
      rawDest.style.position = 'absolute';
      rawDest.style.top = offset.top - correctionOffset.top + 'px';
      rawDest.style.left = offset.left - correctionOffset.left + 'px';
      if(options.adjustHeight === 'dynamic') {
        $sourceParent.animate({height: $dest.height()}, options.duration, options.easing);
      }
      else if(options.adjustHeight === 'auto') {
        destHeight = $dest.height();
        if(parseFloat(sourceHeight) < parseFloat(destHeight)) {
          $sourceParent.css('height', destHeight);
        }
        else {
          adjustHeightOnCallback = true;
        }
      }
      $source.each(function(i) {
        var destElement = [];
        if(typeof(options.attribute) == 'function') {
          val = options.attribute($(this));
          $collection.each(function() {
            if(options.attribute(this) == val) {
              destElement = $(this);
              return false;
            }
          });
        }
        else {
          destElement = $collection.filter('[' + options.attribute + '=' + $(this).attr(options.attribute) + ']');
        }
        if(destElement.length) {
          if(!options.useScaling) {
            animationQueue.push({
              element: $(this), 
              animation: {
                top: destElement.offset().top - correctionOffset.top, 
                left: destElement.offset().left - correctionOffset.left, 
                opacity: 1.0
              }
            });
          }
          else {
            animationQueue.push({
              element: $(this), 
              animation: {
                top: destElement.offset().top - correctionOffset.top, 
                left: destElement.offset().left - correctionOffset.left, 
                opacity: 1.0, 
                scale: '1.0'
              }
            });
          }
        }
        else {
          if(!options.useScaling) {
            animationQueue.push({
              element: $(this), 
              animation: {
                opacity: '0.0'
              }
            });
          }
          else {
            animationQueue.push({
              element: $(this),
              animation: {
                opacity: '0.0', 
                scale: '0.0'
              }
            });
          }
        }
      });
      $collection.each(function(i) {
        var sourceElement = [];
        var destElement = [];
        if(typeof(options.attribute) == 'function') {
          val = options.attribute($(this));
          $source.each(function() {
            if(options.attribute(this) == val) {
              sourceElement = $(this);
              return false;
            }
          });                 
          $collection.each(function() {
            if(options.attribute(this) == val) {
              destElement = $(this);
              return false;
            }
          });
        }
        else {
          sourceElement = $source.filter('[' + options.attribute + '=' + $(this).attr(options.attribute) + ']');
          destElement = $collection.filter('[' + options.attribute + '=' + $(this).attr(options.attribute) + ']');
        }
        var animationOptions;
        if(sourceElement.length === 0) {
          if(!options.useScaling) {
            animationOptions = {
              opacity: '1.0'
            };
          }
          else {
            animationOptions = {
              opacity: '1.0',
              scale: '1.0'
            };
          }
          d = destElement.clone();
          var rawDestElement = d.get(0);
          rawDestElement.style.position = 'absolute';
          rawDestElement.style.margin = '0';
          rawDestElement.style.top = destElement.offset().top - correctionOffset.top + 'px';
          rawDestElement.style.left = destElement.offset().left - correctionOffset.left + 'px';
          d.css('opacity', 0.0);
          if(options.useScaling) {
            d.css('transform', 'scale(0.0)');
          }
          d.appendTo($sourceParent);
          animationQueue.push({
            element: $(d), 
            animation: animationOptions
          });
        }
      });
      $dest.remove();
      options.enhancement($sourceParent);
      for(i = 0; i < animationQueue.length; i++) {
        animationQueue[i].element.animate(animationQueue[i].animation, options.duration, options.easing, postCallback);
      }
    });
  };
})(jQuery);


(function($) {
  $.fn.sorted = function(customOptions) {
    var options = {
      reversed: false,
      by: function(a) { return a.text(); }
    };
    $.extend(options, customOptions);
    $data = $(this);
    arr = $data.get();
    arr.sort(function(a, b) {
      var valA = options.by($(a));
      var valB = options.by($(b));
      if (options.reversed) {
        return (valA < valB) ? 1 : (valA > valB) ? -1 : 0;				
      } else {		
        return (valA < valB) ? -1 : (valA > valB) ? 1 : 0;	
      }
    });
    return $(arr);
  };
})(jQuery);



jQuery(document).ready(function($) {

  var allCheckboxes     = $('.embedly-service-generator li input');
  var photoCheckboxes   = $('.embedly-service-generator li.photo input');
  var videoCheckboxes   = $('.embedly-service-generator li.video input');
  var richCheckboxes    = $('.embedly-service-generator li.rich input');
  var productCheckboxes = $('.embedly-service-generator li.product input');
  var audioCheckboxes   = $('.embedly-service-generator li.audio input');


  function create_provider(obj) {
    var checked = (obj.selected == 1) ? 'checked="checked"' : '';
    var li  = '<li class="'+obj.type+'" id="'+obj.name+'"><div class="full-service-wrapper"><label for="'+obj.name+'-checkbox" class="embedly-icon-name">'+obj.displayname+'</label>';
        li += '<div class="embedly-icon-wrapper"><input type="checkbox" id="'+obj.name+'-checkbox" name="'+obj.name+'" '+checked+' />';
        li += '<img src="'+obj.favicon+'" title="'+obj.name+'" alt="'+obj.displayname+'"></div></div></li>';
    return li;
  }


  $('.embedly-service-generator li').each(function() {
    $(this).find('label').each(function() {
      $(this).css({marginLeft:'-'+$(this).width()/2+'px'});
    });
    $(this).find('input:checked').each(function() {
      $(this).parents('li').addClass('service-selected');
    });
  }).click(function() {
    if($(this).find('input[type=checkbox]').attr('checked')) {
      $(this).removeClass('service-selected');
      $(this).find('input[type=checkbox]').removeAttr('checked').trigger('change');
    }
    else {
      $(this).addClass('service-selected');
      $(this).find('input[type=checkbox]').attr('checked', 'checked').trigger('change');
    }
  });


  $('.embedly-action-select a').click(function(e) {
    $('.embedly-actions').find('a').removeClass('active');
    e.preventDefault();
    var elem_class = $(this).attr('class');
    switch(elem_class) {
      case 'all':
        $(this).addClass('active');
        allCheckboxes.attr('checked', 'checked').trigger('change').parents('li').addClass('service-selected');
      break;
      case 'clearselection':
        $(this).addClass('active');
        allCheckboxes.removeAttr('checked').trigger('change').parents('li').removeClass('service-selected');
      break;
      case 'videos':
        $(this).addClass('active');
        videoCheckboxes.attr('checked', 'checked').trigger('change').parents('li').addClass('service-selected');
      break;
      case 'photos':
        $(this).addClass('active');
        photoCheckboxes.attr('checked', 'checked').trigger('change').parents('li').addClass('service-selected');
      break;
      case 'products':
        $(this).addClass('active');
        productCheckboxes.attr('checked', 'checked').trigger('change').parents('li').addClass('service-selected');
      break;
      case 'audio':
        $(this).addClass('active');
        audioCheckboxes.attr('checked', 'checked').trigger('change').parents('li').addClass('service-selected');
      break;
      case 'rich':
        $(this).addClass('active');
        richCheckboxes.attr('checked', 'checked').trigger('change').parents('li').addClass('service-selected');
      break;
    }
  });


  $('.embedly_submit').click(function(e) {
    $('#embedly-message.embedly-error').hide();
    $('#embedly-message.embedly-updated').hide();
    e.preventDefault();
    var formName = $(this).parents('form').attr('id');
    var embedly_key = $('#embedly_key').val();
    var providers = [];
    $('.embedly-service-generator li input:checked').each(function(index, elem) {
      providers.push($(elem).attr('name'));
    });
    if(formName == 'embedly_providers_form') {
      var data = {
        action:      'embedly_update',
        providers:   providers.join(','),
        embedly_key: embedly_key
      };
    }
    else if(formName == 'embedly_update_providers_form') {
      var data = {
        action: 'embedly_update_providers'
      };
    }
    jQuery.post(ajaxurl, data, function(json) {
      if(formName == 'embedly_providers_form') {
        if(json.error) {
          $('.embedly-error').fadeIn();
        }
        else {
          $('.embedly-updated').fadeIn();
        }
      }
      else if(formName == 'embedly_update_providers_form') {
        if(json.hasOwnProperty('error')) {
          $('.embedly-error').fadeIn();
        }
        else {
          if($('.embedly-service-generator').length != 1) {
            window.location.reload();
            $('.embedly-service-generator').html('');
            $.each(json, function(index, obj) {
              $('.embedly-service-generator').append(create_provider(obj));
            });
            $('.embedly-updated').fadeIn();
          }
        }
      }
    }, 'json');
  });


  /*
   * TODO: Add Quicksand filtering/sorting here
  */


});