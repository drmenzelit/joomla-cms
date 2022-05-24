<?php
/**
 * @package     Joomla.Tests
 * @subpackage  Acceptance.tests
 *
 * @copyright   (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Tests for frontend.
 *
 * @since   4.0.0
 */
class CheckHomeCest
{
	/**
	 * Test if h1 is present in frontend.
	 *
	 * @param   mixed   AcceptanceTester  $I  I
	 *
	 * @return void
	 *
	 * @since   4.0.0
	 *
	 * @throws Exception
	 */
	public function CheckHeading(AcceptanceTester $I)
	{
		$I->wantToTest('that a h1 heading is present in frontend.');
		$I->amOnPage('index.php');
		$I->see('Home', 'h1');
	}
}
