$(window).bind('load', function() {
    function handleChanges(id){
        var t = $('.nav-tabs a[href="#' + id + '"]');
        if(t.length == 1) {
            var currentActive = t.parentsUntil('.nav-tabs', '.active');
            if(currentActive.find('a').attr('href') != '#' + id) {
                currentActive.removeClass('active');

                var content = $('#' + id);
                var content_parent = content.parentsUntil('.tab-content', '.active').removeClass('active');

                t.tab('show');
            }
        }
    }

    hasher.changed.add(handleChanges);      // Add hash change listener
    hasher.initialized.add(handleChanges);  // Add initialized listener (to grab initial value in case it is already set)
    hasher.init();

    // Change hash value (generates new history record)
    $('a[data-toggle=tab]').on('shown.bs.tab', function (e) {
        var href = $(e.target).attr('href');
        if(href.match(/^#/)) {
            hasher.setHash(href.substr(1));
        }
    });
});

// fire login box if seesion timeout
$(document).ajaxSuccess(function(e, request, setting){
    try {
        var json = $.parseJSON(request.responseText);
        if(json && json.result != undefined && json.result == "session_timeout") {
            var content = json.content;

            // create a unique empty div
            var id = uniqueID();
            var div = $('<div id="' + id + '" class="ajaxException modal hide fade" tabindex="-1" role="dialog">'
                + '<div class="modal-dialog">'
                + '<div class="modal-content">'
                + '</div><!-- /.modal-content -->'
                + '</div><!-- /.modal-dialog -->'
                + '</div>');

            var content = div.find('.modal-content');

            var header = $('<div class="modal-header">'
                + '<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>'
                + '</div>').appendTo(content);

            var body = $('<div class="modal-body"></div>').appendTo(content);
            body.html(json.content);

            var form = body.find('form');

            var header_text = body.find('h2').text();
            if(header_text != null && header_text != '') {
                header.prepend($('<h3>' + header_text + '</h3>'));
                body.find('h2').remove();
            }

            div.modal('show');
        }
    }
    catch (e) {
    }
});
