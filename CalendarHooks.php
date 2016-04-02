<?php
/**
 * Calendar hooks wrapper class
 * All functions are public and static.
 *
 * @file
 * @ingroup Extensions
 * @author Jack Phoenix <jack@shoutwiki.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:Calendar_(Barrylb) Documentation
 */
class CalendarHooks {

	/**
	 * Set up the new <calendar/> parser hook
	 *
	 * @param Parser $parser
	 * @return bool
	 */
	public static function registerTag( &$parser ) {
		$parser->setHook( 'calendar', array( 'CalendarHooks', 'createCalendar' ) );
		return true;
	}

	# The callback function for converting the input text to HTML output
	public static function createCalendar( $input, $args, $parser ) {
		global $wgRequest;

		/**
		 * Check if the date was supplied in $_GET parameter
		 * fallback on default this month
		 */
		$year = $wgRequest->getInt( 'year' );
		$month = $wgRequest->getInt( 'month' );
		if ( $month && ( isset( $year ) ) ) {
			$month = ( $month < 10 ? '0' . $month : $month );
			$year = $wgRequest->getInt( 'year' );
		} else {
			wfSuppressWarnings();
			$month = date( 'm' );
			$year = date( 'Y' );
			wfRestoreWarnings();
		}

		// Add CSS & JS
		$parser->getOutput()->addModules( 'ext.calendar' );

		$mwCalendar = new mwCalendar();
		$mwCalendar->dateNow( $month, $year );

		$categoryName = ( isset( $args['category'] ) ? $args['category'] : false );
		if ( $categoryName ) {
			$mwCalendar->setCategoryName( $categoryName ); //CATNAME NEVER GETS VALIDATED SO BE SURE TO ESCAPE IT UPON USE!!!!!
		}

		if (
			isset( $args['ajaxprevnext'] ) &&
			( $args['ajaxprevnext'] !== false || $args['ajaxprevnext'] == 'on' )
		)
		{
			$mwCalendar->setAjaxPrevNext( true );
		} else {
			$mwCalendar->setAjaxPrevNext( false );
		}

		if (
			isset( $args['upcoming'] ) &&
			( $args['upcoming'] !== false || $args['upcoming'] == 'on' )
		)
		{
			$mwCalendar->showUpcoming( true );
		} else {
			$mwCalendar->showUpcoming( false );
		}

		// Show calendar by default unless explicitly requested not to
		$mwCalendar->showCalendar( true );
		if ( isset( $args['calendar'] ) && $args['calendar'] == 'off' ) {
			$mwCalendar->showCalendar( false );
		}

		return $mwCalendar->showThisMonth();
	}

}