<?php
namespace LayerOne;

/**
 * Class InstallationActivationCest
 *
 * @package LayerOne
 */
class InstallationActivationCest {

	/**
	 * Plugin name.
	 *
	 * @var string $plugin
	 */
	private $slug = 'wp-smushit';

	/**
	 * Executed before tests.
	 *
	 * @param \FunctionalTester $I  Functional tester.
	 */
	public function _before( \FunctionalTester $I ) {
		$I->loginAsAdmin();
		$I->amOnPluginsPage();
		$I->deactivatePlugin( $this->slug );
	}

	/**
	 * Executed after tests.
	 *
	 * @param \FunctionalTester $I  Functional tester.
	 */
	public function _after( \FunctionalTester $I ) {}

	/**
	 * TODO: Enable WP_DEBUG with error log via wp-config.php
	 * Install beta via FTP and activate, check DEBUG log for errors, warnings, notices
	 * Install beta via Plugins > Add New > Upload and activate, check DEBUG log for errors, warnings, notices
	 * If a plugin has FREE and PRO versions, test if activating PRO will automatically deactivate the FREE
	 */

	/**
	 * Test manual upload to plugins folder and activation (simulate FTP).
	 *
	 * @param \FunctionalTester $I  Functional tester.
	 */
	public function tryToActivate( \FunctionalTester $I ) {
		$I->seePluginDeactivated( $this->slug );
		$I->activatePlugin( $this->slug );
		$I->seePluginActivated( $this->slug );
		$I->canSee( 'Selected plugins activated.' );
	}

}
