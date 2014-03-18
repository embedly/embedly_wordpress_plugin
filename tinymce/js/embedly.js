tinyMCEPopup.requireLangPack();

$j = jQuery.noConflict(true);

// callback handler for the embedly service lookup
var embedlyAjaxCallback = function(resp){
  EmbedlyDialog.handleResponseText(resp);
}

var EmbedlyDialog = {
  key : '',
  endpoint: '',
  data : {},
  
  init : function(ed) {
    tinyMCEPopup.resizeToInnerSize();
    this.data = tinyMCEPopup.getWindowArg('data');
    this.key = this.data.key;
    this.endpoint = this.data.endpoint ? this.data.endpoint : 'oembed';
    if(typeof this.key == "undefined" || this.key == '' ){
      $j('#embedly_main').hide();
      $j('#embedly_error').show();
    } else {
      this.endpoint = this.data.endpoint;
      this.dataToForm();
      $j('#embedly_url_field').focus();
      $j('#embedly_form_lookup').bind('click', this.lookupUrl);
    }
  },
  
  dataToForm: function(){
    if(typeof this.data['url'] != "undefined" && this.data['url'] != '')
      $j('#embedly_url_field').val(this.data['url']);
  },
  
  lookupUrl : function(e){
    e.preventDefault();
    $j('#embedly_url_field').removeClass('error');
    url = $j('#embedly_url_field').val();
    if (url == '' || !EmbedlyDialog.urlValid(url)){
      $j('#embedly_url_field').addClass('error');
      return false;
    }
    EmbedlyDialog.data.url = url;
    params = {};
   
    var script = ["\n<",
    "script>!function(a){var b=\"embedly-platform\",c=\"script\";",
    "if(!a.getElementById(b)){var d=a.createElement(c);",
    "d.id=b,d.src=(\"https:\"===document.location.protocol",
    "?\"https\":\"http\")+\"://cdn.embedly.com/widgets/platform.js\";",
    "var e=document.getElementsByTagName(c)[0];e.parentNode.insertBefore(d,e)}}(document);<",
    "/script>"].join('');

    var $jcard = $j('.generator-card'); 
    
    // clear card
    $j('#card').empty();  

    // add url
    var a = document.createElement('a');
    a.href = url;
    a.setAttribute('class', 'embedly-card');
    $j('#card').append(a);

    // this lets us do the setting of things.
    var frame = embedly.card(a);
    $jsnippet = $j('#snippet');

    //Create the embed code when the snippet changes.
    frame.on('card.snippet', function(data){
      $jsnippet.val(data.snippet+script);
      $jsnippet.select();
    });

    //Create the embed code when the snippet changes.
    frame.on('card.rendered', function(data){
      frame.send('card.snippet');
      frame.send('card.freeze');
      frame.send('card.edit');

      // if it's editable, it can't be chromeless.
      if (data.isEditable === true){
        $j('#chromeless').hide();
      }
    });

    $j('#card-background').on('click', function(){
      var theme;
      if ( $j(this).prop('checked') ){
        theme = 'dark';
        $jcard.addClass('dark');
      } else {
        theme = 'light';
        $jcard.removeClass('dark');
      }
      frame.send('card.set', {
        key: 'theme',
        value: theme
      });
    });

    $j('#card-chromeless').on('click', function(){
      var chrome;
      if ( $j(this).prop('checked') ){
        chrome = 0;
      } else {
        chrome = 1;
      }
      frame.send('card.set', {
        key: 'chrome',
        value: chrome
      });
    });

    $j('#embedly_form_submit').attr('disabled', false).removeClass('disabled').focus();
    return false;
  },

  urlValid : function(url){
    var regexp = /(http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/
    return regexp.test(url);
  },

  generateEmbed: function(data){
    return $j('#snippet').val();
  },

  cancel: function(){
    tinyMCEPopup.close()
  },

  insert : function(file, title) {
    EmbedlyDialog.data.embed = EmbedlyDialog.generateEmbed(EmbedlyDialog.data);
    delete EmbedlyDialog.data['key'];
    delete EmbedlyDialog.data['endpoint'];
    var ed = tinyMCEPopup.editor, dom = ed.dom;
    ed.execCommand('mceRepaint');
    tinyMCEPopup.restoreSelection();
    ed.selection.setNode(ed.plugins.embedly.dataToImg(EmbedlyDialog.data));
    
    tinyMCEPopup.close();
  }
};

tinyMCEPopup.onInit.add(EmbedlyDialog.init, EmbedlyDialog);
