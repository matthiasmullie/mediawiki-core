<?php
/**
 *
 *
 * Created on Jun 20, 2007
 *
 * Copyright © 2007 Roan Kattouw "<Firstname>.<Lastname>@gmail.com"
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * @ingroup API
 */
class ApiRollback extends ApiBase {

	/**
	 * @var Title
	 */
	private $mTitleObj = null;

	/**
	 * @var User
	 */
	private $mUser = null;

	public function execute() {
		$params = $this->extractRequestParams();

		// User and title already validated in call to getTokenSalt from Main
		$titleObj = $this->getRbTitle();
		$pageObj = WikiPage::factory( $titleObj );
		$summary = $params['summary'];
		$details = array();
		$retval = $pageObj->doRollback(
			$this->getRbUser(),
			$summary,
			$params['token'],
			$params['markbot'],
			$details,
			$this->getUser()
		);

		if ( $retval ) {
			// We don't care about multiple errors, just report one of them
			$this->dieUsageMsg( reset( $retval ) );
		}

		$this->setWatch( $params['watchlist'], $titleObj );

		$info = array(
			'title' => $titleObj->getPrefixedText(),
			'pageid' => intval( $details['current']->getPage() ),
			'summary' => $details['summary'],
			'revid' => intval( $details['newid'] ),
			'old_revid' => intval( $details['current']->getID() ),
			'last_revid' => intval( $details['target']->getID() )
		);

		$this->getResult()->addValue( null, $this->getModuleName(), $info );
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return array(
			'title' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'user' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'token' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'summary' => '',
			'markbot' => false,
			'watchlist' => array(
				ApiBase::PARAM_DFLT => 'preferences',
				ApiBase::PARAM_TYPE => array(
					'watch',
					'unwatch',
					'preferences',
					'nochange'
				),
			),
		);
	}

	public function getParamDescription() {
		return array(
			'title' => 'Title of the page you want to rollback.',
			'user' => 'Name of the user whose edits are to be rolled back. If ' .
				'set incorrectly, you\'ll get a badtoken error.',
			'token' => 'A rollback token previously retrieved through ' .
				"{$this->getModulePrefix()}prop=revisions",
			'summary' => 'Custom edit summary. If empty, default summary will be used',
			'markbot' => 'Mark the reverted edits and the revert as bot edits',
			'watchlist' => 'Unconditionally add or remove the page from your watchlist, ' .
				'use preferences or do not change watch',
		);
	}

	public function getResultProperties() {
		return array(
			'' => array(
				'title' => 'string',
				'pageid' => 'integer',
				'summary' => 'string',
				'revid' => 'integer',
				'old_revid' => 'integer',
				'last_revid' => 'integer'
			)
		);
	}

	public function getDescription() {
		return array(
			'Undo the last edit to the page. If the last user who edited the page made',
			'multiple edits in a row, they will all be rolled back'
		);
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'invalidtitle', 'title' ),
			array( 'notanarticle' ),
			array( 'invaliduser', 'user' ),
		) );
	}

	public function needsToken() {
		return true;
	}

	public function getTokenSalt() {
		return array( $this->getRbTitle()->getPrefixedText(), $this->getRbUser() );
	}

	private function getRbUser() {
		if ( $this->mUser !== null ) {
			return $this->mUser;
		}

		$params = $this->extractRequestParams();

		// We need to be able to revert IPs, but getCanonicalName rejects them
		$this->mUser = User::isIP( $params['user'] )
			? $params['user']
			: User::getCanonicalName( $params['user'] );
		if ( !$this->mUser ) {
			$this->dieUsageMsg( array( 'invaliduser', $params['user'] ) );
		}

		return $this->mUser;
	}

	/**
	 * @return Title
	 */
	private function getRbTitle() {
		if ( $this->mTitleObj !== null ) {
			return $this->mTitleObj;
		}

		$params = $this->extractRequestParams();

		$this->mTitleObj = Title::newFromText( $params['title'] );

		if ( !$this->mTitleObj || $this->mTitleObj->isExternal() ) {
			$this->dieUsageMsg( array( 'invalidtitle', $params['title'] ) );
		}
		if ( !$this->mTitleObj->exists() ) {
			$this->dieUsageMsg( 'notanarticle' );
		}

		return $this->mTitleObj;
	}

	public function getExamples() {
		return array(
			'api.php?action=rollback&title=Main%20Page&user=Catrope&token=123ABC',
			'api.php?action=rollback&title=Main%20Page&user=217.121.114.116&' .
				'token=123ABC&summary=Reverting%20vandalism&markbot=1'
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/API:Rollback';
	}
}
