tinyMCEPopup.requireLangPack();

$j = jQuery.noConflict(true);

// callback handler for the embedly service lookup
var embedlyAjaxCallback = function(resp){
  EmbedlyDialog.handleResponseText(resp);
}

var EmbedlyDialog = {
  embedlyUrl : 'http://api.embed.ly/1/preview',
  key : '',
  endpoint: '',
  imageIndex : 0,
  embed : null,
  data : {},
  embedTemplate: '<div class="embedly" style="position:relative; {{style}}">{{>content}}',
  embedlyPowered: '<span class="embedly-powered" style="text-align:right;display:block"><a target="_blank" href="http://embed.ly?src=anywhere" title="Powered by Embedly"><img src="//static.embed.ly/images/logos/embedly-powered-small-light.png" alt="Embedly Powered" /></a></span>',
  templateCap: '<div class="embedly-clear"></div></div>',
  
  init : function(ed) {
    tinyMCEPopup.resizeToInnerSize();
    this.data = tinyMCEPopup.getWindowArg('data');
    this.key = this.data.key;
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
    if(typeof this.data['width'] != "undefined" && this.data['width'] != '')
      $j('#embedly_width_field').val(this.data['width']);
    if(typeof this.data['height'] != "undefined" && this.data['height'] != '')
      $j('#embedly_height_field').val(this.data['height']);
    if(typeof this.data['words'] != "undefined" && this.data['words'] != '')
      $j('#embedly_words_field').val(this.data['words']);
    
  },
  
  lookupUrl : function(e){
    var url, width, words, height;
    $j('#embedly_url_field').removeClass('error');
    url = $j('#embedly_url_field').val();
    if (url == '' || !EmbedlyDialog.urlValid(url)){
      $j('#embedly_url_field').addClass('error');
      return false;
    }
    EmbedlyDialog.data.url = url;
    
    // options
    width = $j('#embedly_width_field').val();
    if(typeof width != 'undefined' && width != '')
      EmbedlyDialog.data.width = width;
    else
      width = 500;
    words = $j('#embedly_words_field').val();
    if(typeof words != 'undefined' && words != '')
      EmbedlyDialog.data.words = words;
    else
      words = null;
      
    height = $j('#embedly_height_field').val();
    if(typeof height != "undefined" && height != '')
      EmbedlyDialog.data.height = height;
    else
      height = null;
    
    $j('#embedly_ajax_load').show();
    EmbedlyDialog.embedlyUrl = EmbedlyDialog.endpoint == 'preview' ? 'http://api.embed.ly/1/preview' : 'http://api.embed.ly/1/oembed'
    EmbedlyDialog.ajax('get', EmbedlyDialog.embedlyUrl, 
      {key:EmbedlyDialog.key, url:escape(url), words:words, width:width, height: height });
  },
  
  ajax : function(method, url, params) {
    var xmlhttp;
    url += "?";
    for(var i in params){
      if(params[i])
        url += i+'='+params[i] +'&';
    }
    url+= 'callback=embedlyAjaxCallback';
    
    (function(d,t){var g=d.createElement(t),s=d.getElementsByTagName(t)[0]; 
      g.async=1;g.src=url;s.parentNode.insertBefore(g,s); }(document,'script'))
  },
  
  urlValid : function(url){
    var regexp = /(http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/
    return regexp.test(url);
  },
  
  handleResponseText : function(resp){
    var pr, code, title, style, data;
    EmbedlyDialog.embed = resp;
    data = EmbedlyDialog.data;
    if(EmbedlyDialog.endpoint == 'preview'){
      
      pr = '<div id="embedly-preview">';
      if(resp.images.length > 0){
        pr += '<div class="embedly-images"><div class="embedly-image-scroll">';
        for(image in resp.images)
          pr += '<div class="image"><img src="'+resp.images[image].url+'"/></div>';
        pr += '<p>Select an image to use with your embed.</p><a href="#" class="button secondary images-prev">&lt;</a><a href="#" class="button secondary images-next">&gt;</a>';
        pr += '</div>';
        pr += '</div>'; // /embedly-images div
      }
      pr += '<div class="embedly-content">';
      if(resp.title)
        pr += '<p><strong>Title:</strong> '+resp.title+'</p>';
      if(resp.description)
        pr += '<p><strong>Description:</strong> '+resp.description+'</p>';
      pr += '</div>'; // /embedly-content
      pr += '</div>'; // /embedly-preview
      
    } else {
      pr = EmbedlyDialog.generateOembed(resp);
    }
    
    $j('#embedly_ajax_load').hide();
    $j('#embedly_form_lookup').hide();
    $j('#embedly_form_submit').show().focus();
    $j('#embedly-preview-results').html(pr);
    $j('.embedly-images .images-prev').bind('click', EmbedlyDialog.imagePrev);
    $j('.embedly-images .images-next').bind('click', EmbedlyDialog.imageNext);
    $j('.embedly-images').find('.image').eq(0).addClass('selected');
  },
  imageNext: function(e){
    e.preventDefault();
    var im = $j('.embedly-images ').find('.image');
    EmbedlyDialog.imageIndex++;
    if(EmbedlyDialog.imageIndex >= im.length){
      EmbedlyDialog.imageIndex = 0;
    }
    $j(im).removeClass('selected').eq(EmbedlyDialog.imageIndex).addClass('selected');
    $j(this).blur();
  },
  imagePrev: function(e){
    e.preventDefault();
    var im = $j('.embedly-images ').find('.image');
    EmbedlyDialog.imageIndex--;
    if(EmbedlyDialog.imageIndex < 0){
      EmbedlyDialog.imageIndex = im.length-1;
    }
    $j(im).removeClass('selected').eq(EmbedlyDialog.imageIndex).addClass('selected');
    $j(this).blur();
  },
  
  generateOembed: function(resp){
    var title,style, pr, code;
    data = EmbedlyDialog.data;
    title = resp.title || data.url;
    style = '';
    if(typeof data.width != "undefined" && data.width != '')
      style += 'max-width:'+data.width+'px';
    if(typeof data.height != "undefined" && data.height != '')
      style += 'max-height:'+data.height+'px';
    
    // show a preview of what oEmbed returns, no image editing
    if (resp.type === 'photo'){
      code = '<a href="'+data.url+'" target="_blank"><img style="width:100%" src="'+resp.url+'" title="'+title+'" /></a>';
    } else if (resp.type === 'video'){
      code = resp.html;
    } else if (resp.type === 'rich'){
      code = resp.html;
    } else {
      thumb = resp.thumbnail_url ? '<img src="'+resp.thumbnail_url+'" class="thumb" />' : '';
      description = resp.description;
      code = thumb+"<a href='" + data.url + "'>" + title + "</a>";
      code += description;
    }
    // Wrap the embed in our class for manipulation
    pr = '<div class="embedly" style="'+style+'">'+code;
    pr += '<div style="float:left;"><span>via </span><a href="'+resp.provider_url+'" class="media-attribution-link" target="_blank">'+resp.provider_name+'</a></span></div>'
    pr += EmbedlyDialog.embedlyPowered;
    pr += '<div class="embedly-clear"></div></div>';
    return pr;
  },
    
  generateEmbed: function(preview){
    var tpl = EmbedlyDialog.embedTemplate, view, content;
    if(this.endpoint == 'oembed')
      tpl += EmbedlyDialog.embedlyPowered;
    tpl += EmbedlyDialog.templateCap;
    
    view = EmbedlyDialog.mustacheView(EmbedlyDialog.embed);
    content = EmbedlyDialog.mustacheDisplay(EmbedlyDialog.embed);
    return Mustache.to_html(tpl, view, {'content' : content});
  },
  
  mustacheDisplay: function(preview){
    // display the appropriate Mustache template based on preview type
    // or object type
    if (preview.safe != true) {
      return '<div class="embedly-unsafe"><span class="embedly-title">     \
        <img src="http://static.embed.ly/images/anywhere/alert.png" />     \
        <a onclick="if(!confirm(\'We believe this URL is unsafe. Do you    \
        want to continue?\')) { return false; }" target="_blank"           \
        href="{{url}}">potential '+preview.safe_type+'</a></span>          \
        <p>{{{safe_message}}}<p></div>';
    }
    if (preview.type == 'image'){
      return '<a href="{{original_url}}" class="embedly-thumbnail">        \
      <img style="width:100%" src="{{url}}" /></a>';
    } else if (preview.type == 'video'){
      return '<video src="{{url}}" controls="controls" preload="preload"   \
      class="embedly-video"></video>';
    } else if (preview.type == 'audio'){
      return '<audio src="{{url}}" controls="controls" preload="preload"   \
      class="embedly-video"></audio>';
    } else if (preview.content != null){
      return '<span class="embedly-title"><a target="_blank"               \
      href="{{url}}">{{title}}</a></span><p>{{{content}}}<p>';
    } else if (preview.object.type == 'photo'){
      return '<a href="{{original_url}}" class="embedly-thumbnail">        \
      <img src="{{object_url}}" /></a>';
    } else if (preview.object.type == 'video'){
      return '{{{object_html}}}';
    } else if (preview.object.type == 'rich'){
      return '{{{object_html}}}';
    } else if (preview.type == 'html'){
      var r = '';
      if (preview.images.length != 0) {
        if (preview.images[EmbedlyDialog.imageIndex].width >= EmbedlyDialog.data.width ) {
          r += '<a target="_blank" href="{{original_url}}" title="{{url}}" \
            class="embedly-thumbnail"><img style="width:100%" src="{{thumbnail_url}}"/></a>';
        } else {
          r += '<a target="_blank" href="{{original_url}}" title="{{url}}" \
            class="embedly-thumbnail-small" style="width:50%"> \
            <img src="{{thumbnail_url}}"/></a>';
        }
      }
      r += '<a class="embedly-title" target="_blank" \
        href="{{original_url}}" title="{{url}}">{{title}}</a> \
        <p>{{description}}<p>';

      if (preview.embeds.length != 0)
         r += '{{{embed_html}}}';
      return r;
    } else {
      return ''; 
    }
  },
  mustacheView: function(preview){
    // Map the Preview object to a Mustache View
    var content = preview, style = '';

    if (preview.object) {
      var _t = content['object_type'] = preview.object.type
      if (_t == 'photo'){
        content['object_url'] = preview.object.url;
      } else if (_t == 'video' || _t == 'rich'){
        content['object_html'] = preview.object.html;
      }
      content['object_width'] = preview.object.width;
      content['object_height'] = preview.object.height;
    } else {
      content.object = null;
    }
    
    if(EmbedlyDialog.width)
      style += 'max-width:'+EmbedlyDialog.width+'px;';
    if(EmbedlyDialog.height)
      style += 'max-height:'+EmbedlyDialog.height+'px';

    if (preview.images.length != 0) {
      content['thumbnail_url'] = preview.images[EmbedlyDialog.imageIndex].url;
      content['thumbnail_width'] = preview.images[EmbedlyDialog.imageIndex].width;
      content['thumbnail_height'] = preview.images[EmbedlyDialog.imageIndex].height;
    } else { 
      content['thumbnail_url'] = null;
      content['thumbnail_width'] = null;
      content['thumbnail_height'] = null;
    }
    if (preview.embeds.length != 0) {
      content['embed_html'] = preview.embeds[0].html;
    } else {
      content['embed_html'] = null;
    }
    var view = {
        'favicon_url' : preview.favicon_url,
        'provider_url' : preview.provider_url,
        'provider_name' : preview.provider_name,
        'author_name' : preview.author_name,
        'author_url' : preview.author_url,
        'content' : content,
        'safe' : preview.safe,
        'style' : style,
        'safe_message' : preview.safe_message ? preview.safe_message : '',
        'favicon' : function(){
          if (this.favicon_url == null){
              return false; 
          }
          return true;
        },
        'author' : function(){
          if (this.author_url == null || this.author_name == null){
              return false;
          }
          return true;
        }
    }

    return view;
  },
  
  cancel: function(){
    tinyMCEPopup.close()
  },
  insert : function(file, title) {
    if(EmbedlyDialog.data.endpoint == 'oembed'){
      EmbedlyDialog.data.embed = this.generateOembed(EmbedlyDialog.embed);
    } else{
      EmbedlyDialog.data.embed = this.generateEmbed(EmbedlyDialog.embed);
    }
    var ed = tinyMCEPopup.editor, dom = ed.dom;
    ed.execCommand('mceRepaint');
    tinyMCEPopup.restoreSelection();
    ed.selection.setNode(ed.plugins.embedly.dataToImg(this.data));
                      
    /*ed.execCommand('mceRepaint');
    tinyMCEPopup.restoreSelection();
    ed.selection.setNode(dom.createHTML('div', {
      class: 'embedly-container',
      style: 'display:block;max-width:'+EmbedlyDialog.width+'px'
    }, display));*/
    
    tinyMCEPopup.close();
  }
};

tinyMCEPopup.onInit.add(EmbedlyDialog.init, EmbedlyDialog);
