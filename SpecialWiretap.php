<?php

class SpecialWiretap extends SpecialPage {

	public $mMode;

	public function __construct() {
		parent::__construct( 
			"Wiretap", // 
			"",  // rights required to view
			true // show in Special:SpecialPages
		);
	}
	
	function execute( $parser = null ) {
		global $wgRequest, $wgOut;

		list( $limit, $offset ) = wfCheckLimits();

		// $userTarget = isset( $parser ) ? $parser : $wgRequest->getVal( 'username' );
		$this->mMode = $wgRequest->getVal( 'show' );
		//$fileactions = array('actions...?');

		$wgOut->addHTML( $this->getPageHeader() );
		
		if ($this->mMode == 'dailytotals')
			$this->totals();
		else if ( $this->mMode = 'dailytotalschart' )
			$this->totalsChart2();
		else
			$this->hitsList();
			
	}
	
	public function getPageHeader() {
		global $wgRequest;
		
		// show the names of the different views
		$navLine = '<strong>' . wfMsg( 'wiretap-viewmode' ) . ':</strong> ';
		
		$links_messages = array( // pages
			'wiretap-hits'               => '',
			'wiretap-dailytotals'        => 'dailytotals',
		);
				
		$navLinks = array();
		foreach($links_messages as $msg => $query_param) {
			$navLinks[] = $this->createHeaderLink($msg, $query_param);
		}
		$navLine .= implode(' | ', $navLinks);
		
		if ( $wgRequest->getVal( 'filterUser' ) || $wgRequest->getVal( 'filterPage' ) ) {
			
			$WiretapTitle = SpecialPage::getTitleFor( 'Wiretap' );
			$navLine .= ' | ' . Xml::element( 'a',
				array( 'href' => $WiretapTitle->getLocalURL() ),
				wfMsg( 'wiretap-unfilter' )
			);

		}

		
		$out = Xml::tags( 'p', null, $navLine ) . "\n";
		
		return $out;
	}
	
	function createHeaderLink($msg, $query_param) {
	
		$WiretapTitle = SpecialPage::getTitleFor( 'Wiretap' );

		if ( $this->mMode == $query_param ) {
			return Xml::element( 'strong',
				null,
				wfMsg( $msg )
			);
		} else {
			$show = ($query_param == '') ? array() : array( 'show' => $query_param );
			return Xml::element( 'a',
				array( 'href' => $WiretapTitle->getLocalURL( $show ) ),
				wfMsg( $msg )
			);
		}

	}
	
	public function hitsList () {
		global $wgOut, $wgRequest;

		$wgOut->setPageTitle( 'Wiretap' );

		$pager = new WiretapPager();
		$pager->filterUser = $wgRequest->getVal( 'filterUser' );
		$pager->filterPage = $wgRequest->getVal( 'filterPage' );
		
		// $form = $pager->getForm();
		$body = $pager->getBody();
		$html = '';
		// $html = $form;
		if ( $body ) {
			$html .= $pager->getNavigationBar();
			$html .= '<table class="wikitable sortable" width="100%" cellspacing="0" cellpadding="0">';
			$html .= '<tr><th>Username</th><th>Page</th><th>Time</th><th>Referal Page</th></tr>';
			$html .= $body;
			$html .= '</table>';
			$html .= $pager->getNavigationBar();
		} 
		else {
			$html .= '<p>' . wfMsgHTML('listusers-noresult') . '</p>';
		}
		$wgOut->addHTML( $html );
	}
	
	public function totals () {
		global $wgOut;

		$wgOut->setPageTitle( 'Wiretap: Daily Totals' );

		$html = '<table class="wikitable"><tr><th>Date</th><th>Hits</th></tr>';
		// $html = $form;
		// if ( $body ) {
		
		// } 
		// else {
			// $html .= '<p>' . wfMsgHTML('listusers-noresult') . '</p>';
		// }
		// SELECT wiretap.hit_year, wiretap.hit_month, wiretap.hit_day, count(*) AS num_hits
		// FROM wiretap
		// WHERE wiretap.hit_timestamp>20131001000000 
		// GROUP BY wiretap.hit_year, wiretap.hit_month, wiretap.hit_day
		// ORDER BY wiretap.hit_year DESC, wiretap.hit_month DESC, wiretap.hit_day DESC
		// LIMIT 100000;
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			array('w' => 'wiretap'),
			array(
				"w.hit_year AS year", 
				"w.hit_month AS month",
				"w.hit_day AS day",
				"count(*) AS num_hits",
			),
			null, // CONDITIONS? 'wiretap.hit_timestamp>20131001000000',
			__METHOD__,
			array(
				"DISTINCT",
				"GROUP BY" => "w.hit_year, w.hit_month, w.hit_day",
				"ORDER BY" => "w.hit_year DESC, w.hit_month DESC, w.hit_day DESC",
				"LIMIT" => "100000",
			),
			null // join conditions
		);
		while( $row = $dbr->fetchRow( $res ) ) {
		
			list($year, $month, $day, $hits) = array($row['year'], $row['month'], $row['day'], $row['num_hits']);
			$html .= "<tr><td>$year-$month-$day</td><td>$hits</td></tr>";
		
		}
		$html .= "</table>";
		
		$wgOut->addHTML( $html );

	}

	public function totalsChart () {
		global $wgOut;

		$wgOut->setPageTitle( 'Wiretap: Daily Totals Chart' );
		$wgOut->addModules( 'ext.wiretap.charts' );

		$html = '<canvas id="wiretapChart" width="400" height="400"></canvas>';

		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			array('w' => 'wiretap'),
			array(
				"w.hit_year AS year", 
				"w.hit_month AS month",
				"w.hit_day AS day",
				"count(*) AS num_hits",
			),
			null, //'w.hit_timestamp > 20140801000000', //null, // CONDITIONS? 'wiretap.hit_timestamp>20131001000000',
			__METHOD__,
			array(
				"DISTINCT",
				"GROUP BY" => "w.hit_year, w.hit_month, w.hit_day",
				"ORDER BY" => "w.hit_year ASC, w.hit_month ASC, w.hit_day ASC",
				"LIMIT" => "100000",
			),
			null // join conditions
		);
		$previous = null;

		while( $row = $dbr->fetchRow( $res ) ) {
		
			list($year, $month, $day, $hits) = array($row['year'], $row['month'], $row['day'], $row['num_hits']);

			$currentDateString = "$year-$month-$day";
			$current = new DateTime( $currentDateString );
			
			while ( $previous && $previous->modify( '+1 day' )->format( 'Y-m-d') !== $currentDateString ) {
				$data[ $previous->format( 'Y-m-d' ) ] = 0;
			}

			$data[ $currentDateString ] = $hits;

			$previous = new DateTime( $currentDateString );
		}
		
		//$html .= "<pre>" . print_r( $data, true ) . "</pre>";
		$html .= "<script type='text/template-json' id='wiretap-data'>" . json_encode( $data ) . "</script>";

		$wgOut->addHTML( $html );

	}

	public function totalsChart2 () {
		global $wgOut;

		$wgOut->setPageTitle( 'Wiretap: Daily Totals Chart' );
		$wgOut->addModules( 'ext.wiretap.charts.nvd3' );

		$html = '<div id="wiretap-chart"><svg height="400px"></svg></div>';

		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			array('w' => 'wiretap'),
			array(
				"w.hit_year AS year", 
				"w.hit_month AS month",
				"w.hit_day AS day",
				"count(*) AS num_hits",
			),
			null, //'w.hit_timestamp > 20140801000000', //null, // CONDITIONS? 'wiretap.hit_timestamp>20131001000000',
			__METHOD__,
			array(
				"DISTINCT",
				"GROUP BY" => "w.hit_year, w.hit_month, w.hit_day",
				"ORDER BY" => "w.hit_year ASC, w.hit_month ASC, w.hit_day ASC",
				"LIMIT" => "100000",
			),
			null // join conditions
		);
		$previous = null;

		while( $row = $dbr->fetchRow( $res ) ) {
		
			list($year, $month, $day, $hits) = array($row['year'], $row['month'], $row['day'], $row['num_hits']);

			$currentDateString = "$year-$month-$day";
			$current = new DateTime( $currentDateString );
			
			while ( $previous && $previous->modify( '+1 day' )->format( 'Y-m-d') !== $currentDateString ) {
				$data[] = array(
					'x' => $previous->getTimestamp() * 1000, // x value timestamp in milliseconds
					'y' => 0, // y value = zero hits for this day
				);
			}

			$data[] = array(
				'x' => strtotime( $currentDateString ) * 1000, // x value time in milliseconds
				'y' => intval( $hits ),
			);

			$previous = new DateTime( $currentDateString );
		}
		
		$data = array(
			array(
				'key' => 'Daily Hits',
				'values' => $data,
			),
		);

		//$html .= "<pre>" . print_r( $data, true ) . "</pre>";
		$html .= "<script type='text/template-json' id='wiretap-data'>" . json_encode( $data ) . "</script>";

		$wgOut->addHTML( $html );
	}
}

class WiretapPager extends ReverseChronologicalPager {
	protected $rowCount = 0;
	public $filterUser;
	public $filterPage;
	
	function __construct() {
		parent::__construct();
		// global $wgRequest;
		// $this->filterUsers = $wgRequest->getVal( 'filterusers' );
		// $this->filterUserList = explode("|", $this->filterUsers);
		// $this->ignoreUsers = $wgRequest->getVal( 'ignoreusers' );
		// $this->ignoreUserList = explode("|", $this->ignoreUsers);
	}

	function getIndexField() {
		return "hit_timestamp";
	}
	
	function getExtraSortFields() {
		return array();
	}

	function isNavigationBarShown() {
		return true;
	}
	
	function getQueryInfo() {
		$conds = array();
		// if ( $this->filterUsers ) {
			// $includeUsers = "user_name in ( '";
			// $includeUsers .= implode( "', '", $this->filterUserList ) . "')";
			// $conds[] = $includeUsers;
		// }
		// if ( $this->ignoreUsers ) {
			// $excludeUsers = "user_name not in ( '";
			// $excludeUsers .= implode( "', '", $this->ignoreUserList ) . "')";
			// $conds[] = $excludeUsers;
		// }
		
		if ( $this->filterUser ) {
			$conds[] = "user_name = '{$this->filterUser}'";
		}
		if ( $this->filterPage ) {
			$conds[] = "page_name = '{$this->filterPage}'";
		}
		
		return array(
			'tables' => 'wiretap',
			'fields' => array( 
				'page_id',
				'page_name',
				'user_name',
				// "concat(substr(hit_timestamp, 1, 4),'-',substr(hit_timestamp,5,2),'-',substr(hit_timestamp,7,2),' ',substr(hit_timestamp,9,2),':',substr(hit_timestamp,11,2),':',substr(hit_timestamp,13,2)) AS hit_timestamp",
				'hit_timestamp',
				'referer_title',
			),
			'conds' => $conds
		);
	}

	function formatRow( $row ) {
		$userPage = Title::makeTitle( NS_USER, $row->user_name );
		$name = $this->getSkin()->makeLinkObj( $userPage, htmlspecialchars( $userPage->getText() ) );
		

		if ( $this->filterUser ) {
			// do nothing for now...
		}
		else {
			$url = Title::newFromText('Special:Wiretap')->getLocalUrl(
				array( 'filterUser' => $row->user_name )
			);
			$msg = wfMsg( 'wiretap-filteruser' );
			
			$name .= ' (' . Xml::element(
				'a',
				array( 'href' => $url ),
				$msg
			) . ')';
		}

		
		$pageTitle = Title::newFromID( $row->page_id );
		if ( ! $pageTitle )
			$pageTitle = Title::newFromText( $row->page_name );
		
		if ( ! $pageTitle )
			$page = $row->page_name; // if somehow still no page, just show text
		else
			$page = $this->getSkin()->link( $pageTitle );

			
		if ( $this->filterPage ) {
			// do nothing for now...
		}
		else {
			$url = Title::newFromText('Special:Wiretap')->getLocalUrl(
				array( 'filterPage' => $row->page_name )
			);
			$msg = wfMsg( 'wiretap-filterpage' );
			
			$page .= ' (' . Xml::element(
				'a',
				array( 'href' => $url ),
				$msg
			) . ')';
		}
		
		if ( $row->referer_title ) {
			$referer = Title::newFromText( $row->referer_title );
			$referer = $this->getSkin()->link( $referer );
		}
		else
			$referer = '';
		
		global $wgLang;
		$timestamp = $wgLang->timeanddate( wfTimestamp( TS_MW, $row->hit_timestamp ), true );

		return "<tr><td>$name</td><td>$page</td><td>$timestamp</td><td>$referer</td></tr>\n";
	}

	function getForm() {
		$out = '<form name="filteruser" id="filteruser" method="post">';
		$out .='Usernames: <input type="text" name="filterusers" value="' . $this->filterUsers . '">';
		$out .='<input type="submit" value="Filter">';
		$out .='&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		$out .='Usernames: <input type="text" name="ignoreusers" value="' . $this->ignoreUsers . '">';
		$out .='<input type="submit" value="Exclude">';
		$out .='</form><br /><hr /><br />';
		return $out;
	}

	/**
	 * Preserve filter offset parameters when paging
	 * @return array
	 */
	function getDefaultQuery() {
		$query = parent::getDefaultQuery();
		// if( $this->filterUsers != '' )
			// $query['filterusers'] = $this->filterUsers;
		// if( $this->ignoreUsers != '' )
			// $query['ignoreusers'] = $this->ignoreUsers;
		return $query;
	}

}
