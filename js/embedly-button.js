(function() {
    tinymce.PluginManager.add('embedly_embed_button', function( editor, url ) {
        editor.addButton( 'embedly_embed_button', {

            text: false,
            icon: 'icon embedly-button-icon',
            inline: 1,
            onclick: function() {
              editor.windowManager.open( {
                  // can put our own html form here for more customization later:
                  // instead of built in box will load an html page.
                  // url: 'dialog.html',
                  width: 600,
                  height: 200,
                  resizable: false,
                  title: 'embed a link',
                  // put all the options we want in here, build an a tag later.
                  body: [{
                      type: 'textbox',
                      name: 'href',
                      label: 'link:',
                  },
                  {
                      type: 'button',
                      name: 'preview_btn',
                      label: false,
                      text: 'preview card',
                      onclick: function( e ) {
                          editor.windowManager.open({
                              width: 600,
                              height: 375,
                             url: '../wp-content/plugins/embedly_wordpress_plugin/js/dialog.html'
                         }, {card: 'hellomyfriend'}); // trying to get data to file somehow for card
                          console.log('clicked button')
                      }
                  },
                  {
                      type: 'checkbox',
                      checked: false,
                      name: 'chromeless',
                      label: 'chromeless',
                  },
                  {
                      type: 'checkbox',
                      checked: true,
                      name: 'share',
                      label: 'shareable',
                  }],
                  onrepaint: function( e ) {
                      // can sometimes do things here.. not reliable though
                      // only called ~ 2 times randomly?
                  },
                  execAjax: function( e ) {
                      var data = {
                          'action': 'embedly_get_api_key',
                      }
                      var key = '';

                      jQuery.post(ajaxurl, data, function(response) {
                          console.log('querying ajax')
                          key = response
                          console.log(response)
                      }).done(
                          console.log('done')
                      )
                  },
                  onsubmit: function( e ) {
                      var data = {
                          'action': 'embedly_get_api_key',
                      }
                      var key = '';

                      // I don't know jQuery that well, there's probably
                      // a better way to organize this code so that it's not
                      // all in the callback from post.
                      jQuery.post(ajaxurl, data, function(response) {
                          console.log('querying ajax')
                          key = response
                          console.log(response)

                          if (key == '') {
                              // warn user about Key
                              console.log('no key')
                          }
                          if (e.data.href == '') {
                            return
                          }
                          if (e.data.share) {
                            var share_value = '1'
                          } else {
                            share_value = '0'
                          }
                          if (e.data.chromeless) {
                            chrome_value = '0'
                          } else {
                            chrome_value = '1'
                          }
                          card_key = ' data-card-key="' + key + '"'
                          card_pre = '<a class="embedly-card"'
                          card_href = ' href="' + e.data.href + '"'
                          card_key = ' data-card-key="' + key + '"'
                          card_chrome = ' data-card-chrome="' + chrome_value + '"'
                          card_share = ' data-card-controls="' + share_value + '"'
                          card_post = '></a>'
                          card = card_pre + card_href + card_chrome + card_share + card_key + card_post
                          console.log(card)
                          editor.insertContent(card);
                      });
                  }
              });
          }
        });
    });
})();
