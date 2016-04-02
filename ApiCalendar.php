<?php
/**
 * Calendar API module
 *
 * @file
 * @ingroup API
 * @author Jack Phoenix <jack@shoutwiki.com>
 * @date 23 July 2013
 * @see https://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiCalendar extends ApiBase {

	/**
	 * Main entry point.
	 */
	public function execute() {
		$user = $this->getUser();

		// Get the request parameters
		$params = $this->extractRequestParams();

		$month = $params['month'];
		$year = $params['year'];
		$categoryName = $params['category'];
		$showUpcomingEvents = $params['upcoming'];

		// Ensure that we have all the parameters we need to proceed
		if (
			!$month || $month === null || !is_numeric( $month ) ||
			!$year || $year === null || !is_numeric( $year )
		)
		{
			$this->dieUsageMsg( 'missingparam' );
		}

		// Lack of the category parameter is not a fatal error as we have a
		// fallback for that...
		if ( !$categoryName || $categoryName === null ) {
			$categoryName = wfMessage( 'events-categoryname' )->inContentLanguage()->plain();
		}

		$mwCalendar = new mwCalendar();
		$mwCalendar->dateNow( $month, $year );
		$mwCalendar->setCategoryName( $categoryName );
		$mwCalendar->setAjaxPrevNext( true );
		if (
			!isset( $showUpcomingEvents ) ||
			isset( $showUpcomingEvents ) && $showUpcomingEvents == 'off'
		)
		{
			$mwCalendar->showUpcoming( false );
		} else {
			$mwCalendar->showUpcoming( true );
		}
		$mwCalendar->showCalendar( true );

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			array( 'result' => $mwCalendar->showThisMonth() )
		);

		return true;
	}

	/**
	 * @return string Human-readable module description
	 */
	public function getDescription() {
		return 'Calendar API';
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return array(
			'month' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 12,
			),
			'year' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true,
			),
			'category' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'upcoming' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			)
		);
	}

	// Describe the parameters
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'month' => 'Month (1 = January; 12 = December)',
			'year' => 'Year',
			'category' => 'Category name',
			'upcoming' => 'Whether to show upcoming events or not',
		) );
	}

	// Get examples
	public function getExamples() {
		return array(
			'api.php?action=calendar&year=2013&month=8&categoryName=Meetings&upcoming=off' => 'Shows events in August 2013 from the category "Meetings" without showing upcoming events',
		);
	}
}