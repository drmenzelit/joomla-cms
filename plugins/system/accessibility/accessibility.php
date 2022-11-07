<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.accessibility
 *
 * @copyright   (C) 2020 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt

 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * System plugin to add additional accessibility features to the administrator interface.
 *
 * @since  4.0.0
 */
class PlgSystemAccessibility extends CMSPlugin
{
    /**
     * @var    \Joomla\CMS\Application\CMSApplication
     *
     * @since  4.0.0
     */
    protected $app;

    /**
     * Add the javascript for the accessibility menu
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onBeforeCompileHead()
    {
        $section = $this->params->get('section', 'administrator');

        if ($section !== 'both' && $this->app->isClient($section) !== true) {
            return;
        }

        // Get the document object.
        $document = $this->app->getDocument();

        if ($document->getType() !== 'html') {
            return;
        }

        // Are we in a modal?
        if ($this->app->getInput()->get('tmpl', '', 'cmd') === 'component') {
            return;
        }

        // Load language file.
        $this->loadLanguage();

        // Determine if it is an LTR or RTL language
        $direction = Factory::getLanguage()->isRtl() ? 'right' : 'left';

        // Detect the current active language
        $lang = Factory::getLanguage()->getTag();

        //detect the position
        $position = $this->params->get('position', 'bottom_left');

        /**
        * Add strings for translations in Javascript.
        * Reference  https://ranbuch.github.io/accessibility/
        */
        $document->addScriptOptions(
            'accessibility-options',
            [
                'labels' => [
                    'menuTitle'           => Text::_('PLG_SYSTEM_ACCESSIBILITY_MENU_TITLE'),
                    'increaseText'        => Text::_('PLG_SYSTEM_ACCESSIBILITY_INCREASE_TEXT'),
                    'decreaseText'        => Text::_('PLG_SYSTEM_ACCESSIBILITY_DECREASE_TEXT'),
                    'increaseTextSpacing' => Text::_('PLG_SYSTEM_ACCESSIBILITY_INCREASE_SPACING'),
                    'decreaseTextSpacing' => Text::_('PLG_SYSTEM_ACCESSIBILITY_DECREASE_SPACING'),
                    'invertColors'        => Text::_('PLG_SYSTEM_ACCESSIBILITY_INVERT_COLORS'),
                    'grayHues'            => Text::_('PLG_SYSTEM_ACCESSIBILITY_GREY'),
                    'underlineLinks'      => Text::_('PLG_SYSTEM_ACCESSIBILITY_UNDERLINE'),
                    'bigCursor'           => Text::_('PLG_SYSTEM_ACCESSIBILITY_CURSOR'),
                    'readingGuide'        => Text::_('PLG_SYSTEM_ACCESSIBILITY_READING'),
                    'textToSpeech'        => Text::_('PLG_SYSTEM_ACCESSIBILITY_TTS'),
                    'speechToText'        => Text::_('PLG_SYSTEM_ACCESSIBILITY_STT'),
                    'resetTitle'          => Text::_('PLG_SYSTEM_ACCESSIBILITY_RESET'),
                    'closeTitle'          => Text::_('PLG_SYSTEM_ACCESSIBILITY_CLOSE'),
                    'disableAnimations'   => Text::_('PLG_SYSTEM_ACCESSIBILITY_DISABLE_ANIMATIONS'),
                    'increaseLineHeight'  => Text::_('PLG_SYSTEM_ACCESSIBILITY_INCREASE_LINE_HEIGHT'),
                    'decreaseLineHeight'  => Text::_('PLG_SYSTEM_ACCESSIBILITY_DECREASE_LINE_HEIGHT')
                ],
                'icon' => [
                    'position' => [
                        (($position == 'top_right') || ($position == 'top_left')) ? 'top' : 'bottom' => [
                            'size' => '50',
                            'units' => 'px',
                        ],
                        (($position == 'top_right') || ($position == 'bottom_right')) ? 'right' : 'left' => [
                            'size' => '0',
                            'units' => 'px',
                        ],
                    ],
                    'useEmojis' => $this->params->get('useEmojis') != 'false' ? true : false,
                ],
                'hotkeys' => [
                    'enabled' => true,
                    'helpTitles' => true,
                ],
                'language' => [
                    'textToSpeechLang' => $lang,
                    'speechToTextLang' => $lang
                ]
            ]
        );

        $document->getWebAssetManager()
            ->useScript('accessibility')
            ->addInlineScript(
                'window.addEventListener("load", function() {'
                . 'new Accessibility(Joomla.getOptions("accessibility-options") || {});'
                . '});',
                ['name' => 'inline.plg.system.accessibility'],
                ['type' => 'module'],
                ['accessibility']
            );
    }
}
