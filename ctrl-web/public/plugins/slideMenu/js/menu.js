function MouserClick() {
	jQuery( function($) {
		/**
		 * Most jQuery.localScroll's settings, actually belong to jQuery.ScrollTo, check it's demo for an example of each option.
		 * @see http://flesler.demos.com/jquery/scrollTo/
		 * You can use EVERY single setting of jQuery.ScrollTo, in the settings hash you send to jQuery.LocalScroll.
		 */

		// The default axis is 'y', but in this demo, I want to scroll both
		// You can modify any default like this
		$.localScroll.defaults.axis = 'xy';

		// Scroll initially if there's a hash (#something) in the url
		$.localScroll.hash( {
			target : '#content', // Could be a selector or a jQuery object too.
			queue : true,
			duration : 2000
		});

		/**
		 * NOTE: I use $.localScroll instead of $('#navigation').localScroll()
		 * so I also affect the >> and << links. I want every link in the page
		 * to scroll.
		 */
		$.localScroll( {
			target : '#content', // could be a selector or a jQuery object too.
			queue : true,
			duration : 2000,
			hash : true,
			onBefore : function(e, anchor, $target) {
				// The 'this' is the settings object, can be modified
		},
		onAfter : function(anchor, settings) {
			// The 'this' contains the scrolled element (#content)
		}
		});

	});

	$('#navigation ul').hide();
	$('#navigation ul:first').show();
	$('#navigation li a').click( function() {
		var checkElement = $(this).next();
		if ((checkElement.is('ul')) && (checkElement.is(':visible'))) {
			return false;
		}
		if ((checkElement.is('ul')) && (!checkElement.is(':visible'))) {
			$('#navigation ul:visible').slideUp('normal');
			checkElement.slideDown('normal');
			return false;
		}
	});

}

function bodyCarregaMenuCarros() {
	if ((document.getElementById("navigation") == null)
			&& (document.getElementById("content") == null)) {
		window.setTimeout( function() {
			bodyCarregaMenuCarros();
		}, 80);
	} else {
		MouserClick();
	}
}
