<?php
/**
 * @package     Joomla.Tests
 * @subpackage  Acceptance.tests
 *
 * @copyright   (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Tests for sample data in frontend.
 *
 * @since   4.0.0
 */
class CheckSampleDataCest
{
	/**
	 * Test sample data in frontend.
	 *
	 * @param   mixed   AcceptanceTester  $I  I
	 *
	 * @return void
	 *
	 * @since   4.0.0
	 *
	 * @throws Exception
	 */
	public function CheckSampleData(AcceptanceTester $I)
	{
		$I->wantToTest('that sample data were properly installed.');
		$I->amOnPage('index.php/blog');
		$I->see('Blog', 'h1');
		$I->seeElement(['xpath' => "//div[contains(@class,'items-leading boxed columns-2')]"]);
	}
}
