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
// valid class prefixes for modulation of key state
var valid_states = [
  'invalid',
  'valid',
  'locked',
  'unlocked',
  'lock-control',
];

// for mapping backend data to preview card data-card-* attrs
var preview_map = {
  'card_chrome': 'data-card-chrome',
  'card_controls': 'data-card-controls',
  'card_width': 'data-card-width',
  'card_theme': 'data-card-theme',
  'card_align': 'data-card-align',
}

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

  // forces first render of preview card.
  // with current settings
  (function() {
    update_preview('data');
    build_card();
  })();

  (function load_historical() {
    $.post(
      ajaxurl,
      {'action': 'embedly_analytics_historical_viewers'},
      function(response) {
        var times = JSON.parse(response);
        if(times["err"]) {
          impr = "No Analytics";
        } else {
          var impr = 0;
          times.forEach(function(item) {
            impr += item.actions.load;
          });
        }
        $(".embedly-analytics .historical-viewers .weekly-count").html(add_commas(impr));
      });
  })();

  function add_commas(val){
    while (/(\d+)(\d{3})/.test(val.toString())){
      val = val.toString().replace(/(\d+)(\d{3})/, '$1'+','+'$2');
    }
    return val;
  }

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
    update_option('card_align', align);
  });

  $('.chrome-card-checkbox').click(function() {
    update_option('card_chrome', $(this).is(':checked') ? 0 : 1);
  });

  $('.embedly-social-checkbox').click(function() {
    update_option('card_controls', $(this).is(':checked') ? 1 : 0);
    simulate_hover_on_preview_card();
  });

  $('.embedly-dark-checkbox').click(function() {
    var checked = $(this).is(':checked');
    value = checked ? 'dark' : 'light';
    update_option('card_theme', value);

    var $preview = $('.card-preview-container');
    if(checked) {
      $preview.addClass('dark-theme');
    } else {
      $preview.removeClass('dark-theme');
    }
  });

  $('.embedly-max-width').focusout(function(e) {
    valid_width = update_option('card_width', $(this).val());
  });

  $('.embedly-max-width').keypress(function(e) {
    if(e.which == 13) {
      valid_width = update_option('card_width', $(this).val());
      console.log('valid width: ' + valid_width);
      return false;
    }
  });

  function simulate_hover_on_preview_card() {
    // not sure if this is possible into the iframe..
    // or if worth. maybe just explain with a tooltip
    // what the social setting does.
    $('embedly-card').addClass('hover');
    setTimeout($('embedly-card').removeClass('hover'), 3000);
  }

  // toggles advanced options
  $('.advanced-wrapper .advanced-header').find('a[href="#"]').click(function(e) {
    e.preventDefault();
    $advanced = $('.advanced-wrapper .advanced-body');
    $arrow = $('#advanced-arrow');

    if($advanced.is(":visible")) {
      $advanced.hide();
      $arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
    } else {
      $advanced.show();
      $arrow.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2')
    }
    return false;
  });

  // toggles tutorial
  $('.tutorial-wrapper .tutorial-header').find('a[href="#"]').click(function(e) {
    e.preventDefault();
    $tutorial = $('.tutorial-wrapper .tutorial-body');
    $arrow = $('#tutorial-arrow');

    if($tutorial.is(":visible")) {
      console.log("hiding");
      $tutorial.hide();
      $arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
    } else {
      console.log('showing');
      $tutorial.show();
      $arrow.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2')
    }
    return false;
  });

  // sets the back direct link on the create account button
  $('#create-account-btn')
    .attr(
      "onclick",
      "window.open('https://app.embed.ly/signup/wordpress?back=" + window.location.toString() + "');");

  // given a key, value pair for a card setting, performs
  // ajax request to ajaxurl backend to update option
  function update_option(key, value) {
    $.post(
      ajaxurl,
      {
        'action': 'embedly_update_option',
        'key': key,
        'value': value,
      }, function(response) {
        console.log(response);
        if( key == 'card_width' ) {
          // if the input was invalid for width,
          // the value will default to previous value
          value = response;
        }
        update_preview(preview_map[key], String(value));
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
    var attr = $(this).prop('readonly');
    if (typeof attr !== typeof undefined && attr !== false) {
      // the field is readonly.
      return
    } else if (e.which == 13) {
      e.preventDefault();
      key_test($(this).val());
    }
  });

  // also support on focus out for key input
  $('#embedly_key_test').focusout(function(e) {
    var attr = $(this).prop('readonly');
    if (typeof attr != typeof undefined && attr == false) {
      // the field is NOT readonly, do the test
      key_test($(this).val());
    }
  });

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

    clear_notifications();
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
    } else if ($(this).hasClass('unlocked-key-icon')) {
      lock_key();
    }
  }).hover(function() {
      console.log('hovering..')
      if($(this).hasClass('locked-key-icon')) {
        $(this).attr('title', $(this).attr('data-locked'));
      }
      else {
        $(this).attr('title', $(this).attr('data-unlocked'));
      }
    }, function() {
      $(this).attr('title', '');
    });

  (function () {
    build_tutorial();
  })();

  function build_tutorial() {
    console.log('building tutorial');
    // $('.tutorial-body .embedly-tutorial-container .embedly-card').remove();
    console.log('link is: ' + $('#embedly-tutorial-card'));
    card = embedly.card($('#embedly-tutorial-card'));
    console.log('card is: ' + card);
  };

  function build_card() {
    // clone the template
    clone = $('a.embedly-card-template').clone();
    clone.removeClass('embedly-card-template').addClass('embedly-card-preview');
    // remove the old card
    $('.card-preview-container .embedly-card').remove();
    // insert the new card template
    clone.insertAfter('a.embedly-card-template')[0];
    // cardify it.
    card = embedly.card($('a.embedly-card-preview')[0]);
  }

  // function that updates the template card with the key value pair
  function update_preview(key, value) {
    // update the template first
    $template = $('a.embedly-card-template').attr(key, value);
    // then render the new card
    build_card();
  }

  (function initialize_preview() {
    Object.keys(preview_map).forEach(function(key) {
      // current card is set globally server side.
      // contains a map of "card_chrome" => "1" for all set options
      // if set, update the template for the initial card.
      if(current_card[key]) {
        update_preview(preview_map[key], current_card[key]);
      }
    });
    // when done, build it.
    build_card();
  })();

  // sean's connect button, integration
  var app = {
    _ready: false,
    _iframe: null,
    _callbacks: []
  };

  app.init = function () {
    window.addEventListener('message', function (e) {
      try {
        data = JSON.parse(e.data);
      } catch (err) {
        return false;
      }
      if (!data) {
        return false;
      }
      if (data.method === 'connect' && data.context === 'embedly-app') {
        app.message(data);
      }
    });

    var iframe = document.createElement('iframe');
    app._iframe = iframe;

    iframe.addEventListener('load', function () {
      app._ready = true;
      app.connect();
    });

    iframe.frameborder = '0';
    iframe.style.width = '1px';
    iframe.style.border = 'none';
    iframe.style.position = 'absolute';
    iframe.style.top = '-9999em';
    iframe.style.width = '10px';
    iframe.style.height = '10px';
    iframe.src = 'https://app.embed.ly/api/connect';
    document.body.appendChild(iframe);
  };

  app.message = function (data) {
    if (app._callback) {
      app._callback.call(window, data);
    }
  }

  // connection code
  app.connect = function (callback) {
    app._callback = callback;
    msg = JSON.stringify({
      method: 'connect'
    });
    app._iframe.contentWindow.postMessage(msg, '*');
  };

  app.init();

  var button = document.getElementById('connect-button');

  button.addEventListener('click', function () {
    // if the div is open already, close it., else continue:
    if($('#embedly-which').is(":visible")) {
      $('#embedly-which').hide();
      return;
    }

    // if the user clicks twice, make sure div is empty
    $('#embedly-which-list').empty();

    console.log('click');
    app.connect(function (data) {
      console.log(data);
      if (data.error === false) {

        if (data.organizations.length === 1) {
          var org = data.organizations[0]
          button.innerHTML = 'connected';
          save_account(org.api_key, org.analytics_key, org.name);
          // alert('connected ' + org.name + ' ' + org.api_key + ' ' + org.analytics_key);
        } else {
          // selects the div containing accounts to connect
          var which = document.getElementById('embedly-which');
          var whichlist = document.getElementById('embedly-which-list');
          which.style.display = 'block';
          console.log("whichlist is: " + whichlist)

          var selected = function (org) {
            return function () {
              button.innerHTML = 'connected';
              save_account(org.api_key, org.analytics_key, org.name);
              // alert('connected ' + org.name + ' ' + org.api_key + ' ' + org.analytics_key);
              which.style.display = 'none';
              // clear html after selection in case of reselection
              whichlist.innerHTML = "";
            }
          }

          for (var i = 0; i < data.organizations.length; i++) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            org = data.organizations[i];
            a.innerHTML = org.name;
            a.addEventListener('click', selected(org));
            li.appendChild(a);
            whichlist.appendChild(li);
            // which.appendChild(whichlist);
          }
        }
      } else {
        alert('Please log into Embedly');
      }
    });
  });

  function save_account(api_key, analytics_key, name) {
    $.post(
      ajaxurl,
      {
        'action': 'embedly_save_account',
        'api_key': api_key,
        'analytics_key': analytics_key,
        'org_name': name,
      },
      function(response) {
        if(response == 'true') {
          location.reload();
        }
        // should warn user if something went wrong here..
        console.log(response);
    });
  }
});



