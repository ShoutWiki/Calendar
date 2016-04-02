$( function() {
	// Hooked on body instead of directly on the selector so that the AJAX-y
	// "previous/next month" links will work after being clicked once and the
	// user is not unnecessarily taken to Special:Events
	$( 'body' ).on( 'click', 'tr.calendarTop td:not(.cal-header) a', function( event ) {
		event.preventDefault(); // Don't follow the link to Special:Events
		var that = $( this );
		$.get(
			mw.util.wikiScript( 'api' ),
			{
				action: 'calendar',
				month: that.data( 'month' ),
				year: that.data( 'year' ),
				category: that.data( 'category' ),
				upcoming: that.data( 'upcoming' ),
				format: 'json'
			},
			function( data ) {
				/**
				 * @todo FIXME: this is still a strong candidate for The Most
				 * Batshit Insane Code of the Year
				 * First a placeholder element is appended after the original
				 * calendar, and then the original calendar is removed.
				 * Then the new calendar is injected and finally placeholder is
				 * also removed.
				 */
				$( 'table.calendar' ).after( '<div id="calendar-js-placeholder" style="display:none;"></div>' );
				$( 'table.calendar' ).remove();
				$( 'table.calendarupcoming' ).remove(); // @todo Not good enough, this removes it only for every other request
				$( '#calendar-js-placeholder' ).after( data.calendar.result );
				$( '#calendar-js-placeholder' ).remove();
			}
		);
	} );
} );