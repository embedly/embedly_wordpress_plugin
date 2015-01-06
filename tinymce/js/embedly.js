tinyMCEPopup.requireLangPack();

$j = jQuery;

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

    //Handle URL
    $j('#embedly_url_field').removeClass('error');
    var url = $j('#embedly_url_field').val();
    
    //Trim off the whitespace.
    url = $j.trim(url);
    
    //Escape whitespace
    url = url.replace(/ /g, '%20');

    // Make sure we have a protocol.
    if (!(/^https?:\/\//i).test(url)){
      url = 'http://' + url;
    }

    //Validate URL
    if (url == '' || !EmbedlyDialog.urlValid(url)){
      $j('#embedly_url_field').addClass('error');
      return false;
    }

    EmbedlyDialog.data.url = url;
    var $jcard = $j('.generator-card'); 
    var $jopts = $j('.generator-inputs');

    // clear card
    $j('#card').empty();

    // add embedly a tag w/ url
    var a = document.createElement('a');
    a.href = url;
    $j('#card').append(a);

    // this lets us do the setting of things.
    var frame = embedly.card(a);
    $jsnippet = $j('#snippet');

    // create the embed code when the snippet changes.
    frame.on('card.snippet', function(data){
      $jsnippet.val(data.snippet);//+script);
      $jsnippet.select();
    });

    // create the embed code when the snippet changes.
    frame.on('card.rendered', function(data){
      frame.send('card.snippet');
      frame.send('card.freeze');
      frame.send('card.edit');

      // if it's editable, not chromeless.
      if (data.isEditable === true){
        $j('#card-chromeless').parent().parent().hide();
      }
      else {
        $j('#card-chromeless').parent().parent().show();
      }
    });

    $j('#card-background').attr('onclick','').unbind('click');
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

    $j('#card-chromeless').attr('onclick','').unbind('click');
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

//Set Dialog title.
$j(document).ready(function() {
    document.title = 'Embedly';
});

