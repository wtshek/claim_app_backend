/*****
 * Author: Patrick Yeung <patrick{at}avalade{dot}com>
 *****/
var autoPagination = function(selector, options){
    this.init(selector, options);
};
(function($, fn){
    var classes = {
        'toRemove': 'toRemove',
        'fItem': 'fItem',
        'sItem': 'sItem'
    };
    
    fn.prototype = {
        init: function(selector, options) {
            var me = this;
            //options reserved for future use
            this.root = $(selector);
            this.parent = this.root.parent();
            this.max_height = this.parent.parent().height(); //exclude padding, borders and margins
            this.content_height = this.root.outerHeight(); // exclude margins
            this.currentPage = 0;
            this.mode = this.root.children('.' + classes.fItem).length > 0 ? 1 : this.root.children('.' + classes.sItem).length > 0 ? 2 : 0;
            this.transitionTime = 2000;
            this.pageContent = [];
            this.maxChildCount = 0;
            
            var siblings = this.parent.siblings('h1');
            var siblingsHeight = 0;
            
            for(var i = 0; i < siblings.length; i++) {
                siblingsHeight += $(this.parent.siblings('h1')[i]).outerHeight(true);
            }
            
            this.max_height -= siblingsHeight;
            
            /*
            this.totalPages = Math.ceil(this.content_height / this.max_height);
            
            if(this.totalPages > 1) {
                this.pagination_setup();
            }
            */
            
            eval("me.pagination_setup_" + this.mode + ".apply(me);");
            
            if(this.totalPages > 0) {
                this.parent.parent().append('<div class="pagination"><a href="#" alt="Prev" class="prev">Previous Page</a><a href="#" alt="Next" class="next">Next Page</a></div>');
                this.prevBtn = this.parent.parent().find('.pagination .prev').css('display', 'none');
                this.nextBtn = this.parent.parent().find('.pagination .next');
                
                this.nextBtn.bind('click', function(e){me.nextPage(e)});
                this.prevBtn.bind('click', function(e){me.prevPage(e)});
                
                this.gotoPage(0);
            }
        },
        pagination_setup_0: function(){
            var me = this;
            this.totalPages = Math.ceil(this.content_height / this.max_height);
            
            //this.max_height -= (this.parent.outerHeight(true)-this.parent.outerHeight())+20;
            //this.totalPages = Math.ceil(this.content_height / this.max_height)
            this.parent.css('height', this.max_height);
            
            this.currentPage = 0;
        },
        pagination_setup_1: function(itemClass) {
            if(itemClass == undefined || itemClass == "" || itemClass == null) {
                itemClass = classes.fItem;
            }
            
            // for multiple columns as a page
            var me = this;
            
            var rootWidth = this.root.innerWidth();
            var children = this.root.children('.' + itemClass);
            var width = null;
            var pageAry = -1;
            
            for(var i = 0; children[i] != undefined && children[i] != null; i++) {
                var tmpWidth = children[i].scrollWidth;
                
                if(width == null || width+tmpWidth > rootWidth) {
                    width = 0;
                    pageAry++;
                }
                
                width += tmpWidth;
                
                if(this.pageContent[pageAry] == undefined)
                    this.pageContent[pageAry] = [];
                
                this.pageContent[pageAry].push(children[i]);
                this.maxChildCount = Math.max(this.pageContent[pageAry].length, this.maxChildCount);
            }
            
            children.wrapAll('<div style="display: none;"></div>');
            
            this.totalPages = this.pageContent.length;
        },
        pagination_setup_2: function() {
            this.root.css({'overflow': 'hidden'});
            this.root.parent().css({'overflow': 'hidden'});
            this.pagination_setup_1(classes.sItem);
        },
        nextPage: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            this.gotoPage(this.currentPage+1);
        },
        prevPage: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            this.gotoPage(this.currentPage-1);
        },
        gotoPage: function(p) {
            var me = this;
            var targetPage = p < 0 ? (this.totalPages - 1) : (p % this.totalPages);
            
            this.nextBtn.css('display', targetPage+1 == this.totalPages ? 'none' : 'block');
            this.prevBtn.css('display', targetPage == 0 ? 'none' : 'block');
            
            //this.root.css('margin-top', this.currentPage*this.max_height*-1);
            
            switch(this.mode) {
                case 0:
                    this.root.css('margin-top', this.currentPage*this.max_height*-1);
                    break;
                case 1:
                    var toDim = this.root.children('.fItemsList:not(.' + classes.toRemove + ')');
                    
                    if(toDim.length > 0) {
                        var v = 2;
                        var pTimeDelay = this.transitionTime / toDim.length / v;
                        var subTransitionTime = this.transitionTime - (pTimeDelay * (toDim.length - 1)); // signle piece transition time
                        
                        for(var i = 0; i < toDim.length; i++) {
                            this.qFade(0, $(toDim[i]), subTransitionTime, pTimeDelay * i, v);
                        }
                        toDim.addClass(classes.toRemove);
                    }
                    
                    var newEl = $(this.pageContent[targetPage]).clone().wrapAll('<div class="fItemsList"></div>').parent();
                    this.root.append(newEl);
                    this.qFade(1, newEl, this.transitionTime, toDim.length > 0 ? this.transitionTime / 3 : 0, 2);
                    
                    break;
                case 2:
                    var toDim = this.root.stop().children('.sItemsList').stop().width(this.root.parent().width());
                    var factor = this.currentPage > targetPage ? 1 : -1;
                    var totalWidth = 0;
                    
                    if(toDim.length > 0) {
                        var t = toDim[0];
                        
                        for(var i = 0; i < toDim.length && i < targetPage; i++) {
                            totalWidth += toDim[i].scrollWidth;
                        }
                        
                        this.root.animate({'margin-left': totalWidth * -1}, {'duration': this.transitionTime, 'complete': function() {
                            //me.root.css({'width': 'auto', 'margin-left': 0}).children('.sItemsList.'+classes.toRemove).remove();
                        }});
                    }
                    
                    if(factor < 0) { // add content
                        var newEl = $(this.pageContent[targetPage]).clone().wrapAll('<div class="sItemsList"></div>').parent().width(this.root.parent().width());
                        this.root.append(newEl);
                        this.root.css({'width': totalWidth + newEl[0].scrollWidth});
                    }
                    
                    break;
                default:
                    break;
            }
            
            this.currentPage = targetPage;
        },
        'qFade': function(mode, target, transitionTime, delay, v) {
            if(v == undefined || v < 1)
                v = 1;
            var innerChildren = target.children();
            var innerDelay = transitionTime / this.maxChildCount / v;
            var subTime2 = transitionTime - (innerDelay * (this.maxChildCount - 1));
            
            var properties = {};
            switch(mode) {
                case 1: // fade in
                    innerChildren.css({'opacity': 0});
                    properties.opacity = 1;
                    break;
                case 0: // fade out
                default:
                    target.css({'position': 'relative'});
                    var tH = target.context.scrollHeight;
                    target.css({'margin-top': -tH, 'top': tH});
                    
                    properties.opacity = 0;
                    break;
            }
            
            var s = new Date().getTime();
            
            for(var x = 0; x < innerChildren.length; x++) {
                (function(a,b,c){
                    /* a reverse method (stop after delay)
                    setTimeout(function(){
                        $(a).stop(true).animate(properties, {'duration': c});
                    }, b);
                    */
                    $(a).stop(true).delay(b).animate(properties, {'duration': c});
                })(innerChildren[x], innerDelay * x + delay, subTime2);
            }
            
            if(mode == 0){
                (function(a, b){setTimeout(function(){a.remove();}, b);})($(target), transitionTime+delay);
            }
        }
    }
})(jQuery, autoPagination);