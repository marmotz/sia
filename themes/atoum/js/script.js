var positionToc;

$(window).resize(
    function() {
        var isFixed = $('#toc').hasClass("fixed");
        $('#toc').removeClass("fixed");

        positionToc = $('#toc').offset().top;

        if(isFixed) {
            $('#toc').addClass("fixed");
        }

        resizeToc();
        displayCurrentTocElement();
    }
).resize();

function resizeToc() {
    if($(window).width() >= 768) {
        var scrollTop = $(window).scrollTop();
        var windowHeight = $(window).height();
        var menuHeight = windowHeight - 30;

        if (scrollTop >= positionToc) {
            $('#toc').addClass("fixed");

            var footerHeight = 0;
            var footerTop = $('.footer-container').offset().top - 30;
            var windowBottom = scrollTop + windowHeight;

            if(windowBottom >= footerTop) {
                footerHeight = windowBottom - footerTop;
            }

            $('#toc').height(menuHeight - footerHeight);
        }
        else {
            $('#toc').removeClass("fixed");
            $('#toc').height(menuHeight - positionToc + scrollTop);
        }
    }
    else {
        $('#toc').height('auto');
    }
}

function displayCurrentTocElement() {
    if($(window).width() >= 768) {
        var scrollTop = $(window).scrollTop();

        $('article#page > *').each(
            function() {
                var $this   = $(this);

                if($this.offset().top >= scrollTop) {
                    var $header = $this;

                    if($header[0].tagName == 'HEADER') {
                        // continue
                        return true;
                    }

                    while(!$header[0].tagName.match(/H[0-6]/)) {
                        $header = $header.prev();
                    }

                    $('#toc a.active').removeClass('active');
                    var $tocAnchor = $('#toc a#toc-' + $header.attr('id'));

                    $tocAnchor.addClass('active');

                    $('#toc').scrollTop(
                        $('#toc').scrollTop() -
                        (($('#toc').outerHeight(true) / 2) - $('#toc a.active').position().top)
                    );

                    // break loop
                    return false;
                }
            }
        );
    }
    else {
        $('#toc a.active').removeClass('active');
    }
}

$(window).scroll(
    function() {
        resizeToc();
        displayCurrentTocElement();
    }
).scroll();