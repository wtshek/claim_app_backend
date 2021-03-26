/**
 * File:
 * Author: Patrick Yeung
 * Email: <patrick{at}avalade{dot}com>
 * Date: 13年10月9日
 * Time: 下午2:26
 *
 */
var avAnimate = {};

(function($){
    var bootFn = function(fn, me, vars) {
        if($.type(fn) == 'function') {
            if(me == undefined) {
                me = this;
            }
            if(vars == undefined) {
                vars = [];
            }

            this.init(fn, me, vars);
        }
    };
    bootFn.prototype = {
        'init': function(fn, me, vars) {
            this.me = me;
            this.fn = fn;
            this.vars = vars;
        },
        'execute': function(){
            this.fn.apply(this.me, this.vars);
            this.destroy();
        },
        'destroy': function() {
            try {
                this.me = this.fn = this.vars = null;
            } catch(e) {
                return false;
            }

            return true;
        }
    }

    var bootStack = (function() {

        var fn = function() {
            this.stack = [];
            this.ready = false;

            var me = this;
            this.triggerFn = function() {
                me.trigger();
            };

            $(document).bind('ready', this.triggerFn);
        }

        fn.prototype = {
            'trigger': function() {
                this.ready = true;

                for(var i = 0; i < this.stack.length; i++) {
                    this.stack[i].execute();
                }

                $(document).unbind('ready', this.triggerFn);
            },
            'add': function(fn, me, vars) {
                if($.type(fn) == 'function') {
                    var bFn = new bootFn(fn, me, vars);
                } else {
                    var bFn = fn;
                }

                if(this.ready) {
                    bFn.execute();
                } else {
                    this.stack.push(bFn);
                }
            }
        };

        return new fn();
    })();

    var _topDown = function(root, marginTop, opacity) {
        if(root) {
            var me = this;
            this.initHeight = 0;
            this.marginTop = 0;
            this.opacity = 0;

            bootStack.add(new bootFn(this.init, me, [root, marginTop, opacity]));
        }
    };

    _topDown.prototype = {
        'init': function(root, marginTop, opacity) {
            this.root = root;
            this.initHeight = root.innerHeight();
            // init margin top to make it hidden
            this.marginTop = marginTop;
            if(marginTop == undefined) {
                this.marginTop = this.initHeight;
            }

            this.opacity = opacity;

            if(this.opacity == undefined) {
                this.opacity = 1;
            }

            this.root.css({
                'position': 'relative'
            });

            this.top(-this.marginTop);
        },
        'top': function(height) {
            this.root.css({
                'margin-top': height,
                'opacity': this.opacity
            });
        },
        'animate': function(t) {
            this.root.addClass('started').animate({
                'margin-top': 0,
                'opacity': 1
            }, {
                'duration': t
            });
        }
    };

    avAnimate.topDown = function(root) {
        if(root) {
            var children = root.children();
            var fns = [];
            var trigger = function() {
                var delay = 0;
                var gapTimer = -300;

                for(var i = 0; i < children.length; i++) {
                    var ts = i == 0 ? 800 : 500;
                    var fn = fns[i];

                    setTimeout((function(fn) {
                        var t = ts;
                        return (function() { fn.animate(t) });
                    })(fn), delay);
                    delay += ts + gapTimer;
                }
            };

            for( var i = 0; i < children.length; i++ ) {
                var child = $(children[i]);

                if(i == 0) {
                    fns.push(new _topDown(child));
                } else {
                    var c2 = $(children[i-1]);
                    var pos = c2.position();
                    var lastTop = c2.innerHeight();

                    var pos2 = child.position();
                    var thisTop = pos2.top;

                    fns.push(new _topDown(child, thisTop + child.innerHeight(), 0));
                }
            }

            root.addClass('inited');
            bootStack.add(trigger, this);
        }
    };

    /** not yet finalized */
    avAnimate.stickyContent = function(item, to, pos, minTop, maxTop, inclusive) {
        var initPos = pos;
        var initScrollTop = $(window).scrollTop();
        var initWHeight = $(window).height();
        var currentScrollTop = initScrollTop;
        var lastChangeTop = 0;

        var position = function(pos) {
            item.css({
                'top': pos
            });
        }

        item.css({
            'top': 0,
            'bottom': 'auto'
        });

        var resize = function() {
            var m = item.parent().innerHeight() - item.innerHeight() - 24;
            var scrollTop = $(window).scrollTop() - initScrollTop;

            var t = $(window).height() - initWHeight + initPos + scrollTop;

            t = Math.min(t, m);
            if(maxTop != null && maxTop) {
                t = Math.min(maxTop);
            }

            var mT = minTop - 10;
            if(inclusive)
                mT -= item.innerHeight();

            t = Math.max(mT, t);

            //window.console.dir($(window).height() - initWHeight + initPos + scrollTop + ', ' + m + ', ' + minTop);

            position(t);
        }

        $(window).resize(function() {
            lastChangeTop = 0;

            resize();
        });

        $(window).scroll(function(e) {
            var tmp = currentScrollTop;
            currentScrollTop = $(window).scrollTop();

            if(currentScrollTop > tmp && lastChangeTop < currentScrollTop) {
                lastChangeTop = currentScrollTop;
                resize();
            }
        });

        resize();
    };

    avAnimate.adaptiveBg = function(img, img_mobile, root, relatedContents, fade, siblings, minContents) {
        var image = null;
        var loader = new avaImgLoader();
        var container = $('<div class="main-bg"></div>');
        var duration = 1500;
		var slider_duration = 4000;
        var maxWidth = 2560;
        var maxHeight = 1440;
        var baseImgWidth = 0;
        var baseImgHeight = 0;
        var siblingsHeight = 0;
        var minHeight = 0;
		var slider_type = 'desktop';
        avAnimate.adaptiveBg.performResize = true;

        // the image and window size ratio - find the minimum one
        var Delta = 0;
        if(relatedContents == undefined) {
            relatedContents = [];
        }

        if(fade == undefined) {
            fade = true;
        }

        if(siblings == undefined || siblings == null)
            siblings = root.siblings();

        root.addClass('loading');

        // function declarations
        var rz = function() {
            minHeight = 0;
            for(var i = 0; i < minContents.length; i++) {
                minHeight += $(minContents[i]).outerHeight(true);
            }

            var contents = [root[0], image.obj[0]];
            for(var i = 0; i < relatedContents.length; i++) {
                contents.push(relatedContents[i][0]);
            }

            if($('html').width()>463)
				var contentWidth = Math.min(Math.max($('html').width(), 990), maxWidth);
            else
				var contentWidth = Math.min(Math.max($('html').width(), 320), maxWidth);
            var contentHeight = Math.min(Math.max($('html').height()-siblingsHeight, 825-siblingsHeight, minHeight), maxHeight);
            //root.width(contentWidth);
            //root.height(contentHeight);

            for(var i = 0; i < relatedContents.length; i++) {
                relatedContents[i].height(contentHeight);
            }
			
			// set baseimage width and height
			var reset_image;
			if($('html').width()>463 || img_mobile.length==0)
				reset_image = loader.imgs[0];
			else
				reset_image = loader.imgs[img.length];
			
			Delta = Math.max(reset_image.dimension.width/maxWidth, reset_image.dimension.height/maxHeight);
			baseImgWidth = reset_image.dimension.width/Delta;
			baseImgHeight = reset_image.dimension.height/Delta;
			
			var extra_top = 0;
			if($('html').width()>463)
			{
				root.css('min-height', contentHeight);
				if($('.main').hasClass('about_iprestige'))
					$('.content_wrap').height(contentHeight-$('#header').height()-20);
				//else 
					//$('.content_wrap').height(contentHeight.height()-20);
				//window.console.dir($(".main_body").height());
				if(contentWidth/baseImgWidth > 0.4)
				{
					var ratio = Math.max(contentWidth/baseImgWidth, contentHeight/baseImgHeight);
					var margin_ratio = 2;
				}
				else
				{
					var ratio = parseFloat(contentWidth/baseImgWidth+contentHeight/baseImgHeight)/2;
					var margin_ratio = 1.75;
				}
				if($('html').width()<=768)
					var margin_top_ratio = 1.9;
				else
					var margin_top_ratio = 2.4;
			}
			else
			{
				var ratio = contentWidth/baseImgWidth;
				extra_top = $('#header').height();
				root.css('min-height', parseFloat(reset_image.dimension.height*(contentWidth/reset_image.dimension.width)+extra_top));
				var margin_top_ratio = 2;
				var margin_ratio = 2;
				$('.content_wrap').height('auto');
			}
		
			if(container.children().size() == img.length || container.children().size() == img_mobile.length)
			{
				if($('html').width()>463 && container.hasClass('is_mobile_slider'))
				{
					avBgSlider(container, 'desktop', slider_duration, fade, false);
					container.removeClass('is_mobile_slider');
				}
				else if($('html').width()<=463 && !container.hasClass('is_mobile_slider'))
				{
					avBgSlider(container, 'mobile', slider_duration, fade, false);
					container.addClass('is_mobile_slider');
				}
					
				for(var i=0; i<img.length; i++)
				{
					container.find('img').each(function(index){
						$(this).css({
							'width': Math.ceil(baseImgWidth * ratio),
							'height': Math.ceil(baseImgHeight * ratio),
							'max-width': 'none'
							}).css({
									'margin-left': -$(this).width() / margin_ratio,
									'margin-top': parseFloat(-$(this).height() / margin_top_ratio+extra_top / margin_top_ratio)
								});
					});
					/*$('#bg_slider, #bg_slider_mobile').find('img').each(function(index){
						$(this).css({
							'width': Math.ceil(baseImgWidth * ratio),
							'height': Math.ceil(baseImgHeight * ratio),
							'max-width': 'none'
							}).css({
									'margin-left': -$(this).width() / margin_ratio,
									'margin-top': -$(this).height() / 2
								});
					});*/
				}
			}
			else
			{
				if($('html').width()>463 && container.hasClass('is_mobile_slider'))
				{
					avBgSlider(container, 'desktop', slider_duration, fade, false);
					container.removeClass('is_mobile_slider');
				}
				else if($('html').width()<=463 && !container.hasClass('is_mobile_slider'))
				{
					avBgSlider(container, 'mobile', slider_duration, fade, false);
					container.addClass('is_mobile_slider');
				}
				
				for(var i=0; i<all_images.length; i++)
				{
					all_images[i].obj.css({
					'width': Math.ceil(baseImgWidth * ratio),
					'height': Math.ceil(baseImgHeight * ratio),
					'max-width': 'none'
					}).css({
							'margin-left': -all_images[i].obj.width() / margin_ratio,
							'margin-top': parseFloat(-all_images[i].obj.height() / margin_top_ratio+extra_top / margin_top_ratio)
						});
				}
			}
        };

        var resize = function() {
            // run twice to make sure the parameters are correct
            // TODO: improvement on this
            rz();
            rz();
        }
		
		var avBgSlider = function(container, type, duration, fade, init){
			var dur = duration;
			var this_type = type == 'mobile' ? '_mobile' : '';
			var this_container = container;
			
			if(fade)
			{
				//initial slider
				if(!init)
				{
					this_container.empty();
					this_container.html($('#bg_slider'+this_type).html());
					//resize();
				}
				else
				{
					this_container.children().show();
					//executive slider
					if(typeof(slider_timer)!='undefined')
						window.clearInterval(slider_timer);
					var slider_timer = setInterval(function(){
						this_container.children().last().fadeOut(dur, function(){
							var last_slider = $(this).clone();
							$(this).remove();
							this_container.prepend(last_slider);
							last_slider.show();
						});
					}, dur);
				}
			}
		}

        $(loader).bind('finished', function(e){
            root.removeClass('loading');

            all_images = this.imgs;

			for(var i = 0; i < siblings.length; i++) {
				siblingsHeight += ($(siblings[i]).outerHeight(true) || 0);
			}
			maxHeight -= siblingsHeight;

			for(var i = 0; i < relatedContents.length; i++) {
				maxHeight = Math.max($(relatedContents[i]).outerHeight(true), maxHeight);
				maxWidth -= $(relatedContents[i]).innerWidth();
			}

			if($('html').width()>463 || img_mobile.length==0)
				image = this.imgs[0];
			else
			{
				image = this.imgs[img.length];
				container.addClass('is_mobile_slider');
				slider_type = 'mobile';
			}
			container.append(image.obj);

			if($('.main').hasClass('home'))
			{
				image.obj.wrap('<div></div>');
				var slider_src = image.obj.attr('src');
				if(slider_src.match(/l\-[a-zA-Z0-9\-_]+\.[a-zA-Z]+$/i)){
					image.obj.parent().append($('.content_wrap .home_title_left').clone());
				}
				if(slider_src.match(/r\-[a-zA-Z0-9\-_]+\.[a-zA-Z]+$/i)){
					image.obj.parent().append($('.content_wrap .home_title_right').clone());
				}
				image.obj.parent().hide();
			}
			else
			{
				image.obj.hide();
			}
			image.obj.css({
				'position': 'absolute',
				'top': '50%',
				'left': '50%'
			});

			Delta = Math.max(image.dimension.width/maxWidth, image.dimension.height/maxHeight);
			baseImgWidth = image.dimension.width/Delta;
			baseImgHeight = image.dimension.height/Delta;

			root.prepend(container);

			//make a copy of desktop slider and mobile slider
			$('#bg_slider, #bg_slider_mobile').empty();
			for (var i=0; i<all_images.length; i++)
			{
				if(i<img.length)
					var _copy_target = $('#bg_slider');
				else
					var _copy_target = $('#bg_slider_mobile');					
				var slider = all_images[i].obj.clone();

				_copy_target.append(slider);
				slider.css({
					'position': 'absolute',
					'top': '50%',
					'left': '50%'
				});
				if($('.main').hasClass('home'))
				{
					slider.wrap('<div></div>');
					var slider_src = slider.attr('src');
					if(slider_src.match(/l\-[a-zA-Z0-9\-_]+\.[a-zA-Z]+$/i)){
						slider.parent().append($('.content_wrap .home_title_left').clone());
					}
					if(slider_src.match(/r\-[a-zA-Z0-9\-_]+\.[a-zA-Z]+$/i)){
						slider.parent().append($('.content_wrap .home_title_right').clone());
					}
				}
			}

			if(fade) {
				var this_type = slider_type == 'mobile' ? '_mobile' : '';
				$('#bg_slider'+this_type).children().each(function(index){
					if(index>0)
						container.prepend($(this).clone().hide());
				});
				
				if($('.main').hasClass('home'))
				{
					image.obj.parent().fadeIn(duration, function(){
						avBgSlider(container, slider_type, slider_duration, fade, true);
					});
				}
				else
				{
					image.obj.fadeIn(duration, function(){
						avBgSlider(container, slider_type, slider_duration, fade, true);
					});
				}
			} else {
				//image.obj.show();
			}
			
			$(window).bind('resize', function() {
				if(avAnimate.adaptiveBg.performResize) {
					resize();
				}
			});


			// if window not yet loaded
			if(document.readyState !== "complete") {
				var interval = setInterval(function(){
					resize();
				}, 200);
				var fn = function() {
					resize();
					clearInterval(interval);
					$(window).unbind('load', fn);
				};
				$(window).bind('load', fn);
			}

			resize();

			root.trigger('adaptiveBg.started');
			setTimeout(function() {
				root.trigger('adaptiveBg.finished');
			}, fade ? duration : 0);
        });

        var trigger = function() {
			for(var i=0; i<img.length; i++)
			{
				loader.add(img[i]);
			}
			for(var i=0; i<img_mobile.length; i++)
			{
				loader.add(img_mobile[i]);
			}
			loader.load();
        };
        bootStack.add(trigger, this);
    }

    avAnimate.adaptiveBg.prototype = {
        'performResize': true
    }
})(jQuery);
