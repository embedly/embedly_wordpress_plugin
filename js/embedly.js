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

jQuery(document).ready(function($) {

  // NEW STUFF:
  $(".embedly-align-select-container  a").click(function(){
    $(this).parent().addClass("selected").siblings().removeClass("selected");
  });

  // loads the analytics from narrate immediately,
  // and then every N milliseconds
  (function load_actives() {
    $.post(
      ajaxurl,
      {'action': 'embedly_analytics_active_viewers'},
      function(response) {
        var response = JSON.parse(response);
        $(".embedly-analytics .active-viewers .active-count").html(response.active);
    });

    setTimeout(load_actives, 10000);
  })();

  // When the alignment is selected, unselect other alignments
  $('.align-icon').mousedown(function(e) {

    $(this).children()[0].value = 'checked';
    $(this).addClass('selected-align-select');

    $.each($(this).parent().siblings(), function(name, obj) {
      var span = $(obj).children()[0];
      var hidden = $(span).children()[0];
      hidden.value = 'unchecked';
      $(span).removeClass('selected-align-select');
    });

    var align = $(this).attr('align-value');
    console.log(align);
    update_option('card_align', align);

  });

  // immediate settings
  $('.embedly-minimal-checkbox').click(function() {
    update_option('card_chrome', $(this).is(':checked') ? 0 : 1);
  });

  $('.embedly-social-checkbox').click(function() {
    update_option('card_controls', $(this).is(':checked') ? 1 : 0);
  });

  $('.embedly-dark-checkbox').click(function() {
    update_option('card_theme', $(this).is(':checked') ? 'dark' : 'light');
  });

  $('.embedly-max-width').focusout(function(e) {
    update_option('card_width', $(this).val());
  });

  $('.embedly-max-width').keypress(function(e) {
    if(e.which == 13) {
      update_option('card_width', $(this).val());
      return false;
    }
  });

  // toggles advanced options
  $('.advanced-wrapper .advanced-header').find('a[href="#"]').click(function(e) {
    e.preventDefault();
    $advanced = $('.advanced-wrapper .advanced-body');
    $arrow = $('.embedly-dropdown');

    if($advanced.is(":visible")) {
      $advanced.hide();
      $arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
    } else {
      $advanced.show();
      $arrow.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2')
    }
    return false;
  });

  // given a key, value pair for a card setting, performs
  // ajax request to ajaxurl backend to update option
  function update_option(key, value) {
    console.log("updating: " + key + ": " + value);
    $.post(
      ajaxurl,
      {
        'action': 'embedly_update_option',
        'key': key,
        'value': value,
      }, function(response) {
        console.log(response);
      });
  }

  function key_test(to_test) {
    $.post(ajaxurl, {
      'action': 'embedly_key_input',
      'key': to_test
    },  function(response) {
      if(response == 'false') {
        invalid_key();
      } else {
        valid_key();
        setTimeout(function() {
            lock_key()
          }, 5000);
      }
    });
  }

  // handle 'return' events inside key input field.
  $('#embedly_key_test').keypress(function(e) {
    var attr = $(this).attr('readonly');
    // For some browsers, `attr` is undefined; for others,
    // `attr` is false.  Check for both.
    if (typeof attr !== typeof undefined && attr !== false) {
      // the field is readonly.
      return
    } else if (e.which == 13) {
      key_test($(this).val());
    }
  });

  // also support on focus out for key input
  $('#embedly_key_test').focusout(function(e) {
    var attr = $(this).attr('readonly');
    if (typeof attr == typeof undefined && attr == false) {
      // the field is NOT readonly, do the test
      key_test($(this).val());
    }
  });

  // valid class prefixes for modulation of key state
  var valid_states = [
    'invalid',
    'valid',
    'locked',
    'unlocked',
    'lock-control',
  ];

  (function() {
    // clears any notifications that exist on load
    clear_notifications();
  })();

  // clears all notification text
  function clear_notifications() {
    valid_states.forEach(function(state) {
      $('.' + state + '-outer-text').hide(); // notif. text
    });
  }

  // clears all embedly-api-key-input-container states
  function clear_states() {
    valid_states.forEach(function (state) {
      $('.embedly-api-key-input-container').removeClass(state + '_key');
    });
  }

  function lock_key() {
    clear_states();
    clear_notifications();
    $('#embedly_key_test').prop('readonly', true).parent().addClass('locked_key');

    valid_states.forEach(function(item) {
      $('.key-icon').removeClass(item + '-key-icon').addClass('locked-key-icon');
    });
  }

  function unlock_key() {
    clear_states();
    clear_notifications();
    $('#embedly_key_test').prop('readonly', false).parent().addClass('unlocked_key');

    valid_states.forEach(function(item) {
      $('.key-icon').removeClass(item + '-key-icon').addClass('unlocked-key-icon');
    });
  }

  function valid_key() {
    // set valid key
    // changes the color of the input box
    clear_states();
    $('#embedly_key_test').parent().addClass('valid_key');

    clear_notifications();
    $('.valid-outer-text').show(); // show the notification text

    valid_states.forEach(function(item) {
      $('.key-icon').removeClass(item + '-key-icon').addClass('valid-key-icon');
    });
  }

  function invalid_key() {
    // set invalid key
    clear_states();
    $('#embedly_key_test').parent().addClass('invalid_key');
    // $('#embedly_key_test').removeClass('valid_key').addClass('invalid_key');
    valid_states.forEach(function(item) {
      $('.key-icon').removeClass(item + '-key-icon').addClass('invalid-key-icon');
    });
    clear_notifications();
    $('.invalid-outer-text').show();
  }

  // action handlers for lock icon click
  $('.lock-control-key-icon').click(function(e) {
    e.preventDefault();
    if($(this).hasClass('locked-key-icon')) {
      unlock_key();
      // $(this).removeClass('locked-key-icon').addClass('unlocked-key-icon').parent().removeClass('locked_key').addClass('unlocked_key');
    } else if ($(this).hasClass('unlocked-key-icon')) {
      lock_key();
      // $(this).removeClass('unlocked-key-icon').addClass('locked-key-icon').parent().removeClass('unlocked_key').addClass('locked_key');
    }
  });

// END NEW STUFF

  if($('#embedly_key').attr('readonly')) {
    $('.embedly-lock-control').removeClass('embedly-unlocked').addClass('embedly-locked');
  }
  else {
    $('.embedly-lock-control').removeClass('embedly-locked').addClass('embedly-unlocked');
  }
  $('.embedly-lock-control').click(function(e) {
    e.preventDefault();
    if($(this).hasClass('embedly-locked')) {
      $(this).removeClass('embedly-locked').addClass('embedly-unlocked').siblings('#embedly_key').removeClass('embedly-locked-input').removeAttr('readonly');
    }
    else {
      $(this).removeClass('embedly-unlocked').addClass('embedly-locked').siblings('#embedly_key').addClass('embedly-locked-input').attr('readonly', 'readonly');
    }
  }).hover(function() {
    if($(this).hasClass('embedly-locked')) {
      $(this).attr('title', $(this).attr('data-locked'));
    }
    else {
      $(this).attr('title', $(this).attr('data-unlocked'));
    }
  }, function() {
    $(this).attr('title', '');
  });

});
