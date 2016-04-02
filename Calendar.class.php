<?php
/**
 * Main class file for Calendar extension - shared between <calendar />
 * parser hook and Special:Events special page
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
class mwCalendar {
	private $today;
	private $day;
	private $month;
	private $year;
	private $previousMonth;
	private $previousYear;
	private $nextMonth;
	private $nextYear;

	/**
	 * @var bool $showUpcoming Should the list of upcoming events be shown?
	 */
	private $showUpcoming;

	/**
	 * @var bool $showCalendar Should the calendar be shown? (d'oh!)
	 */
	private $showCalendar;

	/**
	 * @var string $catname User-supplied category name from which to fetch event data
	 */
	private $catname;

	/**
	 * @var bool $ajaxPrevNext Use AJAX for previous/next links?
	 */
	private $ajaxPrevNext;

	/**
	 * @var int $wday_start Does the week start on Sunday (0) or Monday (1)?
	 */
	private $wday_start;

	/* Constructor */
	public function __construct() {
		global $wgCalendarWeekdayStart;
		$this->day = '1';
		$this->today = '';
		$this->month = '';
		$this->year = '';
		$this->previousMonth = '';
		$this->previousYear = '';
		$this->nextMonth = '';
		$this->nextYear = '';
		$this->wday_names = explode( '|', wfMessage( 'calendar-pipe-separated-weekdays' )->plain() );
		$this->wmonth_names = array(
			wfMessage( 'january' )->plain(),
			wfMessage( 'february' )->plain(),
			wfMessage( 'march' )->plain(),
			wfMessage( 'april' )->plain(),
			wfMessage( 'may_long' )->plain(),
			wfMessage( 'june' )->plain(),
			wfMessage( 'july' )->plain(),
			wfMessage( 'august' )->plain(),
			wfMessage( 'september' )->plain(),
			wfMessage( 'october' )->plain(),
			wfMessage( 'november' )->plain(),
			wfMessage( 'december' )->plain()
		);
		$this->ajaxPrevNext = true;
		$this->catname = wfMessage( 'events-categoryname' )->inContentLanguage()->plain();
		$this->wday_start = $wgCalendarWeekdayStart;
		$this->showCalendar = true; // Ever heard of sensible defaults?
	}

	/**
	 * @param bool $show Show upcoming events (true) or not (false)?
	 */
	function showUpcoming( $show ) {
		$this->showUpcoming = $show;
	}

	function showCalendar( $b = true ) {
		$this->showCalendar = $b;
	}

	function setAjaxPrevNext( $b ) {
		$this->ajaxPrevNext = $b;
	}

	function setCategoryName( $cn ) {
		$this->catname = $cn;
	}

	function dateNow( $month, $year ) {
		$this->month = intval( $month );
		$this->year = intval( $year );
		wfSuppressWarnings();
		$this->today = strftime( '%d', time() );
		wfRestoreWarnings();
		$this->previousMonth = $this->month - 1;
		$this->previousYear = $this->year - 1;
		$this->nextMonth = $this->month + 1;
		$this->nextYear = $this->year + 1;
	}

	//I hate this method. A lot.
	//This needs to be split into two methods, one that does queries and shit to fetch content, and
	//another that renders it into HTML. Also, this had XSS and SQL injects galore until version 1.9,
	//and I'm not entirely certain that they're all gone yet --skizzerz
	// ashley 28 February 2016: the new getEventsByDay() method sorta does that.
	// But only sorta. Still slightly better than the original one, I guess & hope.
	function showThisMonth() {
		$lastYear = ( $this->month == 1 ? $this->year - 1 : $this->year );
		$nextYear = ( $this->month == 12 ? $this->year + 1 : $this->year );
		$lastMonth = ( $this->month == 1 ? 12 : $this->month - 1 );
		$nextMonth = ( $this->month == 12 ? 1 : $this->month + 1 );

		$lastMonth = ( $lastMonth < 10 ? '0' . $lastMonth : $lastMonth );
		$nextMonth = ( $nextMonth < 10 ? '0' . $nextMonth : $nextMonth );

		$eventsByDay = $this->getEventsByDay();

		$events = SpecialPage::getTitleFor( 'Events' );
		$output = '';

		/**
		 * Show calendar
		 */
		if ( $this->showCalendar ) {
			$output .= '<table align="center" border="0" cellpadding="0" cellspacing="0" class="calendar">'; // @todo FIXME: HTML5 plz

			for ( $i = 0; $i < 7; $i++ ) {
				if ( ( ( ( $i + $this->wday_start ) % 7 ) == 6 ) || ( ( ( $i + $this->wday_start ) % 7 ) == 0 ) ) {
					$output .= '<col class="cal-weekend"/>';
				} else {
					$output .= '<col/>';
				}
			}

			// If we're using some category other than the default, pass the
			// category query string into any and all URLs constructed here
			if ( $this->catname != wfMessage( 'events-categoryname' )->inContentLanguage()->plain() ) {
				$catnameHref = array( 'category' => $this->catname );
			} else {
				$catnameHref = array();
			}

			$output .= '<tr class="calendarTop"><td>' .
				Linker::link(
					$events,
					'&lt;',
					array(
						'data-year' => $lastYear,
						'data-month' => $lastMonth,
						'data-upcoming' => (bool) $this->showUpcoming,
						'data-category' => $this->catname
					),
					array(
						'year' => $lastYear,
						'month' => $lastMonth
					) + $catnameHref
				) . '</td>
				<td colspan="5" class="cal-header"><center>' .
				Linker::link(
					$events,
					$this->wmonth_names[$this->previousMonth] . ' ' . $this->year,
					array(),
					array(
						'year' => $this->year,
						'month' => $this->month
					) + $catnameHref
				) .
				'</center></td>
				<td>' . Linker::link(
					$events,
					'&gt;',
					array(
						'data-year' => $nextYear,
						'data-month' => $nextMonth,
						'data-upcoming' => (bool) $this->showUpcoming,
						'data-category' => $this->catname
					),
					array(
						'year' => $nextYear,
						'month' => $nextMonth
					) + $catnameHref
				) . '</td>
					</tr>
					<tr class="calendarDayNames">';
			for ( $i = 0; $i < 7; $i++ ) {
				$output .= Html::rawElement( 'td', array(), $this->wday_names[( $i + $this->wday_start ) % 7] );
			}
			$output .= '</tr>';

			wfSuppressWarnings();
			$weekDay = date( 'w', mktime( 0, 0, 0, $this->month, 1, $this->year ) ); // get day of week 0-6 of first day of month (0 = Sunday thru 6=Saturday)
			wfRestoreWarnings();
			$weekDay = $weekDay - $this->wday_start;
			if ( $weekDay < 0 ) {
				$weekDay = 7 + $weekDay;
			}

			wfSuppressWarnings();
			$no_days = date( 't', mktime( 0, 0, 0, $this->month, 1, $this->year ) ); // number of days in month
			wfRestoreWarnings();
			$count = 1;
			$output .= '<tr>';
			for ( $i = 1; $i <= $weekDay; $i++ ) {
				$output .= '<td> </td>';
				$count++;
			}
			/**
			 * Every day is a link to that day
			 */
			wfSuppressWarnings();
			$todaysMonth = date( 'm' );
			$todaysYear = date( 'Y' );
			wfRestoreWarnings();
			for ( $i = 1; $i <= $no_days; $i++ ) {
				if( $count == 1 ) {
					$output .= '<tr>';
				}

				$dayNr = ( $i < 10 ? '0' . $i : $i );

				if ( isset( $eventsByDay[$dayNr] ) ) {
					$fullLink = Linker::link(
						$events,
						$i,
						array( 'title' => str_replace( '_', ' ', $eventsByDay[$dayNr] ) ),
						array(
							'year' => $this->year,
							'month' => $this->month,
							'day=' => $dayNr
						) + $catnameHref
					);
				} else {
					$fullLink = $i;
				}

				$cellClass = '';
				if ( ( $i == $this->today ) && ( $this->month == $todaysMonth ) && ( $this->year == $todaysYear ) ) {
					$cellClass .= ' cal-today';
				}

				if ( isset( $eventsByDay[$dayNr] ) ) {
					$cellClass .= ' cal-eventday';
				}

				$output .= Html::rawElement( 'td', array( 'class' => $cellClass ), $fullLink );

				if ( $count > 6 ) {
					$output .= '</tr>';
					$count = 1;
				} else {
					$count++;
				}
			}

			if ( $count > 1 ) {
				for ( $i = $count; $i <= 7; $i++ ) {
					$output .= '<td> </td>';
				}
				$output .= '</tr>';
			}
			$output .= '</table>';
		} // end if show calendar

		if ( $this->showUpcoming ) {
			/**
			 * Show upcoming events
			 */
			$output .= '<table align="center" border="0" cellpadding="0" cellspacing="0" class="calendarupcoming">' . // @todo FIXME: HTML5 plz
				'<tr><td class="calendarupcomingTop">' . wfMessage( 'events-upcoming' )->text() . '</td></tr>';

			$output .= $this->renderUpcomingEvents();

			$output .= '<tr><td class="calendarupcomingBottom">';
			if ( $this->catname != wfMessage( 'events-categoryname' )->inContentLanguage()->plain() ) {
				$output .= Linker::link(
					Title::makeTitle( NS_CATEGORY, $catnameHref['category'] ),
					wfMessage( 'calendar-more' )->text()
				);
			} else {
				$output .= Linker::linkKnown(
					$events,
					wfMessage( 'calendar-more' )->text()
				);
			}

			$output .= '</td></tr></table>';
		}

		return $output;
	}

	/**
	 * Fetch upcoming events from the database.
	 *
	 * @param string $dateForSQL Date to use in the SQL query [not yet implemented]
	 * @return array Array of upcoming events
	 */
	public function getUpcomingEvents( $dateForSQL = null ) {
		if ( $dateForSQL === null ) {
			// @todo FIXME: i18n compatibility!
			wfSuppressWarnings();
			$dateForSQL = date( 'Y/m/d' );
			wfRestoreWarnings();
		}
		$dbr = wfGetDB( DB_SLAVE );
		$catname = $dbr->strencode( $this->catname );
		$sql = "SELECT page_title, page_namespace, clike1.cl_to AS catlike1 " .
			"FROM {$dbr->tableName( 'page' )} INNER JOIN {$dbr->tableName( 'categorylinks' )} AS c1 ON page_id = c1.cl_from AND c1.cl_to='" . $catname . "' INNER JOIN {$dbr->tableName( 'categorylinks' )} " .
			"AS clike1 ON page_id = clike1.cl_from AND clike1.cl_to LIKE '____/__/__' AND clike1.cl_to >= '" . $dateForSQL . "' " .
			'WHERE page_is_redirect = 0 ' .
			'ORDER BY clike1.cl_to ASC ' .
			'LIMIT 5';
		$res = $dbr->query( $sql, __METHOD__ );

		$rowClass = 'calendarupcomingRow1';
		$output = array();

		foreach ( $res as $row ) {
			$title = Title::makeTitle( $row->page_namespace, $row->page_title );

			$eventDate = substr( $row->catlike1, 8, 2 ) . '-' .
				substr( $row->catlike1, 5, 2 ) . '-' .
				substr( $row->catlike1, 0, 4 );

			$output[] = array(
				'titleObj' => $title,
				'page_namespace' => $row->page_namespace,
				'page_title' => $row->page_title,
				'eventDate' => $eventDate,
				'rowClass' => $rowClass
			);

			$rowClass = 'calendarupcomingRow2';
		}

		return $output;
	}

	/**
	 * Get upcoming events for the current month.
	 *
	 * @return array
	 */
	public function getEventsByDay() {
		$dbr = wfGetDB( DB_SLAVE );
		$pageTable = $dbr->tableName( 'page' );
		$categorylinks = $dbr->tableName( 'categorylinks' );
		$catname = $dbr->strencode( $this->catname );
		//INPUT VALIDATION SUMMARY
		//$pageTable and $categorylinks are generated by MediaWiki software and thus are safe (no user input)
		//$catname is escaped from $this->catname above via $dbr->strencode()
		//$this->month and $this->year are guaranteed to be ints, but we wrap them in intval() anyway
		$res = $dbr->query(
			"SELECT page_title, clike1.cl_to AS catlike1 " .
			"FROM $pageTable INNER JOIN $categorylinks AS c1 ON page_id = c1.cl_from AND c1.cl_to='" . $catname . "' INNER JOIN $categorylinks " .
				"AS clike1 ON page_id = clike1.cl_from AND clike1.cl_to LIKE '" . intval( $this->year ) . "/" . intval( $this->month ) . "/__' " .
			"WHERE page_is_redirect = 0",
			__METHOD__
		);
		/*
		$res = $dbr->select(
			array( 'page', 'c1' => 'categorylinks', 'clike1' => 'categorylinks' ),
			array( 'page_title', 'clike1.cl_to AS catlike1' ),
			array( 'page_is_redirect' => 0 ),
			__METHOD__,
			array(),
			array(
				'c1' => array(
					'INNER JOIN', array(
						'page_id = c1.cl_from',
						'c1.cl_to' => $this->catname
					)
				),
				'clike1' => array(
					'INNER JOIN', array(
						'page_id = clike1.cl_from',
						'clike1.cl_to' . $dbr->buildLike( intval( $this->year ) . '/' . intval( $this->month ) . '/__' )
					)
				)
			)
		);
		*/

		$eventsByDay = array();

		foreach ( $res as $row ) {
			$dbDay = substr( $row->catlike1, 8, 2 );
			if ( isset( $eventsByDay[$dbDay] ) == '' ) {
				$eventsByDay[$dbDay] = substr( $row->page_title, 0, 200 );
			} else {
				$eventsByDay[$dbDay] = '*' . wfMessage( 'calendar-multiple-events' )->plain() . '*';
			}
		}

		return $eventsByDay;
	}

	/**
	 * Build the HTML output for displaying upcoming events.
	 *
	 * @return string HTML
	 */
	public function renderUpcomingEvents() {
		$events = $this->getUpcomingEvents();
		$output = '';

		foreach ( $events as $event ) {
			$title = $event['titleObj'];
			$titleText = $title->getSubpageText();
			$titleText = str_replace( '_', ' ', $titleText );

			$output .= '<tr><td class="' . $event['rowClass'] . '">' .
				Linker::linkKnown( $title, '&raquo; ' . $titleText . '<br />' . $event['eventDate'] ) .
			'</td></tr>';
		}

		return $output;
	}
}
