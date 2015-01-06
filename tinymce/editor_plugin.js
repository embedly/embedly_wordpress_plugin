function embedly(){
  return "[embedly]";
}

(function(){
  var JSON = tinymce.util.JSON, Node = tinymce.html.Node, s = tinymce.settings, t = this;
  function embedlyError(e){ console.warn("Embedly Plugin: %s", e.message); }
  try{
    // Load plugin specific language pack
    tinymce.PluginManager.requireLangPack('embedly');

    //TinyMCE
    tinymce.create('tinymce.plugins.embedly', {
      init: function(ed, url){
        var self = this;
        self.editor = t.editor = ed, self.schema = new tinymce.html.Schema(s);
        function isEmbedlyImg(node){ return node && node.nodeName === 'IMG' && ed.dom.hasClass(node, 'mceItemEmbedly');}
      
        ed.parser = ed.parser || new tinymce.html.DomParser(s, self.schema);
      
        self.url = url;
        try {
          if(typeof embedly_key != "undefined")
            self.key = embedly_key;
          else
            self.key = '';
          self.endpoint = typeof embedly_endpoint != "undefined" ? embedly_endpoint : 'oembed';
        } catch(e){
          embedlyError(e);
        }
        ed.onPreInit.add(function(){
          // in case embeds have videos or audio tags
          // convert our embed to an Image tag for manipulation
          try {
            self.schema.addValidElements('div[id|class|*]');
            ed.parser = ed.parser || new tinymce.html.DomParser(s, self.schema);
            ed.parser.addNodeFilter('div', function(nodes, name){
              var i = nodes.length;
              while(i--){
                if(nodes[i].attr('data-ajax'))
                  self.embedToImg(nodes[i]);
              }
            });
            ed.serializer = ed.serializer || new tinymce.dom.Serializer(s, ed.dom, self.schema);
            ed.serializer.addNodeFilter('img', function(nodes, name, args){
              var i = nodes.length, node;
              while(i--){
                node = nodes[i];
                if ((node.attr('class') || '').indexOf('mceItemEmbedly') !== -1)
                  self.imgToEmbed(node, args);
              }
            });
          } catch(e){
            embedlyError(e);
          }
        });
      
      
      
        ed.onInit.add(function(){
          // add embedly css file to TinyMCE editor window
          try{
            ed.dom.loadCSS(EMBEDLY_TINYMCE+'/css/embedly_editor.css');
            // Display "embedly" instead of "img" in element path
            if (ed.theme && ed.theme.onResolveName) {
              ed.theme.onResolveName.add(function(theme, path_object){
                if(path_object.name === 'img' && ed.dom.hasClass(path_object.node, 'mceItemEmbedly'))
                  path_object.name = 'embedly';
              });
            }
          } catch(e){
            embedlyError(e);
          }
        
          // context menu
          if(ed && ed.plugins.contextmenu){
            ed.plugins.contextmenu.onContextMenu.add(function(plugin, menu, element) {
              try{
                if (element.nodName === 'IMG' && element.className.indexOf('mceItemEmbedly') !== -1)
                  menu.add({title: 'embedly.edit', icon : 'embedly', cmd : 'mceEmbedly'});
              } catch(e){
                embedlyError(e);
              }
            });
          }
        
        });
      
        ed.addCommand('mceEmbedly', function(){
          var data, img;
          // data should follow format:
          // { url: 'url to embed', width: 500, height:500, words: 250, thumbnail: 0, embed: 'html' }
          img = ed.selection.getNode();
          try{
            if(isEmbedlyImg(img)) {
              data = ed.dom.getAttrib(img, 'data-ajax');
              if(data){
                data = JSON.parse(data);
              }
            } 
          } catch(e){
            embedlyError(e);
          }
          if(!data){
            data = {};
          }
        
          data['key'] = self.key;
          data['endpoint'] = self.endpoint
        
          ed.windowManager.open({
            file : url + '/../dialog.php',
            width : 500 + parseInt(ed.getLang('embedly.delta_width', 0)),
            height: 450 + parseInt(ed.getLang('embedly.delta_height', 0)),
            title: 'Embedly',
            inline:1
          }, {
            plugin_url:url,
            data:data
          });
        });
          
        // Register Buttons
        ed.addButton('embedly', 
                       {title : 'Embedly', 
                        image : EMBEDLY_TINYMCE+'/img/icon.gif',
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
        try{
          img = self.editor.dom.create('img', {
                         id : data.id,
                      style : data.style,
                      align : data.align || 'left',
                        src : EMBEDLY_TINYMCE + '/img/trans.gif',
                    'class' : 'mceItemEmbedly',
                'data-ajax' : JSON.serialize(data, "'")
          });
          img.width = data.width || 600;
          img.height = data.height || 600;
        } catch(e){
          embedlyError(e);
        }
        return img;
      },
    
      dataToHtml : function(data) {
        return this.editor.serializer.serialize(this.dataToImg(data), {forced_root_block : ''});
      },
    
      htmlToData : function(html) {
        var fragment, img, imgs, data;
        try{
          fragment = this.editor.parser.parse(html);
          imgs = fragment.getAll('img');
          for(img in imgs){
            data = JSON.parse(img.attr('data-ajax'));
            if(data)
              return data;
          }
        } catch(e){
          embedlyError(e);
        }
        return false;
      },
    
      imgToEmbed : function(node, args) {
        var self = this, editor = self.editor, embed, data;
        var data = node.attr('data-ajax');
        if (!data)
          return;
        data = JSON.parse(data);
        try{      
          if(data.embed){
            var ser = JSON.serialize(data, "'");
            embed = new Node('div', 1);
            value = new Node('#text', 3);
            value.raw = true;
            value.value = data.embed;
            embed.append(value);
            embed.attr('data-ajax', ser);
          }
          if(embed)
            node.replace(embed);
          else
            node.remove();
        } catch(e){
          embedlyError(e);
        }
      },
    
      embedToImg : function(node) {
        var embed, img, width, height, style, words, url, data, getInnerHTML;
        getInnerHTML = function(node) {
          return new tinymce.html.Serializer({
            inner: true,
            validate: false
          }).serialize(node);
        };
      
        // if node isn't in the document
        if (!node.parent)
          return;
        
        if ( node.attr('data-ajax') != ''){
          data = node.attr('data-ajax');
        } else {
          return;
        }
        try {  
          data = JSON.parse(data) || {
            url: null,
            width: null,
            height: null,
            words: null,
            embed: null,
            thumbnail: 0
          };
      
          img = new Node('img', 1);
          img.attr({
            src : EMBEDLY_TINYMCE + '/img/trans.gif'
          });
      
          node.replace(img);
      
          id = node.attr('id');
          style = node.attr('style');
      
          img.attr({
            id : id,
            'class' : 'mceItemEmbedly',
            style : style,
            width : data.width || "600",
            height : data.height || "600",
            "data-ajax" : JSON.serialize(data, "'")
          }); 
        } catch(e){
          embedlyError(e);
        }
      }
    });
    tinymce.PluginManager.add('embedly', tinymce.plugins.embedly);
  } catch(e) { embedlyError(e); }
})();