var clickToUrl = function(selector, options){
    this.init(selector, options);
};

(function($, fn){
  fn.prototype = {
    init: function(selector, options) {
        var me = this;
        this.items = $(selector);
        this.selector = selector;
        this.itemsVars = [];
        this.setRel();
        
        if(this.items && this.items != '') {
            this.items.css('cursor', 'pointer');
            this.items.each(function(i, el){
                $(el).data('targetUrl', me.itemsVars[i].url);
            });
            
            this.items.click(function(e){
                e.preventDefault();
                e.stopPropagation();
                if($(e.target).closest(me.selector).data('targetUrl')) {
                    window.location.href = $(e.target).closest(me.selector).data('targetUrl');
                }
            });
        }
    },
    setRel: function(){
        var me = this;
        
        this.items.each(function(i, el){
            if($(el).attr('rel')) {
                var t = $(el).attr('rel').split(';');
                var attrs = {};
                for(var j = 0; j < t.length; j++) {
                    if(t[j]) {
                        var temp = t[j].split(':');
                        if(temp.length == 2)
                            attrs[temp[0]] = temp[1];
                        else if(temp.length > 2) {
                            var name = temp.shift();
                            var value = temp.join(':');
                            attrs[name] = value;
                        }
                    }
                }
                me.itemsVars[i] = attrs;
            }
        });
    }
    
  }
  
})(jQuery, clickToUrl);