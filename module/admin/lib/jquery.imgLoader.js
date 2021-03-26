var avaImgLoader = function() {
    this.imgs = [];
    this.count = 0;
    this.successCount = 0;
    this.failedCount = 0;
    this.init()
};
var avaImg = function(a) {
    this.url = null;
    this.obj = null;
    this.loaderObj = null;
    this.loaderImg = null;
    this.dimension = {
        width: 0,
        height: 0
    };
    this.init(a)
};
(function(a) {
    avaImgLoader.prototype = {
        init: function() {},
        add: function(d) {
            var b = this;
            var c = new avaImg(d);
            this.imgs.push(c);
            a(c).bind("loaded", function() {
                b.successCount++;
                b.checkCompleted()
            });
            a(c).bind("failed", function() {
                b.failedCount++;
                b.checkCompleted()
            });
            this.count++
        },
        load: function() {
            for (var b = 0; b < this.imgs.length; b++) {
                this.imgs[b].load()
            }
        },
        checkCompleted: function() {
            if ((this.successCount + this.failedCount) == (this.count)) {
                a(this).trigger("finished")
            }
        }
    };
    avaImg.prototype = {
        init: function(b) {
            var c = this;
            this.obj = b;
            this.url = this.obj.attr("src");
            this.loaderImg = a('<img src="' + this.url + '" style="width:auto;height:auto;max-height:none;max-width:none;" />');
            this.loaderObj = a('<div style="width: 1px; height: 1px; overflow: hidden;position:absolute; top: 0; left: 0; z-index: 0;visibility:hidden;"></div>')
        },
        load: function() {
            var b = this;
            a(this.loaderImg).bind("load", function() {
                var c = this;
                setTimeout(function() {
                    b.dimension.width = a(c).width();
                    b.dimension.height = a(c).height();
                    b.loaderObj.remove();
                    a(b).trigger("loaded")
                }, 0)
            });
            a(this.loaderImg).bind("error", function() {
                a(b).trigger("failed");
                b.loaderObj.remove()
            });
            this.loaderObj.css({
                visibility: "hidden"
            });
            this.loaderObj.append(this.loaderImg);
            a("body").append(this.loaderObj)
        }
    }
})(jQuery);