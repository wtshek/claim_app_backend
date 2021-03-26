$(document).ready(function () {
    /*-- Animated Toggler Icon --*/
    var navbar_toggler = $( "button[data-target='#mainNavigation']" ).on('click', function () {
        $('.animated-toggler-icon').toggleClass('open');
    });

    /*-- Date Range Picker --*/
    /*
    var language = $( "html" ).attr( "lang" );
    if ( language == "zh-hans" || language == "zh-hant" )
    {
        language = language == "zh-hans" ? "cn" : "tc";
        $.dateRangePickerLanguages[language]["month-name"] = ["1月", "2月", "3月", "4月", "5月", "6月", "7月", "8月", "9月", "10月", "11月", "12月"];
    }
    $('#headerReservationForm,#mobileBookingMask,#BookingMask').dateRangePicker({
        language: language,
        separator: "–",
        singleMonth: $(document).width() < 576,
        startDate: new Date(),
        selectForward: true,
        getValue: function () {
            var form = $(this);
            if (form.find('.check-in').val() && form.find('.check-out').val())
                return form.find('.check-in').val() + form.find('.check-out').val();
            else
                return '';
        },
        setValue: function (s, s1, s2) {
            var form = $(this);
            form.find('.check-in').val(s1);
            form.find('.check-out').val(s2);
        },
        autoClose: true
    });

    // Change month names from "M月 YYYY" to "YYYY年M月"
    if ( window.MutationObserver && (language == "cn" || language == "tc") )
    {
        var observer = new MutationObserver( function(mutations) {
            mutations.forEach( function(mutation) {
                var target = $( mutation.target );
                var parts = target.text().split( " " );
                if ( parts.length == 2 ) 
                {
                    target.text( parts[1] + "年" + parts[0] );
                }
            } );
        } );
        var targets = $( ".date-picker-wrapper .month-name" );
        var options = {
            characterData: true,
            childList: true
        };
        observer.observe( targets[0], options );
        observer.observe( targets[1], options );
    }
    */

    /*-- Sticky Menu on desktop --*/
    var stickymenu = document.getElementById("mainNavbar");
    var stickymenuoffset = stickymenu.offsetTop;
    window.addEventListener("scroll", function (e) {
        requestAnimationFrame(function () {
            if (window.pageYOffset > stickymenuoffset) {
                stickymenu.classList.add('sticky');
            } else {
                stickymenu.classList.remove('sticky');
            }
        })
    });

    /*-- Main Navigation on desktop and tablet --*/
    // Click on dropdown visits the URL instead of toggling the dropdown when the dropdown is already shown
    $( "#mainNavigation .dropdown .nav-link" ).on( "click", function(e) {
        var hasContent = $(this).parent().hasClass("has-content");
        if ( !navbar_toggler.is(":visible") )
        {
            var toggle = $( this ).next();
            if ( toggle.attr("aria-expanded") == "false" || !hasContent )
            {
                toggle.dropdown( "toggle" );
                return false;
            }
            else if ( e.isTrigger )
            {
                location.href = this.href;
            }
        }
        else
        {
            return hasContent;
        }
    } );

    // Hover on dropdown toggles the dropdown
    $( "#mainNavigation .dropdown" ).hover( function(e) {
        var dropdown = $( this );
        var target = $( e.target );
        if ( !navbar_toggler.is(":visible") )
        {
            dropdown.children( "button[data-toggle=dropdown]" ).dropdown( "toggle" );
            dropdown.find( ".dropdown-item:has(.sr-only)" ).addClass( "active" );   // Restore missing active class
        }
        else
        {
            // https://bootstrapious.com/p/bootstrap-multilevel-dropdown
            var dropdown = $( this );
            dropdown.siblings().toggleClass( "show" );
            if ( !dropdown.next().hasClass("show") )
            {
                dropdown.parents( ".dropdown-menu" ).first().find( ".show" ).removeClass( "show" );
            }
            dropdown.parents( "li.nav-item.dropdown.show" ).on( "hidden.bs.dropdown", function(e) {
                $( ".dropdown-submenu .show").removeClass( "show" );
            } );
        }
    } )

    // Handle touchstart on dropdown as click
    // Needed for Chrome
    .on( "touchstart", function(e) {
        if ( !navbar_toggler.is(":visible") )
        {
            var target = $( e.target );
            if ( target.hasClass("nav-link") )
            {
                target.trigger( "click" );
                return false;   // https://stackoverflow.com/a/37130354
            }
            else if ( target.hasClass("dropdown-item") && !target.hasClass("dropdown-toggle") )
            {
                location.href = target.attr( "href" );
            }
        }
    } );

    /*-- Bootstrap components that use href to reference to another element --*/
    $( [
        ".carousel > .carousel-control-prev, .carousel > .carousel-control-next",   // Carousel
        ".collapse",                                                                // Collapse
        ".nav[role=tablist] > .nav-item > .nav-link[role=tab]"                      // Navs
    ].join(", ") ).each( function() {
        this.href = this.hash;
    } );

    /*-- Tooltip --*/
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    })

    /*-- Turn off smooth scrolling in IE and Edge -- */
    /*-- https://teamtreehouse.com/community/background-attachment-is-messed-up-in-ie-and-microsoft-edge -- */
    if(navigator.userAgent.match(/MSIE 10/i) || navigator.userAgent.match(/Trident\/7\./) || navigator.userAgent.match(/Edge\//)) {
        $('body').on("mousewheel", function () {
            event.preventDefault();
            var wd = event.wheelDelta;
            var csp = window.pageYOffset;
            window.scrollTo(0, csp - wd);
        });
    }
});
