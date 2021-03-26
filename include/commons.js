////////////////////////////////////////////////////////////////////////////////
// Description: Common functions                                              //
// Date: 2008-12-18                                                           //
////////////////////////////////////////////////////////////////////////////////

/**
 * Get checked elements (radio boxes or check boxes)
 *
 * @since   2007-02-09
 * @param   elements    The array of elements
 * @return  The checked elements
 */
function get_checked_elements( elements )
{
    var inputs = elements.tagName ? [elements] : elements;
    var outputs = new Array();
    for ( var i = 0; i < inputs.length; i++ )
    {
        if ( inputs[i].checked )
        {
            outputs.push( inputs[i] );
        }
    }
    return outputs;
}


/**
 * Generate a random password
 * http://www.php.net/rand
 *
 * @since   2007-02-09
 * @param   length  The length of password
 * @return  The random password
 */
function generate_password( length )
{
    var length = length || 8;
    var chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPRQSTUVWXY13456789';
    var code = '';
    while ( code.length < length )
    {
        code += chars.charAt( Math.floor(Math.random()*chars.length+1)-1 );
    }
    return code;
}

/**
 * Load an image into a placeholder
 *
 * @since   2008-12-12
 * @param   placeholder     The ID of placeholder, or the reference of placeholder
 * @param   url             The URL of the image
 * @param   duration        The duration of blending in milliseconds
 * @return  Success or not
 */
function load_image( placeholder, url, duration )
{
    var element = typeof placeholder == "string" ? document.getElementById( placeholder ) : placeholder;
    if ( element && element.nodeName && (element.nodeName.toLowerCase() == "img" || element.nodeName.toLowerCase() == "object") )
    {
        if ( !element.blending )
        {
            var node_name = element.nodeName.toLowerCase();

            if ( duration > 0 && element[node_name == "img" ? "src" : "data"] != url )
            {
                // Stop image rotation, if any
                if ( element.next && element.srcs.length > 0 )
                {
                    window.clearInterval( element.tid );
                }

                // Get the selected link and next selected link
                var old_selected_link, selected_link;
                var a = document.createElement( "a" );
                a.href = url;
                for ( var i = 0; i < document.links.length; i++ )
                {
                    var link = document.links[i];
                    if ( link.onclick )
                    {
                        if ( link.href == element[node_name == "img" ? "src" : "data"] )
                        {
                            link.className = "selected";
                            old_selected_link = link;
                        }
                        else if ( link.href == a.href )
                        {
                            link.className = "selected";
                            selected_link = link;
                        }
                        else
                        {
                            link.className = "unselected";
                        }
                    }
                }

                // Set current image as the background image
                var old_element = document.createElement( "span" );
                old_element.style.display = "inline-block";
                old_element.style.background = "transparent url(" + element[node_name == "img" ? "src" : "data"] + ") no-repeat scroll top left";
                element.parentNode.insertBefore( old_element, element );
                old_element.appendChild( element );
                element.style.opacity = 0;
                /*@cc_on
                @if (@_jscript_version)
                element.style.filter = "alpha(opacity=0)";
                @end
                @*/
                element.blending = true;

                // Set current image of selected link as the background image
                if ( old_selected_link )
                {
                    var old_selected_link_span = document.createElement( "span" );
                    var old_selected_link_img;
                    while ( old_selected_link.firstChild )
                    {
                        if ( old_selected_link.firstChild.nodeName.toLowerCase() == "img" )
                        {
                            old_selected_link_img = old_selected_link.firstChild;
                        }
                        old_selected_link_span.appendChild( old_selected_link.firstChild );
                    }
                    old_selected_link.appendChild( old_selected_link_span );
                    old_selected_link_span.style.display = "inline-block";
                    old_selected_link_span.style.lineHeight = 1;
                    old_selected_link_span.style.background = "transparent url(" + old_selected_link_img.src + ") no-repeat scroll 1px 1px";
                    old_selected_link_img.style.opacity = 1;
                    /*@cc_on
                    @if (@_jscript_version)
                    old_selected_link_img.style.filter = "alpha(opacity=100)";
                    @end
                    @*/
                    if ( old_selected_link_img.overlay )
                    {
                        old_selected_link_img.overlay.style.opacity = 0;
                        /*@cc_on
                        @if (@_jscript_version)
                        old_selected_link_img.overlay.style.filter = "alpha(opacity=0)";
                        @end
                        @*/
                    }
                }

                // Set current image of next selected link as the background image
                if ( selected_link )
                {
                    var selected_link_span = document.createElement( "span" );
                    var selected_link_img;
                    while ( selected_link.firstChild )
                    {
                        if ( selected_link.firstChild.nodeName.toLowerCase() == "img" )
                        {
                            selected_link_img = selected_link.firstChild;
                        }
                        selected_link_span.appendChild( selected_link.firstChild );
                    }
                    selected_link.appendChild( selected_link_span );
                    selected_link_span.style.display = "inline-block";
                    selected_link_span.style.lineHeight = 1;
                    selected_link_span.style.background = "transparent url(" + selected_link_img.src + ") no-repeat scroll 1px 1px";
                    selected_link_img.style.opacity = 0;
                    /*@cc_on
                    @if (@_jscript_version)
                    selected_link_img.style.filter = "alpha(opacity=0)";
                    @end
                    @*/
                    if ( selected_link_img.overlay )
                    {
                        selected_link_img.overlay.style.opacity = 1;
                        /*@cc_on
                        @if (@_jscript_version)
                        selected_link_img.overlay.style.filter = "alpha(opacity=100)";
                        @end
                        @*/
                    }
                }

                // Change opacity of image, so as to create a blending effect
                var duration_step = 25; // Duration of each step in milliseconds
                var opacity_step = duration_step / duration;
                var tid = window.setInterval( function()
                {
                    var new_opacity = parseFloat( element.style.opacity ) + opacity_step;
                    element.style.opacity = new_opacity;
                    /*@cc_on
                    @if (@_jscript_version)
                    element.style.filter = "alpha(opacity=" + ( new_opacity * 100 ) + ")";
                    @end
                    @*/

                    if ( old_selected_link )
                    {
                        old_selected_link_img.style.opacity = 1 - new_opacity;
                        /*@cc_on
                        @if (@_jscript_version)
                        old_selected_link_img.style.filter = "alpha(opacity=" + ( (1 - new_opacity) * 100 ) + ")";
                        @end
                        @*/
                        if ( old_selected_link_img.overlay )
                        {
                            old_selected_link_img.overlay.style.opacity = new_opacity;
                            /*@cc_on
                            @if (@_jscript_version)
                            old_selected_link_img.overlay.style.filter = "alpha(opacity=" + ( new_opacity * 100 ) + ")";
                            @end
                            @*/
                        }
                    }

                    if ( selected_link )
                    {
                        selected_link_img.style.opacity = new_opacity;
                        /*@cc_on
                        @if (@_jscript_version)
                        selected_link_img.style.filter = "alpha(opacity=" + ( new_opacity * 100 ) + ")";
                        @end
                        @*/
                        if ( selected_link_img.overlay )
                        {
                            selected_link_img.overlay.style.opacity = 1- new_opacity;
                            /*@cc_on
                            @if (@_jscript_version)
                            selected_link_img.overlay.style.filter = "alpha(opacity=" + ( (1 - new_opacity) * 100 ) + ")";
                            @end
                            @*/
                        }
                    }

                    // Blending finished, remove dummy element and remove opacity
                    if ( new_opacity >= 1 )
                    {
                        window.clearInterval( tid );
                        old_element.parentNode.insertBefore( element, old_element );
                        old_element.parentNode.removeChild( old_element );
                        element.style.opacity = "";
                        /*@cc_on
                        @if (@_jscript_version)
                        element.style.filter = "";
                        @end
                        @*/
                        element.blending = false;

                        if ( old_selected_link )
                        {
                            old_selected_link.className = "unselected";
                            while ( old_selected_link_span.firstChild )
                            {
                                old_selected_link.appendChild( old_selected_link_span.firstChild );
                            }
                            old_selected_link.removeChild( old_selected_link_span );
                            old_selected_link_img.style.opacity = "";
                            /*@cc_on
                            @if (@_jscript_version)
                            old_selected_link_img.style.filter = "";
                            @end
                            @*/
                            if ( old_selected_link_img.overlay )
                            {
                                old_selected_link_img.overlay.style.opacity = 1;
                                /*@cc_on
                                @if (@_jscript_version)
                                old_selected_link_img.overlay.style.filter = "alpha(opacity=100)";
                                @end
                                @*/
                            }
                        }

                        if ( selected_link )
                        {
                            selected_link.className = "selected";
                            while ( selected_link_span.firstChild )
                            {
                                selected_link.appendChild( selected_link_span.firstChild );
                            }
                            selected_link.removeChild( selected_link_span );
                            selected_link_img.style.opacity = "";
                            /*@cc_on
                            @if (@_jscript_version)
                            selected_link_img.style.filter = "";
                            @end
                            @*/
                            if ( selected_link_img.overlay )
                            {
                                selected_link_img.overlay.style.opacity = 0;
                                /*@cc_on
                                @if (@_jscript_version)
                                selected_link_img.overlay.style.filter = "alpha(opacity=0)";
                                @end
                                @*/
                            }
                        }

                        // Restart image rotation, if any
                        if ( element.next && element.srcs.length > 0 )
                        {
                            element.tid = window.setInterval( element.next, element.speed );
                        }
                    }
                }, duration_step );
            }

            element[node_name == "img" ? "src" : "data"] = url;
        }

        return true;
    }
    return false;
}

/**
 * Load a content into a placeholder
 *
 * @since   2009-03-02
 * @param   placeholder     The ID of placeholder, or the reference of placeholder
 * @param   url             The ID of content, or the reference of content
 * @return  Success or not
 */
function load_content( placeholder, content )
{
    var placeholder_element = typeof placeholder == "string" ? document.getElementById( placeholder ) : placeholder;
    var content_element = typeof content == "string" ? document.getElementById( content ) : content;
    if ( placeholder_element && content_element )
    {
        while ( placeholder_element.firstChild )
        {
            placeholder_element.removeChild( placeholder_element.firstChild );
        }
        for ( var i = 0; i < content_element.childNodes.length; i++ )
        {
            placeholder_element.appendChild( content_element.childNodes[i].cloneNode(true) );
        }
        return true;
    }
    return false;
}

/**
 * Scroll to a specify position
 *
 * @since   2009-02-25
 * @param   container       The ID of container, or the reference of container
 * @param   x               The target X position
 * @param   y               The target Y position
 * @return  Success or not
 */
function scroll_to( container, x, y, duration )
{
    var element = typeof container == "string" ? document.getElementById( container ) : container;
    if ( element )
    {
        x = Math.min( Math.max(x, 0), element.scrollWidth );
        y = Math.min( Math.max(y, 0), element.scrollHeight );
        if ( !element.scrolling && (element.scrollLeft != x || element.scrollTop != y) )
        {
            if ( duration > 0 )
            {
                element.scrolling = true;

                var duration_step = 25; // Duration of each step in milliseconds
                var current_step = 0;
                var total_steps = Math.ceil( duration / duration_step );
                var x_step = Math.floor( (x - element.scrollLeft) / (duration / duration_step) );
                var y_step = Math.floor( (y - element.scrollTop) / (duration / duration_step) );
                var tid = window.setInterval( function()
                {
                    if ( current_step < total_steps )
                    {
                        element.scrollLeft += x_step;
                        element.scrollTop += y_step;
                        current_step++;
                    }
                    else
                    {
                        window.clearInterval( tid );
                        element.scrolling = false;
                        element.scrollLeft = x;
                        element.scrollTop = y;
                    }
                }, duration_step );
            }

            else
            {
                element.scrollLeft = x;
                element.scrollTop = y;
            }
        }

        return true;
    }
    return false;
}

/**
 * jQuery plugins
 */
(function( $ ) {
    $.fn.extend( {
        /**
         * Make a list dynamic (insert add / remove buttons).
         *
         * @since   2012-05-15
         * @param   min         The minimum number of rows
         * @param   max         The maximum number of rows
         * @param   list_tag    The tag name of the list
         * @param   item_tag    The tag name of the item
         */
        dynamicList: function( options )
        {
            // Get options
            var defaults = {
                min: 1,
                max: Infinity,
                list_tag: "tbody",
                item_tag: "tr"
            };
            var options = $.extend( defaults, options );

            // Update the buttons
            function update( list )
            {
                var length = list.children().length;
                list.find( "button.dynamic_table_add" ).attr( "disabled", length >= options.max );
                list.find( "button.dynamic_table_remove" ).attr( "disabled", length <= options.min );
            }

            // Traverse the lists
            this.each( function() {
                var list = $(this);

                // Must be list
                if ( !list.is(options.list_tag) ) return false;

                // Traverse the items
                $.each( list.children(), function(i, item) {
                    var last_child = $( item ).children( ":last-child" );

                    // Add button
                    $( "<button type='button' class='btn dynamic_table_add'>+</button>" ).click( function() {
                        var item =  $( this ).closest( options.item_tag );
                        var new_item = item.clone( true, true );
                        new_item.find( "input, select, textarea" ).each( function(i, input) {
                            input.value = null;
                            if ( input.id )
                            {
                                input.id = Date.now();
                            }
                        } );
                        item.after( new_item );
                        update( list );
                    } ).appendTo( last_child );

                    // Remove button
                    $( "<button type='button' class='btn dynamic_table_remove'>" + String.fromCharCode(8722) + "</button>" ).click( function() {
                        var item =  $( this ).closest( options.item_tag );
                        item.remove();
                        update( list );
                    } ).appendTo( last_child );
                } );

                // Update the buttons
                update( list );

                return this;
            } );

            return this;
        },

        /**
         * Override the default serialize method which ignore input[type=file].
         * http://www.bram.us/2008/10/27/jqueryserializeanything-serialize-anything-and-not-just-forms/
         *
         * @since   2012-05-30
         */
        serialize: function()
        {
            var toReturn = [];
            var els = $( this ).find( ":input" ).get();

            $.each( els, function() {
                if ( this.name && !this.disabled && (this.checked || /select|textarea/i.test(this.nodeName) || !/button|checkbox|radio|reset|submit/i.test(this.type)) )
                {
                    var val = $(this).val();
                    toReturn.push( encodeURIComponent(this.name) + "=" + encodeURIComponent(val) );
                }
            } );

            return toReturn.join( "&" ).replace( /%20/g, "+" );
        }
    } );
})( jQuery );

/**
 * Time ticker
 */
var timeTicker;

// server time
timeTicker = function(start) {
    this.init(start);
}

timeTicker.prototype = {
    'init': function(start) {
        this.start_date = new Date();
        this.offset = (this.start_date.getTimezoneOffset() * 60 * 1000);
        this.start_ts = start + this.offset;
        this.current_ts = this.start_ts;

        var me = this;
        var updateFn = function() {
            me.tsUpdate();
        }

        setInterval(updateFn, 1000);
        this.tsUpdate();

        return this;
    },
    'tsUpdate': function() {
        var now = new Date();
        this.current_ts = now.getTime() - this.start_date.getTime() + this.start_ts;

        $(this).trigger('update', [new Date(this.current_ts), this.current_ts]);
    }
}

var uniqueID;

(function(){
    var UID = Date.now();

    uniqueID = function() {
        return (UID++).toString(36);
    };
})()
