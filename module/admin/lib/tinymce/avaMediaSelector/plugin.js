/**
 * File:
 * Author: Patrick Yeung
 * Email: <patrick{at}avalade{dot}com>
 * Date: 13年7月25日
 * Time: 上午11:55
 *
 */
var avaMediaSelectorSettings = {
    'title': '',
    'url1': '',
    'url2': ''
};
(function(exports, undefined) {
    "use strict";

    var modules = {};


    function require(ids, callback) {
        var module, defs = [];

        for (var i = 0; i < ids.length; ++i) {
            module = modules[ids[i]] || resolve(ids[i]);
            if (!module) {
                throw 'module definition dependecy not found: ' + ids[i];
            }

            defs.push(module);
        }

        callback.apply(null, defs);
    }

    function define(id, dependencies, definition) {
        if (typeof id !== 'string') {
            throw 'invalid module definition, module id must be defined and be a string';
        }

        if (dependencies === undefined) {
            throw 'invalid module definition, dependencies must be specified';
        }

        if (definition === undefined) {
            throw 'invalid module definition, definition function must be specified';
        }

        require(dependencies, function() {
            modules[id] = definition.apply(null, arguments);
        });
    }

    function defined(id) {
        return !!modules[id];
    }

    function resolve(id) {
        var target = exports;
        var fragments = id.split(/[.\/]/);

        for (var fi = 0; fi < fragments.length; ++fi) {
            if (!target[fragments[fi]]) {
                return;
            }

            target = target[fragments[fi]];
        }

        return target;
    }

    function expose(ids) {
        for (var i = 0; i < ids.length; i++) {
            var target = exports;
            var id = ids[i];
            var fragments = id.split(/[.\/]/);

            for (var fi = 0; fi < fragments.length - 1; ++fi) {
                if (target[fragments[fi]] === undefined) {
                    target[fragments[fi]] = {};
                }

                target = target[fragments[fi]];
            }

            target[fragments[fragments.length - 1]] = modules[id];
        }
    }

// Included from: js/moxman/util/Loader.js

    /**
     * Loader.js
     *
     * Copyright 2003-2013, Moxiecode Systems AB, All rights reserved.
     */

    define("avalade/util/Loader", [], function() {
        "use strict";

        var idCount = 0, loadedUrls = {};

        function noop() {}

        function appendToHead(node) {
            document.getElementsByTagName('head')[0].appendChild(node);
        }

        var Loader = {
            maxLoadTime: 5,

            load: function(urls, loadedCallback, errorCallback) {
                var cssFiles = urls.css || [], jsFiles = urls.js || [];

                function loadNextScript() {
                    if (jsFiles.length) {
                        Loader.loadScript(jsFiles.shift(), loadNextScript, errorCallback);
                    } else {
                        loadNextCss();
                    }
                }

                function loadNextCss() {
                    if (cssFiles.length) {
                        Loader.loadCss(cssFiles.shift(), loadNextCss, errorCallback);
                    } else {
                        loadedCallback();
                    }
                }

                loadNextScript();
            },

            loadScript: function(url, loadedCallback, errorCallback) {
                var key, script;

                function done() {
                    loadedUrls[url] = true;
                    loadedCallback();
                }

                if (loadedUrls[url]) {
                    loadedCallback();
                    return;
                }

                loadedCallback = loadedCallback || noop;
                errorCallback = errorCallback || noop;

                script = document.createElement('script');
                script.type = 'text/javascript';

                // Enables attributes to be passed in
                if (typeof(url) == "object") {
                    for (key in url) {
                        script.setAttribute(key, url[key]);
                    }
                } else {
                    script.src = url;
                }

                if ("onload" in script) {
                    script.onload = done;
                    script.onerror = errorCallback;
                } else {
                    script.onreadystatechange = function() {
                        var state = script.readyState;

                        if (state == 'complete' || state == 'loaded') {
                            done();
                        }
                    };

                    script.onerror = errorCallback;
                }

                appendToHead(script);
            },

            loadCss: function(url, loadedCallback, errorCallback) {
                var doc = document, link, style, startTime;

                function done() {
                    loadedUrls[url] = true;
                    loadedCallback();
                }

                if (loadedUrls[url]) {
                    loadedCallback();
                    return;
                }

                loadedCallback = loadedCallback || noop;
                errorCallback = errorCallback || noop;

                // Sniffs for older WebKit versions that have the link.onload but a broken one
                function isOldWebKit() {
                    var webKitChunks = navigator.userAgent.match(/WebKit\/(\d*)/);
                    return !!(webKitChunks && webKitChunks[1] < 536);
                }

                // Waits for WebKitLink to be loaded
                function waitForWebKitLinkLoaded() {
                    var styleSheets = doc.styleSheets, file, i = styleSheets.length, owner;

                    while (i--) {
                        file = styleSheets[i];
                        owner = file.ownerNode ? file.ownerNode : file.owningElement;
                        if (owner && owner.id === link.id) {
                            done();
                            return;
                        }
                    }

                    // Wait for 5 seconds
                    if ((new Date().getTime()) - startTime < Loader.maxLoadTime * 1000) {
                        window.setTimeout(waitForWebKitLinkLoaded, 0);
                    } else {
                        errorCallback();
                    }
                }

                // Waits for a Gecko link to be loaded
                function waitForGeckoLinkLoaded() {
                    try {
                        // Accessing the cssRules will throw an exception until the CSS file is loaded
                        var cssRules = style.sheet.cssRules;
                        done();
                        return cssRules;
                    } catch (ex) {
                        // Ignore
                    }

                    // Wait for 5 seconds
                    if ((new Date().getTime()) - startTime < Loader.maxLoadTime * 1000) {
                        window.setTimeout(waitForGeckoLinkLoaded, 0);
                    } else {
                        errorCallback();
                    }
                }

                link = doc.createElement('link');
                link.rel = 'stylesheet';
                link.type = 'text/css';
                link.href = url;
                link.id = 'u' + (idCount++);
                startTime = new Date().getTime();

                // Feature detect onload on link element and sniff older webkits since it has an broken onload event
                if ("onload" in link && !isOldWebKit()) {
                    link.onload = done;
                    link.onerror = errorCallback;
                } else {
                    // Sniff for old Firefox that doesn't support the onload event on link elements
                    if (navigator.userAgent.indexOf("Firefox") > 0) {
                        style = doc.createElement('style');
                        style.textContent = '@import "' + url + '"';
                        waitForGeckoLinkLoaded();
                        appendToHead(style);
                        return;
                    } else {
                        // Use the id owner on older webkits
                        waitForWebKitLinkLoaded();
                    }
                }

                appendToHead(link);
            }
        };

        return Loader;
    });

// Included from: js/moxman/Env.js

    /**
     * Env.js
     *
     * Copyright 2003-2013, Moxiecode Systems AB, All rights reserved.
     */

    /**
     * ...
     */
    define("avalade/Env", [], function() {
        return {
            apiPageName: "api.php",
            ie7: document.all && !window.opera && !document.documentMode
        };
    });

// Included from: js/moxman/util/I18n.js

    /**
     * I18n.js
     *
     * Copyright 2003-2013, Moxiecode Systems AB, All rights reserved.
     */

    /**
     * I18n class that handles translation of moxman UI.
     * Uses po style with csharp style parameters.
     *
     * @class moxman.util.I18n
     */
    define("avalade/util/I18n", [], function() {
        "use strict";

        function resolve(id) {
            var target = window;
            var fragments = id.split(/\//);

            for (var fi = 0; fi < fragments.length; ++fi) {
                if (!target[fragments[fi]]) {
                    return;
                }

                target = target[fragments[fi]];
            }

            return target;
        }

        var I18n = resolve("avalade/util/I18n");
        if (I18n) {
            return I18n;
        }

        var data = {};

        return {
            /**
             * Adds translations for a specific language code.
             *
             * @method add
             * @param {String} code Language code like sv_SE.
             * @param {Array} items Name/value array with English en_US to sv_SE.
             */
            add: function(code, items) {
                for (var name in items) {
                    data[name] = items[name];
                }
            },

            /**
             * Translates the specified text.
             *
             * It has a few formats:
             * I18n.translate("Text");
             * I18n.translate(["Text {0}/{1}", 0, 1]);
             * I18n.translate({raw: "Raw string"});
             *
             * @method translate
             * @param {String/Object/Array} text Text to translate.
             * @return {String} String that got translated.
             */
            translate: function(text) {
                if (typeof(text) == "undefined") {
                    return text;
                }

                if (typeof(text) != "string" && "raw" in text) {
                    return text.raw;
                }

                if (text.push) {
                    var values = text.slice(1);

                    text = (data[text[0]] || text[0]).replace(/\{([^\}]+)\}/g, function(match1, match2) {
                        return values[match2];
                    });
                }

                return data[text] || text;
            },

            data: data
        };
    });

// Included from: js/moxman/Loader.js

    /**
     * Loader.js
     *
     * Copyright 2003-2013, Moxiecode Systems AB, All rights reserved.
     */

    /*global moxman:true */

    /**
     * ...
     */
    define("avalade/Loader", [
        "avalade/util/Loader",
        "avalade/Env",
        "avalade/util/I18n"
    ], function(ResourceLoader, Env, I18n) {
        var exports = this || window;

        function loadAndRender(view) {
            return function(settings) {
                settings = settings || {};

                if (!Env.baseUrl) {
                    var scripts = document.getElementsByTagName('script');
                    for (var i = 0; i < scripts.length; i++) {
                        var src = scripts[i].src;

                        if (/(^|\/)avalade\./.test(src)) {
                            Env.baseUrl = src.substring(0, src.lastIndexOf('/')) + '/..';
                            Env.apiPageUrl = Env.baseUrl + "/" + Env.apiPageName;
                            break;
                        }
                    }
                }

                function done() {
                    // moxman.Env gets overwritten when loading the whole API
                    // so lets stick in the baseURL again
                    avalade.Env.baseUrl = Env.baseUrl;
                    avalade.Env.apiPageUrl = Env.baseUrl + "/" + Env.apiPageName;
                    avalade.util.JsonRpc.url = avalade.Env.apiPageUrl;
                    avalade.ui.Control.translate = I18n.translate;
                    avalade.ui.FloatPanel.zIndex = settings.zIndex || avalade.ui.FloatPanel.zIndex;

                    var manager = new avalade.Manager(settings);
                    manager.init(new avalade.views[view](manager));
                }

                if (!avalade.ui) {
                    ResourceLoader.load({
                        js: [
                            Env.baseUrl + "/js/moxman.api.min.js",
                            Env.apiPageUrl + "?action=language" + (settings.language ? '&code=' + settings.language : ''),
                            Env.apiPageUrl + "?action=PluginJs"
                        ],
                        css: [Env.baseUrl + "/skins/lightgray/skin" + (Env.ie7 ? ".ie7" : "") + ".min.css"]
                    }, done);
                } else {
                    done();
                }
            };
        }

        var Loader = {
            browse: loadAndRender("FileListView"),
            upload: loadAndRender("UploadView"),
            edit: loadAndRender("EditView"),
            zip: loadAndRender("ZipView"),
            createDir: loadAndRender("CreateDirView"),
            createDoc: loadAndRender("CreateDocView"),
            view: loadAndRender("ViewFileView"),
            rename: loadAndRender("RenameView")
        };

        // Expose loader methods onto moxman root namespace
        exports.avalade = exports.avalade || {};
        for (var name in Loader) {
            exports.avalade[name] = Loader[name];
        }

        exports.avalade.addI18n = I18n.add;

        return Loader;
    });

// Included from: js/moxman/interop/TinyMcePlugin.js

    /**
     * TinyMcePlugin.js
     *
     * Copyright 2003-2013, Moxiecode Systems AB, All rights reserved.
     */

    /*global tinymce:true */

    /**
     * ...
     */
    define("avalade/avaMediaSelector", [
        "avalade/Loader",
        "avalade/Env"
    ], function(Loader, Env) {
        tinymce.PluginManager.add('avaMediaSelector', function(editor, url) {
            var editorSettings = editor.settings;

            //Env.baseUrl = url;
            //Env.apiPageUrl = url + "/" + Env.apiPageName;

            function getBrowseSettings(type) {
                var browseSettings = {};

                // Extend with prefixed editor settings
                tinymce.each(editorSettings, function(value, key) {
                    if (key.indexOf('avamedia_') === 0) {
                        browseSettings[key.substring(13)] = value;
                    }
                });

                // Extend with type specifc settings and remove any prefix
                var typeSettings = editorSettings['avamedia_' + type + '_settings'];
                if (typeSettings) {
                    tinymce.each(typeSettings, function(value, key) {
                        key = key.replace(/avamediar_/g, '');
                        browseSettings[key] = value;
                    });
                }

                return browseSettings;
            }

            /*
            editorSettings.file_browser_callback = function(id, value, type, win) {
                var zIndex = editor.windowManager.zIndex; // TinyMCE 3
                var data = {};
                var types = {
                    "image": {"value": 1, "label": "image"},
                    "file": {"value": 2, "label": "link"},
                    "media": {"value": 3, "label": "video"},
                };
                var params = "&field_id=" + encodeURIComponent(id) + "&type=" + types[type].value;

                // TinyMCE 4
                if (tinymce.ui.FloatPanel) {
                    zIndex = tinymce.ui.FloatPanel.currentZIndex;
                }

                var body = [];

                if(avaMediaSelectorSettings.url1 != undefined && avaMediaSelectorSettings.url1 != '') {
                    body.push({
                        title: avaMediaSelectorSettings.title == '' ? 'Shared Files' : avaMediaSelectorSettings.title,
                        type: 'iframe',
                        minWidth: 950,
                        minHeight: 500,
                        url: avaMediaSelectorSettings.url1 + params
                    });
                }

                if(avaMediaSelectorSettings.url2 != undefined && avaMediaSelectorSettings.url2 != '') {
                    body.push({
                        title: 'Page Specific Files',
                        type: 'iframe',
                        minWidth: 950,
                        minHeight: 500,
                        url: avaMediaSelectorSettings.url2 + params

                    });
                }

                editor.windowManager.open({
                    title: 'Insert/edit ' + types[type].label,
                    data: data,
                    bodyType: 'tabpanel',
                    body: body,
                    buttons: []
                }, {
                    body: $(document).find('body')[0],
                    pane: zIndex-1,
                    panel: tinymce.ui.FloatPanel,

                });

                //window.console.dir($(document).find('body')[0].innerHTML);

//                Loader.browse(tinymce.extend({
//                    zIndex: zIndex,
//                    url: editor.documentBaseURI.toAbsolute(value),
//                    document_base_url: editorSettings.document_base_url,
//                    view: type == "image" || type == "media" ? "thumbs" : "files",
//                    multiple: false,
//                    oninsert: function(args) {
//                        var fieldElm = win.document.getElementById(id);
//
//                        fieldElm.value = editor.convertURL(args.focusedFile.meta.url, null, null);
//
//                        if ("fireEvent" in fieldElm) {
//                            fieldElm.fireEvent("onchange");
//                        } else {
//                            var evt = document.createEvent("HTMLEvents");
//                            evt.initEvent("change", false, true);
//                            fieldElm.dispatchEvent(evt);
//                        }
//                    }
//                }, getBrowseSettings(type)));
            };
            */

            editorSettings.file_picker_callback = function(callback, value, meta) {
                var types = {
                    "image": {"value": 1, "label": "image"},
                    "file": {"value": 2, "label": "link"},
                    "media": {"value": 3, "label": "video"},
                };
                var params = "&type=" + types[meta.filetype].value;

                var body = {
                    type: 'tabpanel',
                    tabs: []
                };

                if(avaMediaSelectorSettings.url1 != undefined && avaMediaSelectorSettings.url1 != '') {
                    body.tabs.push({
                        title: avaMediaSelectorSettings.title == '' ? 'Shared Files' : avaMediaSelectorSettings.title,
                        items: [{
                            type: 'htmlpanel',
                            html: '<iframe src="' + (avaMediaSelectorSettings.url1 + params).replace(/&/g, "&amp;") + '"  frameborder="0" style="width: 100%; height: 552px"></iframe>'
                        }]
                    });
                }

                if(avaMediaSelectorSettings.url2 != undefined && avaMediaSelectorSettings.url2 != '') {
                    body.tabs.push({
                        title: 'Page Specific Files',
                        items: [{
                            type: 'htmlpanel',
                            html: '<iframe src="' + (avaMediaSelectorSettings.url2 + params).replace(/&/g, "&amp;") + '"  frameborder="0" style="width: 100%; height: 552px"></iframe>'
                        }]
                    });
                }

                editor.windowManager.open({
                    title: 'Insert/edit ' + types[meta.filetype].label,
                    body: body,
                    buttons: [],
                    size: 'large'
                });

                window.callback = function(value, meta) {
                    callback(value, meta);
                    editor.windowManager.close();
                    delete window.callback;
                };
            }

            function replace(template, data, escapeFuncs) {
                if (typeof(template) != 'string') {
                    return template(data, escapeFuncs);
                }

                function resolve(data, path) {
                    var i, res;

                    for (i = 0, res = data, path = path.split('.'); i < path.length; i++) {
                        res = res[path[i]];
                    }

                    return res;
                }

                // Replace variables
                template = '' + template.replace(/\{\$([^\}]+)\}/g, function(match, variableName) {
                    var i, parts = variableName.split('|'), value = resolve(data, parts[0]);

                    if (typeof(value) == 'undefined') {
                        return '';
                    }

                    // Default encoding
                    if (parts.length == 1 && escapeFuncs && escapeFuncs.xmlEncode) {
                        value = escapeFuncs.xmlEncode(value);
                    }

                    // Execute encoders
                    for (i = 1; i < parts.length; i++) {
                        value = escapeFuncs[parts[i]](value, data, variableName);
                    }

                    return value;
                });

                // Execute functions
                template = template.replace(/\{\=([\w]+)([^\}]+)\}/g, function(match, funcName, args) {
                    return resolve(escapeFuncs, funcName)(data, funcName, args);
                });

                return template;
            }

            editor.addCommand('mceInsertFile', function() {
                var selection = editor.selection, lastRng;

                lastRng = selection.getRng();

                function processTemplate(template, file) {
                    return replace(
                        template,
                        file,
                        {
                            urlencode: function(value) {
                                return encodeURIComponent(value);
                            },

                            xmlEncode: function(value) {
                                return tinymce.DOM.encode(value);
                            },

                            sizeSuffix: function(value) {
                                if (value == -1) {
                                    return '';
                                }

                                if (value > 1048576) {
                                    return Math.round(value / 1048576, 1) + " MB";
                                }

                                if (value > 1024) {
                                    return Math.round(value / 1024, 1) + " KB";
                                }

                                return value + " b";
                            }
                        }
                    );
                }

                var zIndex = editor.windowManager.zIndex; // TinyMCE 3

                // TinyMCE 4
                if (tinymce.ui.FloatPanel) {
                    zIndex = tinymce.ui.FloatPanel.currentZIndex;
                }

                Loader.browse(tinymce.extend({
                    zIndex: zIndex,
                    document_base_url: editorSettings.document_base_url,
                    oninsert: function(args) {
                        var html = '';

                        tinymce.each(args.files, function(file, i) {
                            var isImage = /\.(gif|jpe?g|png)$/i.test(file.name);

                            selection.setRng(lastRng);

                            // Create link on selection
                            if (!isImage && !selection.isCollapsed()) {
                                editor.execCommand('mceInsertLink', file.meta.url);
                                return false;
                            }

                            if (i > 0) {
                                html += ' ';
                            }

                            // Create image/file template
                            if (isImage) {
                                html += processTemplate(editor.getParam(
                                    'moxiemanager_image_template',
                                    '<img src="{$meta.url}" ' +
                                        'width="{$meta.width}" height="{$meta.height}">'
                                ), file);
                            } else {
                                html += processTemplate(editor.getParam('moxiemanager_file_template', '<a href="{$url}">{$name}</a>'), file);
                            }
                        });

                        selection.setRng(lastRng);
                        editor.execCommand('mceInsertContent', false, html);
                    }
                }, getBrowseSettings()));
            });

            editor.ui.registry.addButton('insertfile', {
                icon: 'browse',
                title: 'Insert file',
                onAction: 'mceInsertFile'
            });
        });
    });

    expose(["avalade/util/Loader","avalade/Env","avalade/util/I18n","avalade/Loader","avalade/avaMediaSelector"]);
})(this);