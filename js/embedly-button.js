(function() {
    // tinymce.ScriptLoader.load('../wp-content/plugins/embedly_wordpress_plugin/js/embedly-platform.js');
    tinymce.PluginManager.add('embedly_embed_button', function( editor, url ) {
        editor.addButton( 'embedly_embed_button', {
            text: false,
            icon: 'icon embedly-button-icon',
            onclick: function() {
              editor.windowManager.open( {
                  title: 'Give us a link to embed',
                  // put all the options we want in here, build an a tag later.
                  body: [{
                      type: 'textbox',
                      name: 'href',
                      label: 'link'
                  }],
                  onsubmit: function( e ) {
                      card = '<a class="embedly-card" href="' + e.data.href + '"></a>'
                      editor.insertContent(card + script);
                  }
              });
          }
        });
    });
})();
