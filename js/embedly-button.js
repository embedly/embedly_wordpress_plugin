(function() {
    tinymce.PluginManager.add('embedly_embed_button', function(editor, url) {
        editor.addButton('embedly_embed_button', {

            text: false,
            icon: 'icon embedly-button-icon',
            inline: 1,
            onclick: function() {
                editor.windowManager.open({
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
                    }, {
                        type: 'button',
                        name: 'preview_btn',
                        label: false,
                        text: 'preview card',

                        onclick: function(  ) {
                            // Hopefully not too hacky->
                            // this.parent() returns a ref. to the body of the dialog
                            // it's items are the inputs ('checkbox', etc)
                            // their values are how we build the card.
                            card_items = this.parent().items();
                            href = card_items[0].items()[1].value()
                            // checkbox is 'chromeless' so if checked -> '0'
                            chrome = card_items[2].items()[1].checked() ? '0' : '1'
                            share = card_items[3].items()[1].checked() ? '1' : '0'

                            editor.windowManager.open({
                                width: 800,
                                height: 500,
                                url: '../wp-content/plugins/embedly_wordpress_plugin/js/dialog.html'
                            }, {
                                card: {
                                    controls: share,
                                    href: href,
                                    chrome: chrome,
                                }
                            }); // trying to get data to file somehow for card
                        }

                    }, {
                        type: 'checkbox',
                        checked: false,
                        name: 'chromeless',
                        label: 'chromeless',
                    }, {
                        type: 'checkbox',
                        checked: true,
                        name: 'share',
                        label: 'shareable',
                    }],

                    buildcard: function( e ) {
                        console.log(e.data.href)
                    },

                    onsubmit: function(e) {
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

                            share_value = e.data.share ? '1' : '0'
                            chrome_value = e.data.chromeless ? '0' : '1'

                            // eventually get a nicer placeholder?
                            card_pre = '&nbsp;<blockquote class="embedly-card"><h7>' + e.data.href + '<a ';
                            card_key = ' data-card-key="' + key + '"';
                            card_href = ' href="' + e.data.href + '"';
                            card_chrome = ' data-card-chrome="' + chrome_value + '"';
                            card_share = ' data-card-controls="' + share_value + '"';
                            card_post = '</a></h7><h4>embedly card</h4></blockquote>&nbsp;';

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
