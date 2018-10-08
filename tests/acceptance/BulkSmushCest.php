<?php
/**
 * Class BulkSmushCest
 *
 * Test Bulk Smush functionality
 *
 * @package AcceptanceTests
 */

/**
 * Class BulkSmushCest
 */
class BulkSmushCest {

	/**
	 * Prepare tests.
	 *
	 * @param AcceptanceTester $I
	 */
	public function _before( AcceptanceTester $I ) {
		$I->loginAsAdmin();
	}

	public function _after( AcceptanceTester $I ) {}

	/**
	 * Try Bulk Smush.
	 *
	 * @param AcceptanceTester $I
	 * @throws \Codeception\Exception\ModuleException
	 */
	public function tryBulkSmush( AcceptanceTester $I ) {
		// Disable auto Smush and CDN (if enabled).
		$I->updateInDatabase(
			'wp_options',
			array(
				'option_value' => serialize(
					array(
						'auto' => false,
						'cdn'  => false,
					)
				),
			),
			array(
				'option_name' => 'wp-smush-settings',
			)
		);

		// Upload images.
		$I->wantTo( 'Upload images to the media library' );
		$ids = $I->cliToArray( 'post list --post_type=attachment --post_title=image1 --field=ID' );

		// No image found.
		if ( empty( $ids ) ) {
			//$images = array( 'image1.jpeg', 'image2.jpeg', 'image3.jpeg' );
			$I->cli( 'media import ' . dirname( dirname( __FILE__ ) ) . '/_data/images/image1.jpeg' );
			$ids = $I->cliToArray( 'post list --post_type=attachment --post_title=image1 --field=ID' );
		}

		$I->amOnPage( '/wp-admin/admin.php?page=smush' );
		$I->seeElement( 'button', [ 'title' => 'Click to start Bulk Smushing images in Media Library' ] );
		$I->click( 'button.wp-smush-all' );

		$I->see( 'Bulk smush is currently running' );
		sleep( 5 );
		$I->see( 'All attachments have been smushed. Awesome!' );

		// Remove images.
		foreach ( $ids as $id ) {
			$I->wantTo( "Remove image with ID: {$id}" );
			$I->cli( "post delete {$id}" );
		}
	}

}
