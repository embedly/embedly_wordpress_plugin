(function(){
  var JSON = tinymce.util.JSON, Node = tinymce.html.Node;
  
  function toArray(obj){
    var un, arr, i;
    if(obj && !obj.splice){
      arr = [];
      for(i in obj){
        if(obj[i])
          arr[i] = obj[i];
      }
      return arr;
    }
    return obj;
  };
  
  tinymce.create('tinymce.plugins.EmbedlyPlugin', {
    init: function(ed, url){
      var self = this;
      
      function isEmbedlyImg(node){
        return node && node.nodeName === 'IMG' && ed.dom.hasClass(node, 'mceItemEmbedly');
      }
      
      self.editor = ed;
      self.url = url;
      self.key = embedly_key;
      self.endpoint = embedly_endpoint;
      
      ed.onPreInit.add(function(){
        // in case embeds have videos or audio tags
        ed.schema.addValidElements('object[id|style|width|height|classid|codebase|*],param[name|value],embed[id|style|width|height|type|src|*],video[*],audio[*],source[*]');
        
        // convert our embed to an Image tag for manipulation
        ed.parser.addNodeFilter('div,span,p', function(nodes){
          var i = nodes.length;
          while(i--){
            if(ed.dom.hasClass(nodes[i], 'mceItemEmbedly'))
              self.embedToImg(nodes[i]);
          }
        });
        
        ed.serializer.addNodeFilter('img', function(nodes, name, args){
          var i = nodes.length, node;
          while(i--){
            node = nodes[i];
            if ((node.attr('class') || '').indexOf('mceItemEmbedly') !== -1)
              self.imgToEmbed(node, args);
          }
        });
      });
      
      ed.onInit.add(function(){
        // add embedly css file to TinyMCE editor window
        ed.contentCSS.push('../jscripts/tiny_mce/plugins/embedly/embedly_editor.css');
        
        // Display "embedly" instead of "img" in element path
        if (ed.theme && ed.theme.onResolveName) {
          ed.theme.onResolveName.add(function(theme, path_object){
            if(path_object.name === 'img' && ed.dom.hasClass(path_object.node, 'mceItemEmbedly'))
              path_object.name = 'embedly';
          });
        }
        
        // context menu
        if(ed && ed.plugins.contextmenu){
          ed.plugins.contextmenu.onContextMenu.add(function(plugin, menu, element) {
            if (element.nodName === 'IMG' && element.className.indexOf('mceItemEmbedly') !== -1)
              menu.add({title: 'embedly.edit', icon : 'embedly', cmd : 'mceEmbedly'});
          });
        }
        
      });
      
      ed.addCommand('mceEmbedly', function(){
        var data, img;
        // data should follow format:
        // { url: 'url to embed', width: 500, height:500, words: 250, thumbnail: 0, embed: 'html' }
        img = ed.selection.getNode();
        if(isEmbedlyImg(img)) {
          data = ed.dom.getAttrib(img, 'data-mce-json');
          if(data){
            data = JSON.parse(data);
          }
        } if(!data){
          data = {
          }
        }
        
        data['key'] = self.key;
        data['endpoint'] = self.endpoint
        
        ed.windowManager.open({
          file : url + '/embed.htm',
          width : 500 + parseInt(ed.getLang('embedly.delta_width', 0)),
          height: 450 + parseInt(ed.getLang('embedly.delta_height', 0)),
          inline:1
        }, {
          plugin_url:url,
          data:data
        });
      });
          
      // Register Buttons
      ed.addButton('embedly', 
                     {title : 'embedly.embedly_desc', 
                      image : '../jscripts/tiny_mce/plugins/embedly/img/icon.gif',
                        cmd : 'mceEmbedly'});
          
      ed.onNodeChange.add(function(ed, cm, node){
        cm.setActive('embedly', isEmbedlyImg(node));
      });  
    },
    
    getInfo: function(){
      return {
        longname : 'Embedly',
        author : 'Embed.ly, Inc.',
        authorurl: 'http://embed.ly',
        infourl: 'http://embed.ly/docs/libraries',
        version: tinymce.majorVersion + '.' + tinymce.minorVersion
      };
    },
    
    /*
     * Convert JSON data object to an img node
     */
    dataToImg: function(data){
      var self = this, editor = self.editor, settings = editor.settings, img;
      
      img = self.editor.dom.create('img', {
                     id : data.id,
                  style : data.style,
                  align : data.align || 'left',
                    src : editor.theme.url + '/img/trans.gif',
                'class' : 'mceItemEmbedly',
        'data-mce-json' : JSON.serialize(data, "'")
      });
      img.width = data.width || 320;
      img.height = data.height || 240;
      
      return img;
    },
    
    dataToHtml : function(data) {
      return this.editor.serializer.serialize(this.dataToImg(data), {forced_root_block : ''});
    },
    
    htmlToData : function(html) {
      var fragment, img, imgs, data;
      fragment = this.editor.parser.parse(html);
      imgs = fragment.getAll('img');
      for(img in imgs){
        data = JSON.parse(img.attr('data-mce-json'));
        if(data)
          return data;
      }
      return false;
    },
    
    imgToEmbed : function(node, args) {
      var self = this, editor = self.editor, embed, data;
      data = node.attr('data-mce-json');
      if (!data)
        return;
      data = JSON.parse(data);
      
      style = node.attr('data-mce-style');
      if (!style) {
        style = node.attr('style');
        if (style)
          style = editor.dom.serializeStyle(editor.dom.parseStyle(style, 'img'));
      }
      if(data.embed){
        embed = new Node('div', 1);
        value = new Node('#text', 3);
        value.raw = true;
        value.value = data.embed;
        embed.append(value);
      }
      if(embed)
        node.replace(embed);
      else
        node.remove();
    },
    
    embedToImg : function(node) {
      var embed, img, width, height, style, words, url, data;
      function getInnerHTML(node) {
        return new tinymce.html.Serializer({
          inner: true,
          validate: false
        }).serialize(node);
      };
      
      // if node isn't in the document
      if (!node.parent)
        return;
        
      data = data || {
        url: null,
        width: null,
        height: null,
        words: null,
        embed: null,
        thumbnail: 0
      }
      
      img = new Node('img', 1);
      img.attr({
        src : this.editor.theme.url + '/img/trans.gif'
      });
      
      id = embed.attr('id');
      style = embed.attr('style');
      
      img.attr({
				//id : id,
				'class' : 'mceItemEmbedly',
				//style : style,
				width : data.width || "320",
				height : data.height || "240",
				"data-mce-json" : JSON.serialize(data, "'")
			});
      
    }
    
    
  });
  tinymce.PluginManager.add('embedly', tinymce.plugins.EmbedlyPlugin);
})();