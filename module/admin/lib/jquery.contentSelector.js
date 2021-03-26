/*****
 * Content Selector
 * File: jquery.contentSelector.js
 * Author: Patrick Yeung
 * Email: <patrick{at}avalade{dot}com>
 * Date: 13年8月21日
 * Time: 上午9:58
 *
 * Version: support tablet and touch platform
 *          20130912 - added another style
 */
var contentSelector = function(a, b) {
    this.init(a, b)
};
(function(c, b, d) {
    var a = {};
    a.SneakPreview = {
        start: function() {
            this.continuousEvtListen = false;
            this.clickEvtName = d.touch || window.navigator.msPointerEnabled ? "tap" : "click";
            this.dragBase = this.root;
            this.setUp()
        },
        showItem: function(f, g, h) {
            if (!this.pauseEvt) {
                this.pauseEvt = true;
                this.currentItem = f < 0 ? (this.count - 1) : f % this.count;
                f = this.currentItem;
                var e = c.inArray(f, this.posList);
                if (e > -1) {
                    this.slideToItem(f, g, h)
                } else {
                    this.replaceItem(f, g, h)
                }
                var e = c.inArray(f, this.posList);
                this.currentList[e].addClass("active");
                for (var f = 0; f < this.currentList.length; f++) {
                    if (f != e) {
                        this.currentList[f].removeClass("active")
                    }
                }
            }
        },
        replaceItem: function(g, f, q) {
            var n = this;
            if (this.currentList.length == 3) {
                var m = [];
                for (var o = 0; o < this.currentList.length; o++) {
                    var p = this.currentList[o];
                    m.push(p[0]);
                    p.css({
                        position: "static",
                        left: p.position().left
                    })
                }
                c(m).css({
                    position: "absolute"
                });
                this.currentList[0].animate({
                    opacity: 0,
                    "margin-left": -this.currentList[0].outerWidth()
                }, {
                    duration: this.duration / 2,
                    complete: function() {
                        c(this).remove()
                    }
                });
                this.currentList[1].animate({
                    opacity: 0
                }, {
                    duration: this.duration / 2,
                    complete: function() {
                        c(this).remove()
                    }
                });
                this.currentList[2].animate({
                    opacity: 0,
                    "margin-left": this.currentList[0].outerWidth()
                }, {
                    duration: this.duration / 2,
                    complete: function() {
                        c(this).remove()
                    }
                });
                this.currentList = [];
                this.posList = [];
                this.currentItem = 0;
                this.contentsWrapper.css({
                    width: 0,
                    left: 0
                })
            }
            this.currentItem = g < 0 ? (this.count - 1) : g % this.count;
            g = this.currentItem;
            var k = f;
            var l = this.attachItemToList(g, true, true);
            l.animate({
                opacity: 1
            }, {
                duration: this.duration,
                complete: function() {
                    n.cleanList()
                }
            });
            if (this.count > 1) {
                var j = this.attachItemToList(g - 1, false, true);
                var h = this.attachItemToList(g + 1, true, true);
                j.css({
                    left: -j.innerWidth()
                });
                h.css({
                    left: j.innerWidth()
                });
                setTimeout(function() {
                    var i = [j[0], h[0]];
                    c(i).animate({
                        left: 0,
                        opacity: 1
                    }, {
                        duration: n.duration / 2
                    })
                }, this.duration / 2);
                if (this.prevNextActions == "Item") {
                    this.actionsContainer.closest(".ctActions").find("a").css({
                        visibility: "visible"
                    })
                }
                if (this.items.length > 1) {
                    this.resetInterval()
                }
            }
            var e = this.actionsContainer.children();
            if (this.captionTimer != null) {
                clearInterval(this.captionTimer)
            } else {
                this.caption.stop();
                this.caption.animate({
                    opacity: 0
                }, {
                    duration: n.captionDuration
                })
            }
            this.captionTimer = setTimeout(function() {
                n.showCaption.apply(n, [])
            }, k - k / 2);
            c(e[g]).addClass("active").find("span.doom").css({
                width: c(e[g]).find("img:first").width(),
                opacity: "0.6"
            });
            e.not(":eq(" + g + ")").removeClass("active").find("span.doom").css("opacity", "0")
        }
    };
    b.prototype = {
        init: function(e, g) {
            var k = this;
            this.contentsWrapper = c(e);
            var m = this.contentsWrapper.attr("id");
            this.root = this.contentsWrapper.wrapAll('<div><div class="contentArea"></div></div>').parent().parent();
            if (m != "") {
                this.contentsWrapper.attr("id", "");
                this.root.attr("id", m)
            }
            if (this.root && this.root.length > 0) {
                this.root.css("visibility", "hidden").addClass("contentSelector");
                this.items = this.contentsWrapper.children().clone();
                this.itemCaptions = [];
                this.itemsVars = [];
                this.posList = [];
                this.currentList = [];
                this.count = this.items.length;
                this.pageItems = 6;
                this.totalPages = null;
                this.currentPage = null;
                this.nodesDisplayWidth = null;
                this.currentItem = null;
                this.caption = c('<div class="caption"></div>');
                this.captionWrapper = c('<div class="captionWrapper"></div>');
                this.captionTimer = null;
                this.intervalPtr = null;
                this.displayNodes = false;
                this.prevNextActions = "Item";
                this.duration = 800;
                this.captionDuration = this.duration / 2;
                this.interval = 5000;
                this.transitionDuration = 500;
                this.imgLoader = new avaImgLoader();
                this.started = false;
                this.resize = true;
                this.style = null;
                this.pauseEvt = false;
                this.pauseEvtActionEvt = false;
                this.continuousEvtListen = true;
                this.clickEvtName = d.touch || window.navigator.msPointerEnabled ? "tap" : "click";
                this.gestureEnabled = d.touch || window.navigator.msPointerEnabled;
                this.contentsWrapper.empty();
                this.items.each(function(s, u) {
                    c(u).addClass("item");
                    k.itemCaptions[s] = null;
                    if (c(u).attr("rel")) {
                        var r = c(u).attr("rel").split(";");
                        var q = {};
                        for (var p = 0; p < r.length; p++) {
                            if (r[p]) {
                                var n = r[p].split(":");
                                if (n.length == 2) {
                                    q[n[0]] = n[1]
                                } else {
                                    if (n.length > 2) {
                                        var o = n.shift();
                                        var v = n.join(":");
                                        q[o] = v
                                    }
                                }
                            }
                        }
                        k.itemsVars[s] = q
                    }
                    if (c(u).find(".info").length) {
                        k.itemCaptions[s] = c(u).find(".info").clone();
                        c(u).find(".info").remove()
                    }
                });
                if (g) {
                    for (variable in g) {
                        switch (variable) {
                            case "displayNodes":
                                this[variable] = parseInt(g[variable]) != 0;
                                break;
                            case "thumbs":
                                if (c.type(g[variable]).toLowerCase() == "array") {
                                    for (var j = 0; j < g[variable].length; j++) {
                                        var h = g[variable][j];
                                        if (h && h != "" && k.itemsVars[j]) {
                                            k.itemsVars[j].thumbnail = h
                                        }
                                    }
                                }
                                break;
                            case "style":
                                if (a[g[variable]] != undefined) {
                                    this.root.addClass(g[variable]);
                                    c.extend(this, a[g[variable]])
                                }
                                break;
                            case "resize":
                                this[variable] = g[variable];
                                break;
                            case "pageItems":
                            case "duration":
                            case "transitionDuration":
                            case "interval":
                                if (g[variable] !== "") {
                                    this[variable] = parseInt(g[variable])
                                }
                                break
                        }
                    }
                }
                this.totalPages = Math.ceil(this.items.length / this.pageItems);
                this.root.find(".contentArea").append(this.caption);
                this.root.find('.contentArea').append('<a href="#" class="pause_play" style="display:none;"></a>');
                this.root.append('<div class="ctActionContainer"><div class="ctActions"><a class="prev" href="#">Previous</a><div class="previewNodes"><ul></ul></div><a class="next" href="#">Next</a></div></div>');
                this.actionsContainer = this.root.find(".previewNodes ul");
                this.actionsContainer.hammer().delegate("li a", this.clickEvtName, function(o) {
                    o.preventDefault();
                    o.stopPropagation();
                    var n = c(o.target).closest("li").prevAll().length;
                    k.showItem(n, k.duration, k.currentItem < n)
                });
                this.setupNodes();
                this.gotoPage(0);
                this.actionsContainer.hide();
                this.actionsContainer.closest(".ctActions").hide();
                var l = 0;
                if (this.items.length > 0) {
                    var k = this;
                    c(this.imgLoader).bind("finished", function() {
                        k.start()
                    });
                    for (var j = 0; j < this.items.length; j++) {
                        var f = c(this.items[j]).find("img:eq(0)");
                        if (f.length) {
                            l++;
                            this.imgLoader.add(f)
                        }
                    }
                    this.root.addClass("loading");
                    this.imgLoader.load()
                }
                if (!l) {
                    this.start()
                }
                this.root.css({
                    visibility: "visible"
                })
            }
            this.winResize = function() {
                k.resizeEvt()
            }
        },
        resizeEvt: function() {
            var e = this.root.width();
            var l = 0;
            var f = 0;
            var j = [];
            var k = c('<ul style="position: absolute; top: 0; left: 0;z-index: 0;visibility: hidden;"></ul>');
            for (var g = 0; g < this.items.length; g++) {
                var h = c(this.items[g]).clone();
                j.push(h);
                k.append(h)
            }
            this.items.width(e);
            this.contentsWrapper.children().width(e);
            for (var g = 0; g < this.currentList.length; g++) {
                l += Math.ceil(this.currentList[g].outerWidth(true)) + 1
            }
            this.contentsWrapper.width(l);
            this.showItem(this.currentItem, 0);
            this.root.find(".contentArea").prepend(k);
            for (var g = 0; g < j.length; g++) {
                f = Math.max(f, j[g][0].scrollHeight)
            }
            k.remove();
            this.contentsWrapper.height(f)
        },
        start: function() {
            this.dragBase = this.root.find(".contentArea");
            this.setUp()
        },
        setUp: function() {
            this.root.removeClass("loading");
            var f = this;
            if (this.items.length > 1) {
                this.actionsContainer.show();
                this.actionsContainer.closest(".ctActions").show();
                if (this.clickEvtName != "click") {
                    this.actionsContainer.closest(".ctActions").find("a").bind("click", function(g) {
                        g.stopPropagation();
                        g.preventDefault()
                    })
                }
                this.actionsContainer.closest(".ctActions").hammer({
                    hold: false
                }).delegate("a.prev", this.clickEvtName, function(g) {
                    g.preventDefault();
                    g.stopPropagation();
                    if (!f.pauseEvt && !f.pauseEvtActionEvt) {
                        f["prev" + f.prevNextActions]()
                    }
                });
                this.actionsContainer.closest(".ctActions").hammer({
                    hold: false
                }).delegate("a.next", this.clickEvtName, function(g) {
                    g.preventDefault();
                    g.stopPropagation();
                    if (!f.pauseEvt && !f.pauseEvtActionEvt) {
                        f["next" + f.prevNextActions]()
                    }
                });
                if (this.gestureEnabled) {
                    var e = {
                        prevent_default: true,
                        drag: true,
                        drag_block_horizontal: true,
                        drag_block_vertical: true,
                        drag_lock_to_axis: true,
                        stop_browser_behavior: {
                            userSelect: "none",
                            touchAction: "none",
                            touchCallout: "none",
                            contentZooming: "none",
                            userDrag: "none",
                            tapHighlightColor: "rgba(0,0,0,0)"
                        }
                    };
                    this.root.hammer(e);
                    this.root.find("a.prev, a.next").hammer(e);
                    this.root.find(".contentArea").hammer({
                        drag_min_distance: 1,
                        prevent_mouseevents: true,
                        hold: false,
                        drag_block_horizontal: true,
                        release: false,
                        touch: false
                    }).bind("swipeleft", function(g) {
                        if (!f.pauseEvt) {
                            f.swipeAction.apply(f, [g, "next", f.dragBase])
                        }
                    });
                    this.root.find(".contentArea").bind("swiperight", function(g) {
                        if (!f.pauseEvt) {
                            f.swipeAction.apply(f, [g, "prev", f.dragBase])
                        }
                    });
                    this.dragEvt(this.dragBase)
                }
                this.root.find(".contentArea").bind("mouseenter", function() {
                    try {
                        clearInterval(f.intervalPtr)
                    } catch (g) {}
                });
                this.root.find(".contentArea").bind("mouseleave", function() {
                    f.resetInterval()
                });
                if (this.interval > 0) {
                    this.root.find('.pause_play').show().bind('click', function(e) {
                        e.preventDefault();
                        if (!$(this).hasClass('play_btn')) {
                            $(this).parent('.contentArea').unbind('mouseleave mouseenter');
                            $(this).addClass('play_btn');
                            try {
                                clearInterval(f.intervalPtr)
                            } catch (g) {}
                        } else {
                            $(this).removeClass('play_btn');
                            $(this).parent('.contentArea').bind('mouseleave', function() {
                                f.resetInterval();
                            }).bind('mouseenter', function() {
                                try {
                                    clearInterval(f.intervalPtr)
                                } catch (g) {}
                            });
                            f.resetInterval();
                        }
                    });
                }
            }
            this.resizeEvt();
            this.contentsWrapper.css({
                visibility: "visible"
            });
            if (this.items.length > 1) {
                this.resetInterval()
            }
            this.started = true;
            if (f.resize) {
                c(window).bind("resize", this.winResize)
            }
            c(window).bind("load", this.winResize())
        },
        swipeAction: function(h, g, f) {
            h.preventDefault();
            h.stopPropagation();
            this.dragBase.unbind("drag dragend");
            this.currentItem = c(f).data("startItem");
            this[g + this.prevNextActions](this.duration / 4);
            this.pauseEvtActionEvt = false
        },
        dragEvt: function(f) {
            var e = this;
            f.bind("dragstart", function(g) {
                g.stopPropagation();
                g.preventDefault();
                if (!e.pauseEvt) {
                    e.contentsWrapper.stop(true, false);
                    c(this).data("prevDragPos", e.contentsWrapper.position().left);
                    c(this).data("startItem", e.currentItem);
                    f.bind("drag", ".contentArea", function(o) {
                        if (o.gesture != undefined && c.inArray(o.gesture.direction, ["left", "right"]) > -1) {
                            o.stopPropagation();
                            o.preventDefault();
                            e.resetInterval();
                            e.pauseEvtActionEvt = true;
                            var p = (o.gesture.distance * (o.gesture.direction == "left" ? -1 : 1));
                            c(this).data("dragDirection", p > 0 ? "left" : "right");
                            var n = c(this).data("prevDragPos") + p;
                            e.contentsWrapper.css({
                                left: n
                            });
                            var m = e.contentsWrapper.children();
                            var k = 0;
                            var j = o.gesture.direction == "left" ? 0.9 : 0.1;
                            var h = e.root.outerWidth(true) * j + Math.abs(n);
                            for (var l = 0; l < m.length; l++) {
                                k += c(m[l]).outerWidth(true);
                                if (k > h) {
                                    break
                                }
                            }
                            if (l == m.length - 1) {
                                e.attachItemToList(e.posList[e.posList.length - 1] + 1)
                            } else {
                                if (l == 0) {
                                    e.attachItemToList(e.posList[0] - 1, false);
                                    c(this).data("prevDragPos", e.contentsWrapper.position().left - p)
                                }
                            }
                            e.currentItem = e.posList[l]
                        }
                    });
                    f.bind("dragend", function(h) {
                        h.stopPropagation();
                        h.preventDefault();
                        e.pauseEvtActionEvt = false;
                        f.unbind("drag");
                        f.unbind("dragend");
                        e.showItem(e.currentItem, e.duration / 4, c(this).data("dragDirection") == "right");
                        c(this).data("prevDragPos", 0);
                        c(this).data("dragDirection", "")
                    })
                }
            })
        },
        showCaption: function() {
            clearInterval(this.captionTimer);
            this.captionTimer = null;
            this.caption.empty();
            if (this.itemCaptions[this.currentItem] != null) {
                var l = this.itemCaptions[this.currentItem].clone();
                this.caption.append(this.captionWrapper.clone());
                this.caption.find(".captionWrapper").append(l);
                var f = l.children();
                if (f.length > 1) {
                    var j = 0;
                    var k = null;
                    for (var e = 0; e < f.length; e++) {
                        var g = c(f[e]).innerHeight();
                        if (k == null || j < g) {
                            j = g;
                            k = f[e]
                        }
                    }
                    f.not(c(k)).each(function(m, o) {
                        var n = c(o).innerHeight();
                        c(o).css({
                            position: "relative",
                            top: j / 2 - c(o).innerHeight() / 2
                        })
                    })
                }
                this.caption.stop(true, false).css({
                    opacity: 0
                }).animate({
                    opacity: 1
                }, {
                    duration: this.captionDuration
                })
            }
        },
        showItem: function(e, f, g) {
            this.slideToItem(e, f, g)
        },
        slideToItem: function(v, e, u) {
            var A = this;
            var B = null;
            var k = c.inArray(this.currentItem, this.posList);
            var h = this.currentItem;
            this.currentItem = v < 0 ? (this.count - 1) : v % this.count;
            v = this.currentItem;
            var m = c.inArray(v, this.posList, u ? k : 0);
            if (m > -1) {
                B = this.currentList[m]
            }
            if (B == null) {
                if (u) {
                    var g = this.posList.length - 1
                } else {
                    var g = 0
                }
                var o = 0;
                if (this.currentList[g] != undefined) {
                    var l = [];
                    var D = [];
                    var y = [];
                    for (var t = 0; t < this.posList.length; t++) {
                        if (t != g) {
                            l.push(this.currentList[t]);
                            D.push(this.posList[t]);
                            y.push(this.currentList[t][0])
                        } else {
                            if (!u) {
                                o = this.currentList[t].innerWidth()
                            }
                        }
                    }
                    this.posList = D;
                    this.currentList = l;
                    this.contentsWrapper.children().not(c(y)).remove();
                    if (o) {
                        this.contentsWrapper.css({
                            left: this.contentsWrapper.position().left + o
                        })
                    }
                }
                B = this.attachItemToList(v, u)
            }
            var s = e;
            m = c.inArray(v, this.posList, u ? k - 1 : 0);
            if (m == 0) {
                this.attachItemToList(v - 1, false);
                m++
            }
            if (m == this.posList.length - 1) {
                this.attachItemToList(v + 1)
            }
            var r = this.actionsContainer.children();
            if (this.captionTimer != null) {
                clearInterval(this.captionTimer)
            } else {
                this.caption.stop();
                this.caption.animate({
                    opacity: 0
                }, {
                    duration: A.captionDuration
                })
            }
            this.captionTimer = setTimeout(function() {
                A.showCaption.apply(A, [])
            }, s - s / 2);
            c(r[v]).addClass("active").find("span.doom").css({
                width: c(r[v]).find("img:first").width(),
                opacity: "0.6"
            });
            r.not(":eq(" + v + ")").removeClass("active").find("span.doom").css("opacity", "0");
            var C = B.position();
            var n = this.contentsWrapper.position().left;
            var x = parseFloat(B.css("marginLeft").replace(/px/g, ""));
            var p = -C.left - x;
            var z = B.innerWidth();
            var q = Math.abs(n - p);
            if (q < z) {
                var f = q / z;
                s *= f
            }
            this.contentsWrapper.stop(true, false).animate({
                left: p
            }, {
                duration: s,
                complete: function() {
                    A.cleanList()
                }
            });
            if (this.prevNextActions == "Item") {
                this.actionsContainer.closest(".ctActions").find("a").css({
                    visibility: "visible"
                })
            }
            if (this.items.length > 1) {
                this.resetInterval()
            }
            this.contentsWrapper.children().css({
                opacity: 1
            })
        },
        cleanList: function() {
            var j = [this.getPrevItemNum(), this.currentItem, this.getNextItemNum()];
            for (var e = 0; e < j.length; e++) {
                var h = j[e];
                var g = c.inArray(h, this.posList, e);
                if (g == -1) {
                    var l = this.items.eq(h).clone();
                    if (this.currentList[e]) {
                        this.currentList[e].replaceWith(l);
                        this.currentList[e] = l
                    }
                    this.posList[e] = h
                } else {
                    this.currentList[e] = this.currentList[g];
                    this.posList[e] = h
                }
            }
            this.currentList = this.currentList.slice(0, 3);
            this.posList = j;
            var f = [];
            for (var e = 0; e < this.currentList.length; e++) {
                f[e] = this.currentList[e][0]
            }
            this.contentsWrapper.children().not(c(f)).remove();
            if (this.currentList[1]) {
                var k = parseFloat(this.currentList[1].css("marginLeft").replace(/px/g, ""));
                this.contentsWrapper.css({
                    left: (this.currentList[1].position().left + k) * -1
                })
            }
            this.pauseEvt = false
        },
        getNextItemNum: function() {
            var e = this.currentItem + 1;
            return e < 0 ? (this.count - 1) : e % this.count
        },
        getPrevItemNum: function() {
            var e = this.currentItem - 1;
            return e < 0 ? (this.count - 1) : e % this.count
        },
        attachItemToList: function(k, f, j) {
            k = k < 0 ? (this.count - 1) : k % this.count;
            if (f == undefined) {
                f = true
            }
            if (j == undefined) {
                j = false
            }
            var l = this.items.eq(k).clone();
            l.css({
                position: "absolute",
                visibility: "hidden"
            });
            if (f) {
                this.posList.push(k);
                this.currentList.push(l);
                this.contentsWrapper.append(l)
            } else {
                this.posList = c.merge([k], this.posList);
                this.currentList = c.merge([l], this.currentList);
                this.contentsWrapper.prepend(l)
            }
            var g = 0;
            var h = [];
            for (var e = 0; e < this.currentList.length; e++) {
                g += this.currentList[e].outerWidth(true) + 1;
                h.push(this.currentList[e][0])
            }
            this.contentsWrapper.css({
                width: g
            });
            if (this.currentList.length == 1) {
                var m = parseFloat(l.css("marginLeft").replace(/px/g, ""))
            } else {
                var m = 0
            }
            if (!f) {
                m += l.outerWidth(true)
            }
            this.contentsWrapper.css({
                left: this.contentsWrapper.position().left - m
            });
            if (j) {
                c(h).css({
                    opacity: 0
                })
            }
            l.css({
                position: "relative",
                visibility: "visible"
            });
            return l
        },
        setupNodes: function() {
            var m = this;
            this.actionsContainer.empty();
            this.items.each(function(o, p) {
                var n = (o + 1);
                if (m.itemsVars[o] && m.itemsVars[o].thumbnail) {
                    m.actionsContainer.append('<li><a href="#" title="' + m.itemsVars[o].title + '"><span class="overlay"></span><img src="' + m.itemsVars[o].thumbnail + '" /></a></li>')
                } else {
                    m.actionsContainer.append('<li><a href="#" title="' + n + '" class="dot">' + n + "</a></li>")
                }
            });
            var e = 0,
                g = 0;
            var k = this.actionsContainer.children();
            var j = Math.min(k.length, this.pageItems) - 1;
            for (var h = 0; h < k.length; h++) {
                var l = c(k[h]).outerWidth(true);
                c(k[h]).addClass("node-" + (h + 1));
                var f = h != j ? l : c(k[h]).innerWidth();
                if (h <= j) {
                    g += f
                }
                e += l
            }
            this.actionsContainer.css("width", e);
            this.actionsContainer.closest(".ctActions").css("width", g);
            this.nodesDisplayWidth = g
        },
        nextItem: function(f) {
            var e = this.currentItem + 1;
            if (f == undefined) {
                f = this.duration
            }
            this.showItem(e, f, true);
            this.gotoPage(Math.floor(this.currentItem / this.pageItems))
        },
        prevItem: function(f) {
            var e = this.currentItem - 1;
            if (f == undefined) {
                f = this.duration
            }
            this.showItem(e, f, false);
            this.gotoPage(Math.floor(this.currentItem / this.pageItems))
        },
        gotoPage: function(g) {
            var f = g >= this.totalPages ? 0 : (g < 0 ? this.totalPages - 1 : g);
            if (f != this.currentPage) {
                var e = this.nodesDisplayWidth * f;
                this.actionsContainer.stop().animate({
                    left: -e
                }, {
                    duration: this.transitionDuration,
                    easing: "linear",
                    queue: false
                });
                if (this.prevNextActions == "Page") {
                    this.actionsContainer.closest(".ctActions").find("a.prev, a.next").css({
                        visibility: "hidden"
                    });
                    if (f != this.totalPages - 1) {
                        this.actionsContainer.closest(".ctActions").find("a.next").css({
                            visibility: "visible"
                        })
                    }
                    if (f != 0) {
                        this.actionsContainer.closest(".ctActions").find("a.prev").css({
                            visibility: "visible"
                        })
                    }
                }
                this.currentPage = f
            }
        },
        nextPage: function() {
            this.gotoPage(this.currentPage + 1)
        },
        prevPage: function() {
            this.gotoPage(this.currentPage - 1)
        },
        resetInterval: function() {
            var e = this;
            if (this.intervalPtr) {
                clearInterval(this.intervalPtr)
            }
            if (this.interval > 0) {
                this.intervalPtr = setInterval(function() {
                    e.nextItem()
                }, e.interval)
            }
        },
        destroy: function() {
            try {
                clearInterval(this.captionTimer);
                clearInterval(this.intervalPtr);
                this.root.find(".contentArea").unbind();
                this.actionsContainer.closest(".ctActions").find("a").unbind();
                c(window).unbind("resize", this.winResize);
                this.root.empty();
                this.root.remove();
                this.items.remove();
                this.prototype = {}
            } catch (f) {}
        }
    }
})(jQuery, contentSelector);