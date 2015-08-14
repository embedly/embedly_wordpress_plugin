(function() {
    // tinymce.ScriptLoader.load('../wp-content/plugins/embedly_wordpress_plugin/js/embedly-platform.js');
    tinymce.PluginManager.add('embedly_embed_button', function( editor, url ) {
        editor.addButton( 'embedly_embed_button', {

            text: false,
            icon: 'icon embedly-button-icon',
            inline: 1,
            onclick: function() {
              editor.windowManager.open( {
                  // can put our own html form here for more customization later
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
                          console.log('clicked button')
                          console.log(editor.windowManager.getParams().key) // testing params
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
                  onsubmit: function( e ) {
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
                      card_pre = '<a class="embedly-card"'
                      card_href = ' href="' + e.data.href + '"'
                      card_chrome = ' data-card-chrome="' + chrome_value + '"'
                      card_share = ' data-card-controls="' + share_value + '"'
                      card_post = '></a>'
                      card = card_pre + card_href + card_chrome + card_share + card_post
                      console.log(card)
                      editor.insertContent(card);
                  }
              }, {
                key: 1 // this is how we can try to pass data to the window
              });
          }
        });
    });
})();
