<?php
/**
 * @package     Joomla.Tests
 * @subpackage  Acceptance.tests
 *
 * @copyright   (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Install Sample Data tests
 *
 * @since  4.0.0
 */
class InstallSampleDataCest
{

	/**
	 * Runs before every test.
	 *
	 * @param   mixed   AcceptanceTester  $I  I
	 *
	 * @since   4.0.0
	 *
	 * @throws Exception
	 */
	public function _before(AcceptanceTester $I)
	{
		$I->doAdministratorLogin();

	}

	/**
	 * Test that it is possible to select a single file.
	 *
	 * @param   mixed   AcceptanceTester  $I  I
	 *
	 * @since   4.0.0
	 *
	 * @throws Exception
	 */
	public function installBlogData(AcceptanceTester $I)
	{
		$I->wantToTest('that it is possible to install blog sample data');
		$I->amOnPage('administrator/index.php');
		$I->waitForJsOnPageLoad();
		$I->see('Sample Data');
		$I->click(['css' => 'button[data-type="blog"]']);
		$I->seeInPopup('Proceeding will install a sample data set into your Joomla website. This process can\'t be reverted once done.');
		$I->acceptPopup();
		$I->waitForText('Sample data installed.', 60);
	}
}
