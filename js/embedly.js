/* globals EMBEDLY_CONFIG:true, jQuery:true */
// EMBEDLY ADMIN PAGE JAVASCRIPT
// Copyright 2015 Embedly  (email : developer@embed.ly)

// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License, version 2, as
// published by the Free Software Foundation.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

// valid class prefixes for modulation of key state

// To avoid poluting the global namespace, everything should be in here.
(function($){

  // for mapping backend data to preview card data-card-* attrs
  var preview_map = {
    'card_chrome': 'data-card-chrome',
    'card_controls': 'data-card-controls',
    'card_width': 'data-card-width',
    'card_theme': 'data-card-theme',
    'card_align': 'data-card-align'
  };

  var utils = {};

  utils.comma = function(val){
    while (/(\d+)(\d{3})/.test(val.toString())){
      val = val.toString().replace(/(\d+)(\d{3})/, '$1'+','+'$2');
    }
    return val;
  };

  utils.unparam = function(param){
    var query = window.location.search.substring(1).split('&').reduce(function(obj, tuple){
      var parts = tuple.split('=');
      obj[parts[0]] = decodeURIComponent(parts[1]);
      return obj;
    }, {});

    if (param){
      return query[param];
    }
    return query;
  };


  // Returns 'YYYYMMDD' for now, or and offset in days.
  utils.date = function(days){
    var now = new Date(),
      utc = new Date(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate());

    if (days){
      utc.setDate(utc.getDate() + days);
    }

    var str = utc.getFullYear().toString();

    return str + $.map([utc.getMonth(), utc.getDate()], function(v){
      return ("00" + v).slice(-2);
    }).join('');
  };

  window.utils = utils;

  /*
  * APP
  * Connect button integration
  */
  var app = {
    _ready: false,
    _iframe: null,
    _queue: false,
    _callback: null
  };

  app.init = function () {
    window.addEventListener('message', function (e) {
      var data;
      try {
        data = window.JSON.parse(e.data);
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
      // If we set a callback earlier, use it.
      if (app._callback !== null){
        app.connect(app._callback);
      }
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
  };

  // connection code
  app.connect = function (callback) {
    app._callback = callback;

    if (app._ready === false){
      return false;
    }

    var msg = window.JSON.stringify({
      method: 'connect'
    });
    app._iframe.contentWindow.postMessage(msg, '*');
  };


  /*
  * ANALYTICS
  * Everything that has to do with getting information from Embedly's Analytics Engines.
  */
  var analytics = {};

  // loads the analytics from narrate.
  analytics.actives = function() {
    if (EMBEDLY_CONFIG.analyticsKey){
      $.getJSON('https://narrate.embed.ly/1/keys?' + $.param({
        key: EMBEDLY_CONFIG.analyticsKey
      })).then(function(response){
        $(".embedly-analytics .active-viewers .active-count").text(response.active);
      });
    }
  };

  // Number of impressions in the last week.
  // analytics.historical = function() {
  //   if (EMBEDLY_CONFIG.analyticsKey){
  //     var start = utils.date(-7),
  //       end = utils.date(1);

  //     $.getJSON('https://api.embed.ly/2/analytics/stats?' + $.param({
  //       key: EMBEDLY_CONFIG.analyticsKey,
  //       start: start,
  //       end: end
  //     })).then(function(response){
  //       var value = '-';
  //       if (response){
  //         value = response.reduce(function(sum, entry){
  //           return sum + entry.actions.load;
  //         }, 0);
  //         value = utils.comma(value);
  //       }
  //        $(".embedly-analytics .historical-viewers .weekly-count").html(value);
  //     });
  //   }
  // };

  // Start everything.
  analytics.init = function(){
    analytics.actives();
    setInterval(analytics.actives, 10000);
    // analytics.historical();
  };


  /*
  * SETTINGS
  * Everything that has to do with the saving things to the Wordpress Backend.
  */
  var settings = {};

  // given a key, value pair for a card setting, performs
  // ajax request to ajaxurl backend to update option
  settings.update = function (key, value) {
    $.post(
      EMBEDLY_CONFIG.ajaxurl,
      {
        'action': 'embedly_update_option',
        'security': EMBEDLY_CONFIG.updateOptionNonce,
        'key': key,
        'value': value
      }, function(response) {
        if(key === 'card_width') {
          // if the input was invalid for width,
          // the value will default to previous value
          value = response;
        }
        settings.preview(preview_map[key], String(value));
      });

    $('#embedly-settings-saved').show();

    // Fade out after 3 seconds.
    setTimeout(function(){
      $('#embedly-settings-saved').fadeOut();
    }, 3000);
  };

  // Build the card.
  settings.card = function() {
    if (window.embedly){
      // clone the template
      var clone = $('a.embedly-card-template').clone();
      clone.removeClass('embedly-card-template').addClass('embedly-card-preview');
      // remove the old card
      $('.card-preview-container .embedly-card').remove();
      // insert the new card template
      clone.insertAfter('a.embedly-card-template');
      // cardify it.
      window.embedly.card($('a.embedly-card-preview')[0]);
    } else {
      // when embedly loads build the card.
      window.onEmbedlyReady = function(){
        settings.card();
      };
    }
  };

  // function that updates the template card with the key value pair
  settings.preview = function(key, value){
    // update the template first
    $('a.embedly-card-template').attr(key, value);
    // then render the new card
    settings.card();
  };

  // Save the account.
  settings.save = function(api_key, analytics_key, name) {
    $.post(
      EMBEDLY_CONFIG.ajaxurl,
      {
        'action': 'embedly_save_account',
        'security': EMBEDLY_CONFIG.saveAccountNonce,
        'api_key': api_key,
        'analytics_key': analytics_key,
        'org_name': name
      },
      function(response) {
        if(response === 'true') {
          location.reload();
        } else {
          window.alert([
            'We were unable to save your Embedly information your Wordpress ',
            'install. Please email support@embed.ly and we will try to help.'].join(''));
        }

    });
  };

  // Save the account.
  settings.save_api_key = function(api_key) {
    $.post(
      EMBEDLY_CONFIG.ajaxurl,
      {
        'action': 'embedly_save_api_key',
        'security': EMBEDLY_CONFIG.saveAccountNonce,
        'api_key': api_key,
      },
      function(response) {
        input = $('#embedly-api-key')

        if(response === 'removed') {
          console.log("Successfully removed Embedly API key")
          input.attr('class', 'default-input')
        } else if(response === 'true') {
          console.log("successfully saved API key")
          input.attr('class', 'success-input')
        } else {
          input.val('')
          console.log("Invalid Embedly API Key")
          input.attr('class', 'error-input')
        }
    });
  };

  //Uses the app.connect to try to auth the user.
  settings.connect = function(callback){
    // cleans html for user select:
    // if the div is open already, close it., else continue:
    var $which = $('#embedly-which'),
      $list = $('#embedly-which-list'),
      $button = $('#connect-button');

    if($which.is(":visible")) {
      $which.hide();
      return;
    }
    // if the user clicks multiple times, make sure div is empty
    $list.empty();

    app.connect(function (data) {
      if (data.error === false) {
        if (data.organizations.length === 1) {
          // single organization. easy.
          var org = data.organizations[0];
          $button.text('CONNECTED');
          settings.save(org.api_key, org.analytics_key, org.name);
        } else {
          $which.show();

          var selected = function (org) {
            return function () {
              $button.text('CONNECTED');
              settings.save(org.api_key, org.analytics_key, org.name);
              // clear html after selection in case of reselection
              $which.hide();
              $list.empty();
            };
          };

          data.organizations.forEach(function(org){
            var $li = $('<li></li>'),
              $a = $('<a></a>');
              $a.text(org.name.toUpperCase());

            $a.on('click', selected(org));
            $li.append($a);
            $list.append($li);
          });
        }
      } else {
        // user is not currently logged in
        if (callback){
          callback({error: true});
        } else {
          window.alert("Please log in to your Embedly account first");
        }
      }
    });
  };

  settings.init = function(){
    Object.keys(preview_map).forEach(function(key) {
      // current card is set globally server side.
      // contains a map of "card_chrome" => "1" for all set options
      // if set, update the template for the initial card.
      if(EMBEDLY_CONFIG.current[key]) {
        settings.preview(preview_map[key], EMBEDLY_CONFIG.current[key]);
      }
    });

    settings.card();
  };

  $(document).ready(function($) {
    // Set up the iframe;
    app.init();

    // Set up the analytics.
    analytics.init();

    // Set up the settings.
    settings.init();

    // All the code to deal with the advanced options.
    $(".embedly-align-select-container  a").click(function(){
      $(this).parent().addClass("selected").siblings().removeClass("selected");
    });

    // When the alignment is selected, unselect other alignments
    $('.align-icon').mousedown(function() {
      $(this).children()[0].value = 'checked';
      $(this).addClass('selected-align-select');

      $.each($(this).parent().siblings(), function(name, obj) {
        var span = $(obj).children()[0];
        var hidden = $(span).children()[0];
        hidden.value = 'unchecked';
        $(span).removeClass('selected-align-select');
      });

      var align = $(this).attr('align-value');
      settings.update('card_align', align);
    });

    // minimal checkbox at the moment
    $('.chrome-card-checkbox').click(function() {
      settings.update('card_chrome', $(this).is(':checked') ? 0 : 1);
    });

    $('.embedly-social-checkbox').click(function() {
      settings.update('card_controls', $(this).is(':checked') ? 1 : 0);
    });

    $('.embedly-dark-checkbox').click(function() {
      var checked = $(this).is(':checked'),
        value = checked ? 'dark' : 'light';

      settings.update('card_theme', value);

      var $preview = $('.card-preview-container');
      if(checked) {
        $preview.addClass('dark-theme');
      } else {
        $preview.removeClass('dark-theme');
      }
    });

    $('#embedly-max-width').focusout(function() {
      settings.update('card_width', $(this).val());
    });

    $('#embedly-max-width').keypress(function(e) {
      if(e.which === 13) {
        settings.update('card_width', $(this).val());
        return false;
      }
    });

    $('#embedly-api-key').focusout(function() {
      console.log($(this).val());
      settings.save_api_key($(this).val());
    });

    $('#embedly-api-key').keypress(function(e) {
      if(e.which === 13) {
        console.log($(this).val());
        settings.save_api_key($(this).val());
        return false;
      }
    });

    // toggles dropdowns
    $('.dropdown-wrapper .dropdown-header a').click(function(){
      var $wrapper = $(this).parents('.dropdown-wrapper'),
        $body = $wrapper.find('.dropdown-body'),
        $arrow = $wrapper.find('.dropdown-header a span');

      if($body.is(":visible")) {
        $body.hide();
        $arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
      } else {
        $body.show();
        $arrow.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
      }
      return false;
    });

    // sets the back direct link on the create account button
    $('#create-account-btn').attr("href", "https://app.embed.ly/signup/wordpress?back=" + encodeURIComponent(window.location.toString()));


    // sets the back direct link for pre-existing users
    $('#preexisting-user').attr('href',
      'https://app.embed.ly/wordpress?back=' +
      encodeURIComponent(window.location.toString()));


    $('#connect-button').click(function() {
      // First try to see if we are logged in, then move away from the plugin.
      settings.connect(function(){
        $('#connect-button').html("VISITING APP.EMBED.LY...");
        window.location = [
          'https://app.embed.ly/wordpress?back=',
          encodeURIComponent(window.location.toString())
        ].join('');
      });
      return false;
    });

    // checks if page was loaded after signing in from app.embed.ly/wordpress/*
    if(utils.unparam('embedly') === 'back' && !EMBEDLY_CONFIG.analyticsKey) {
      $('#embedly-connect-failed-refresh').show();
      $('.embedly-create-account-btn-wrap').hide();
      settings.connect(function(){
        //I'm pretty sure this should fail silently.
      });
    }
  });
})(jQuery);
