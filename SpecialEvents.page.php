<?php
/**
 * Special page to display events.
 * Events must be stored in articles with [[Category:Events]] and a category
 * for the date, e.g. [[Category:2006/07/12]].
 * The article can use any name, for example using subpages "Events/2006/07/12/Party at my house".
 * Only the last part of the name is shown when displaying the title.
 * This special page also uses my calendar extension to display a calendar.
 *
 * @file
 * @ingroup Extensions
 * @author Barrylb -- original code
 * @author Jack Phoenix <jack@shoutwiki.com>
 * @author Misza <misza@shoutwiki.com>
 * @author Ryan Schmidt <skizzerz@shoutwiki.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:Calendar_(Barrylb) Documentation
 */
class SpecialEvents extends IncludableSpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'Events' );
	}

	/**
	 * Show the special page
	 *
	 * @param mixed|null $par Parameter passed to the page, if any
	 */
	public function execute( $par ) {
		global $wgParser;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$year = $request->getInt( 'year' );
		$month = $request->getInt( 'month' );
		$month = ( $month < 10 ? '0' . $month : $month );
		$day = $request->getInt( 'day' );

		wfSuppressWarnings();
		if ( $year == '' ) {
			$year = date( 'Y' );
		}
		if ( $month == '' || $month == '00' ) {
			$month = date( 'm' );
		}
		wfRestoreWarnings();

		if ( $request->getVal( 'category' ) ) {
			$catname = $request->getVal( 'category' );
		} else {
			$catname = $this->msg( 'events-categoryname' )->inContentLanguage()->plain();
		}

		# Don't show the navigation if we're including the page
		if ( !$this->mIncluding ) {
			$this->setHeaders();
			$out->addModules( 'ext.calendar' );
			$out->addWikiMsg( 'events-header' );
		}

		if ( $day == '' ) {
			$mwCalendar = new mwCalendar();
			$mwCalendar->dateNow( $month, $year );
			$mwCalendar->showUpcoming( false );
			$mwCalendar->setAjaxPrevNext( false );
			$mwCalendar->setCategoryName( htmlspecialchars( $catname ) );
			$out->addHTML( $mwCalendar->showThisMonth() );
			$day = '__';
		}

		// Build the SQL query
		$dbr = wfGetDB( DB_SLAVE );
		$sPageTable = $dbr->tableName( 'page' );
		$categorylinks = $dbr->tableName( 'categorylinks' );
		$catname_safe = $dbr->strencode( $catname );
		$year = intval( $year );
		$month = intval( $month );
		$day = intval( $day );
		// @todo FIXME: query almost identical to mwCalendar::getUpcomingEvents()!
		$sSqlSelect = "SELECT page_namespace, page_title, page_id, clike1.cl_to AS catlike1 ";
		$sSqlSelectFrom = "FROM $sPageTable INNER JOIN $categorylinks AS c1 ON page_id = c1.cl_from AND c1.cl_to='$catname_safe' INNER JOIN $categorylinks " .
			"AS clike1 ON page_id = clike1.cl_from AND clike1.cl_to LIKE '$year/$month/$day'";
		$sSqlWhere = ' WHERE page_is_redirect = 0 ';
		$sSqlOrderby = ' ORDER BY catlike1 ASC';

		$res = $dbr->query(
			$sSqlSelect . $sSqlSelectFrom . $sSqlWhere . $sSqlOrderby,
			__METHOD__
		);

		// Informational message when there are no upcoming events (the SQL query
		// above returned zero results)
		if ( $dbr->numRows( $res ) == 0 ) {
			$out->addHTML( $this->msg( 'events-no-upcoming-events' )->parse() );
			return;
		}

		foreach ( $res as $row ) {
			$title = Title::makeTitle( $row->page_namespace, $row->page_title );
			$out->addHTML( '<div class="eventsblock">' );

			$title_text = $title->getSubpageText();
			$out->addHTML( '<b>' . Linker::linkKnown( $title, $title_text ) . '</b><br />' );

			$wl_article = new Article( $title );
			$wl = $wl_article->getContent();

			$parserOptions = ParserOptions::newFromUser( $user );
			$parserOptions->setEditSection( false );
			$parserOptions->setTidy( true );
			$parserOutput = $wgParser->parse( $wl, $title, $parserOptions );
			$previewHTML = $parserOutput->getText();

			$out->addHTML( $previewHTML );

			$out->addHTML( '</div>' );
		}
	}
}