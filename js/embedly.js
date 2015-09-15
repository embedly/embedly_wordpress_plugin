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

  // minimal checkbox at the moment
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
      "window.open('https://app.embed.ly/signup/wordpress?back=" + encodeURIComponent(window.location.toString()) + "');");

  // sets the back direct link for pre-existing users
  $('#preexisting-user').attr('href',
    'https://app.embed.ly/wordpress?back=' +
    encodeURIComponent(window.location.toString()));

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

    $('#embedly-settings-saved').show();
    settings_remainder = 3;
  }

  var settings_remainder = 0;
  var settings_timer = function() {
    if(settings_remainder <= 0) {
      settings_remainder = 0;
      $('#embedly-settings-saved').fadeOut();
    } else {
      settings_remainder -= 1;
    }
  };
  (function() { setInterval(settings_timer, 1000); })();


  // grab a param from the url (to determine if back from embed.ly)
  function getUrlParameter(sParam) {
    var sPageURL = decodeURIComponent(window.location.search.substring(1)),
        sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : sParameterName[1];
        }
    }
  }

  // checks if page was loaded after signing in from app.embed.ly/wordpress/*
  (function check_backdirect() {
    if(getUrlParameter('embedly') == 'back') {
      $('.embedly-create-account-btn-wrap').hide();
    }
  })();

  (function () {
    build_tutorial();
  })();

  function build_tutorial() {
    card = embedly.card($('#embedly-tutorial-card'));
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

  // sean's connect button integration
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
          button.innerHTML = 'CONNECTED';
          save_account(org.api_key, org.analytics_key, org.name);
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
              which.style.display = 'none';
              // clear html after selection in case of reselection
              whichlist.innerHTML = "";
            }
          }

          for (var i = 0; i < data.organizations.length; i++) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            org = data.organizations[i];
            a.innerHTML = org.name.toUpperCase();
            a.addEventListener('click', selected(org));
            li.appendChild(a);
            whichlist.appendChild(li);
          }
        }
      } else {
        // alert('Please log into Embedly');
        window.open('https://app.embed.ly/wordpress?back=' +
        encodeURIComponent(window.location.toString()));
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



