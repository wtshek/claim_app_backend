/**
 * File:
 * Author: Patrick Yeung
 * Email: <patrick{at}avalade{dot}com>
 * Date: 13年8月7日
 * Time: 下午12:08
 *
 */

// admin form submission
var form_submit_panel = function(form) {
    this.form = null;
    this.container = null;
    this.errors = [];
    this.html = {
        'errorStack': '<ul class="errorStack errors"></ul>'
    };
    this.init(form);
};

(function(fn, $){
    var error = function(text, ref) {
        this.html = {
            'ul': '<li class="error"></li>',
            'others': '<div class="error"></div>'
        }
        this.el = null;
        this.single = null;
        this.container = null;
        this.ref = null;
        this.additionalData = {};
        this.init(text, ref);
    };

    error.prototype = {
        'init': function(text, ref) {
            this.msg = text;
            if(this.ref != undefined && this.ref != null) {
                this.ref = ref;
            }
        },
        'appendTo': function(parent) {
            var type = parent.hasClass('errors') && this.html[parent.prop("tagName").toLowerCase()] != undefined ? parent.prop("tagName").toLowerCase() : "others";
            this.single = type == 'others';
            this.el = $(this.html[type]).append('<span>' + this.msg + '</span>');
            //parent.append(this.el);
            parent.prepend(this.el);

            if(this.single) {
                // try to find its dl , if no dl could be found, use the current one instead
                var tmp = parent;
                var maxPCount = 5; //maximum 5 level
                var i = 0;

                do {
                    tmp = tmp.parent();

                    if(tmp.prop("tagName").toLowerCase() == 'dl' || tmp.hasClass('input-field')) {
                        parent = tmp;
                        break;
                    }
                } while(tmp != null && i++ < maxPCount);

                this.container = parent;

                //this.container = $.inArray(parent.prop("tagName").toLowerCase(), ["dd", "dt"]) > -1 ? parent.parent() : parent;
                this.container.addClass('hasError');
            }
        },
        'destroy': function() {
            if(this.el != undefined && this.el != null) {
                if(this.single) {
                    this.container.removeClass('hasError');
                }
                this.el.remove();
            }
        }
    }

    fn.prototype = {
        'init': function(form) {
            this.form = form;
            this.error_container = $(this.html.errorStack);
            this.container = $(this.form.find("> .box-content")[0] || this.form);
            this.container.prepend(this.error_container);
            this.errors = [];

            var me = this;

            var submitFn = function(e) {
                if(me.form.valid == undefined || (me.form.valid != undefined && me.form.valid())) {
                    me.submitHandler(e);
                }
                return false;
            }

            this.form.bind('submit', submitFn);

            /*
            this.form.delegate('input[type="text"], input[type="password"], input[type="email"]', 'keydown', function(e){
                if(e.keyCode == 13) {
                    e.preventDefault();
                    me.form.trigger('submit');
                }
            });
            */
        },
        'submitHandler': function(e) {
            e.preventDefault();
            //return;

            var me = this;

            this.clearErrors();
            this.errors = [];

            $.ajax({
                'url': this.form.attr('action'),
                'type': this.form.attr('method'),
                'cache': false,
                'async': true,
                'data': this.getData(),
                'beforeSend': function() {
                    me.form.find('.hasErrors').removeClass('hasErrors');
                    me.form.find('.hasError').removeClass('hasError');
                    me.form.find('button,input[type="submit"],input[type="button"],input[type="reset"]').prop('disabled', true);
					$('.loading').css('padding-top', parseFloat($(window).scrollTop()+$('html').height()/2)+'px').fadeIn('fast');
                },
                'error': function(ajax, text, err) {

                },
                'complete': function(ajax, status) {
                    this.additionalData = {};

                    switch(ajax.status) {
                    }

                    if(!ajax.redirect)
                        me.form.find('button,input[type="submit"],input[type="button"],input[type="reset"]').prop('disabled', false);
					$('.loading').delay( 400 ).fadeOut("fast");
                },
                'success': function(json, status, ajax) {
                    if(json.result == "session_timeout") {

                    } else if(json.result == "error") {
                        me.processErrors(json.errors);
                    } else if(json.result == "confirm") {
                        if ( window.confirm(json.message) ) {
                            me.form.attr( "action", json.action );
                            me.form.submit();
                        }
                    } else if(json.redirect != undefined && json.redirect != "") {
                        if(json.target != undefined && json.target == "_blank") {
                            window.open(json.redirect, "admin_preview");
                        } else {
                            $(window).unbind('leaveWpEdit');
                            window.location.href = json.redirect;
                        }
                    } else {
                        //$(me.form).submit();

                        me.form.trigger('ajaxSuccess', [json]);
                    }
                }
            });

        },
        'clearErrors': function() {
            for(var i = 0; i < this.errors.length; i++) {
                this.errors[i].destroy();
            }

            this.errors = [];

            // ensure the hasErrors class are removed
            this.form.find('.hasErrors').removeClass('hasErrors');
            this.form.find('.hasError').removeClass('hasError');
        },
        'processErrors': function(errors) {
            var first_element = null;
            for(var n in errors) {
                if(errors.hasOwnProperty(n)) {

                    var target = this.form.find('*[id="' + n + '"],input[name="' + n + '"],select[name="' + n + '"],textarea[name="' + n + '"]');
                    for(var i = 0; i < errors[n].length; i++) {
                        var e = new error(errors[n][i], target.length ? target : null);
                        var parent;

                        if(target.length > 0) {
                            if(first_element == null)
                                first_element = target;

                            switch($(target[0]).prop("tagName").toLowerCase()) {
                                case "input":
                                    var t = $(target[0]).attr('type');
                                    if(t == "radio") {
                                        parent = $(target[0]).parentsUntil(".radio-field", "dd").last();
                                    } else if(t == "checkbox") {
                                        parent = $(target[0]).parentsUntil(".checkbox-field", "dd").last();
                                    } else {
                                        parent = $(target[0]).parent().parent();
                                    }
                                    break;
                                case "select":
                                default:
                                    parent = $(target[0]).parent();
                                    break;
                            }
                        } else {
                            parent = this.error_container;
                        }

                        e.appendTo(parent);
                        this.errors.push(e);
                    }
                }
            }

            // get inner tab boxes
            var tabs = $('.tabs-box .tab-content .tab-pane');
            var errorFilter = null;
            for(var i = 0; i < tabs.length; i++) {
                var tab = $(tabs[i]);
                //window.console.dir(tab.find('.hasError').length);
                if(tab.find('.hasError').length > 0) {
                    var pos = tab.prevAll('.tab-pane').length;
                    var root = tab.parentsUntil('.tabs-box').parent()[0];

                    $(root).addClass('hasErrors').find('.tabs li:eq(' + pos + ')').addClass('hasErrors');
                    if(errorFilter == null) {
                        errorFilter = $(root).find('.tabs li:eq(' + pos + ') a');
                        errorFilter.tab('show');
                    }
                    $(tab).addClass('hasErrors');
                }
            }


            if(this.errors[0]) {
                try {
                    first_element.focus();
                } catch(e) {

                };
            }

        },
        'getData': function() {
            var data = {
                'ajax': 1
            };

            // get field data
            var fields = this.form.find("textarea");
            $.extend(true, data, this.getFieldsValues(fields));

            var fields = this.form.find("input[type='text'], input[type='hidden'], input[type='email'], input[type='number'], input[type='password'], input[type='file']");
            $.extend(true, data, this.getFieldsValues(fields));

            fields = this.form.find("input[type='checkbox']:checked");
            $.extend(true, data, this.getFieldsValues(fields));

            fields = this.form.find("input[type='radio']:checked");
            $.extend(true, data, this.getFieldsValues(fields));

            fields = this.form.find("select");
            $.extend(true, data, this.getFieldsValues(fields));

            $.extend(true, data, this.additionalData);

            return data;
        },
        'getFieldsValues': function(fs) {
            var data = {};
            var mRegExp = /\[\]$/i;
            var mRegExp2 = /([^\[]+)\[([^\]]*)\]/i;


            for(var i = 0; i < fs.length; i++) {
                var f = $(fs[i]);
                var n = f.attr('name');
                if(f.is('textarea') && f.attr('id')!=undefined && tinyMCE.get(f.attr('id'))) {
                    var val = tinyMCE.get(f.attr('id')).getContent();

                } else {
                    var val = f.val();
                }

                if(mRegExp.test(n)) {
                    var n2 = n.replace(mRegExp, "");
                    if(data[n2] == undefined) {
                        data[n2] = []; // assign a new array
                    }
                    if($.isArray(val)) {
                        data[n2] = val;
                    } else {
                        data[n2].push(val);
                    }
                } else if(mRegExp2.test(n)) {
                    var tmp = n;
                    var obj = {};
                    var obj2 = obj;

                    do {
                        var args = undefined;
                        tmp = tmp.replace(mRegExp2, function(){
                            args = arguments;

                            return args[1];
                        });

                        if(mRegExp2.test(tmp)) {
                            obj2[args[2]] = {};
                            obj2 = obj2[args[2]];
                        } else {
                            obj2[args[2]] = val;
                            break;
                        }
                    } while(true);

                    n2 = args[1];
                    tmp = {};
                    tmp[n2] = obj;
                    data = $.extend(true, data, tmp);
                } else {
                    data[n] = val;
                }
            }
            return data;
        }
    }


})(form_submit_panel, jQuery);