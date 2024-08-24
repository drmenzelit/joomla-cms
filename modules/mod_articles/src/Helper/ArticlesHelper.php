<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_articles
 *
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\Articles\Site\Helper;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\HTML\Helpers\StringHelper as SpecialStringHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper for mod_articles
 *
 * @since  __DEPLOY_VERSION__
 */
class ArticlesHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Retrieve a list of articles
     *
     * @param   Registry         $params  The module parameters.
     * @param   SiteApplication  $app     The current application.
     *
     * @return  object[]
     *
     * @since   __DEPLOY_VERSION__
     */
    public function getArticles(Registry $params, SiteApplication $app)
    {
        $factory = $app->bootComponent('com_content')->getMVCFactory();

        // Get an instance of the generic articles model
        $articles = $factory->createModel('Articles', 'Site', ['ignore_request' => true]);

        // Set application parameters in model
        $input     = $app->getInput();
        $appParams = $app->getParams();
        $articles->setState('params', $appParams);

        $articles->setState('list.start', 0);
        $articles->setState('filter.published', ContentComponent::CONDITION_PUBLISHED);

        // Set the filters based on the module params
        $articles->setState('list.limit', (int) $params->get('count', 0));
        $articles->setState('load_tags', $params->get('show_tags', 0) || $params->get('article_grouping', 'none') === 'tags');

        // Access filter
        $access     = !ComponentHelper::getParams('com_content')->get('show_noauth');
        $authorised = Access::getAuthorisedViewLevels($app->getIdentity()->get('id'));
        $articles->setState('filter.access', $access);

        $catids = $params->get('catid');
        $articles->setState('filter.category_id.include', (bool) $params->get('category_filtering_type', 1));

        // Category filter
        if ($catids) {
            if ($params->get('show_child_category_articles', 0) && (int) $params->get('levels', 0) > 0) {
                // Get an instance of the generic categories model
                $categories = $factory->createModel('Categories', 'Site', ['ignore_request' => true]);
                $categories->setState('params', $appParams);
                $levels = $params->get('levels', 1) ?: 9999;
                $categories->setState('filter.get_children', $levels);
                $categories->setState('filter.published', 1);
                $categories->setState('filter.access', $access);
                $additional_catids = [];

                foreach ($catids as $catid) {
                    $categories->setState('filter.parentId', $catid);
                    $recursive = true;
                    $items     = $categories->getItems($recursive);

                    if ($items) {
                        foreach ($items as $category) {
                            $condition = (($category->level - $categories->getParent()->level) <= $levels);

                            if ($condition) {
                                $additional_catids[] = $category->id;
                            }
                        }
                    }
                }

                $catids = array_unique(array_merge($catids, $additional_catids));
            }

            $articles->setState('filter.category_id', $catids);
        }

        // Ordering
        $ordering = $params->get('article_ordering', 'a.ordering');

        switch ($ordering) {
            case 'random':
                $articles->setState('list.ordering', $this->getDatabase()->getQuery(true)->rand());
                break;

            case 'rating_count':
            case 'rating':
                $articles->setState('list.ordering', $ordering);
                $articles->setState('list.direction', $params->get('article_ordering_direction', 'ASC'));

                if (!PluginHelper::isEnabled('content', 'vote')) {
                    $articles->setState('list.ordering', 'a.ordering');
                }

                break;

            default:
                $articles->setState('list.ordering', $ordering);
                $articles->setState('list.direction', $params->get('article_ordering_direction', 'ASC'));
                break;
        }

        // Filter by multiple tags
        $articles->setState('filter.tag', $params->get('filter_tag', []));

        $articles->setState('filter.featured', $params->get('show_featured', 'show'));
        $articles->setState('filter.author_id', $params->get('created_by', []));
        $articles->setState('filter.author_id.include', $params->get('author_filtering_type', 1));
        $articles->setState('filter.author_alias', $params->get('created_by_alias', []));
        $articles->setState('filter.author_alias.include', $params->get('author_alias_filtering_type', 1));

        // Filter archived articles
        if ($params->get('show_archived', 'hide') === 'show') {
            $articles->setState('filter.published', ContentComponent::CONDITION_ARCHIVED);
        }

        // Filter by id in case it should be excluded
        $excluded_articles = $params->get('excluded_articles', '');
        if (
            $params->get('exclude_current', true)
            && $input->get('option') === 'com_content'
            && $input->get('view') === 'article'
        ) {
            // Add current article to excluded list
            $excluded_articles .= "\r\n" . $input->get('id', 0, 'UINT');
        }

        if ($excluded_articles) {
            $excluded_articles = explode("\r\n", $excluded_articles);
            $articles->setState('filter.article_id', $excluded_articles);

            // Exclude
            $articles->setState('filter.article_id.include', false);
        }

        // Filter by id in case it should be included only
        $included_articles = $params->get('included_articles', '');
        if ($included_articles) {
            $included_articles = explode("\r\n", $included_articles);
            $articles->setState('filter.article_id', $included_articles);

            // Include
            $articles->setState('filter.article_id.include', true);
        }

        $date_filtering = $params->get('date_filtering', 'off');

        if ($date_filtering !== 'off') {
            $articles->setState('filter.date_filtering', $date_filtering);
            $articles->setState('filter.date_field', $params->get('date_field', 'a.created'));
            $articles->setState('filter.start_date_range', $params->get('start_date_range', '1000-01-01 00:00:00'));
            $articles->setState('filter.end_date_range', $params->get('end_date_range', '9999-12-31 23:59:59'));
            $articles->setState('filter.relative_date', $params->get('relative_date', 30));
        }

        // Filter by language
        $articles->setState('filter.language', $app->getLanguageFilter());

        $items = $articles->getItems();

        // Display options
        $show_date          = $params->get('show_date', 0);
        $show_date_field    = $params->get('show_date_field', 'created');
        $show_date_format   = $params->get('show_date_format', 'Y-m-d H:i:s');
        $show_category      = $params->get('show_category', 0);
        $show_category_link = $params->get('show_category_link', 0);
        $show_hits          = $params->get('show_hits', 0);
        $show_author        = $params->get('show_author', 0);
        $show_introtext     = $params->get('show_introtext', 0);
        $introtext_limit    = $params->get('introtext_limit', 100);

        // Find current Article ID if on an article page
        $option = $input->get('option');
        $view   = $input->get('view');

        if ($option === 'com_content' && $view === 'article') {
            $active_article_id = $input->getInt('id');
        } else {
            $active_article_id = 0;
        }

        // Prepare data for display using display options
        foreach ($items as &$item) {
            $item->slug = $item->id . ':' . $item->alias;

            $articleLink = Route::_(RouteHelper::getArticleRoute($item->slug, $item->catid, $item->language));

            if ($access || \in_array($item->access, $authorised)) {
                // We know that user has the privilege to view the article
                $item->link = $articleLink;
            } else {
                $menu      = $app->getMenu();
                $menuitems = $menu->getItems('link', 'index.php?option=com_users&view=login');

                if (isset($menuitems[0])) {
                    $Itemid = $menuitems[0]->id;
                } elseif ($input->getInt('Itemid') > 0) {
                    // Use Itemid from requesting page only if there is no existing menu
                    $Itemid = $input->getInt('Itemid');
                }

                $return = base64_encode($articleLink);

                $item->link = Route::_('index.php?option=com_users&view=login&Itemid=' . $Itemid . '&return=' . $return);
            }

            // Used for styling the active article
            $item->active      = $item->id == $active_article_id ? 'active' : '';

            if ($show_date) {
                $item->displayDate = HTMLHelper::_('date', $item->$show_date_field, $show_date_format);
            }

            if ($show_category) {
                $item->displayCategoryTitle = $item->category_title;
            }

            if ($show_category_link) {
                $item->displayCategoryLink = Route::_(RouteHelper::getCategoryRoute($item->catid, $item->category_language));
            }

            $item->displayAuthorName    = $show_author ? $item->author : '';
            $item->displayCategoryTitle = $show_category ? $item->category_title : '';
            $item->displayCategoryLink  = $show_category_link ? $item->displayCategoryLink : '';
            $item->displayDate          = $show_date ? $item->displayDate : '';
            $item->displayHits          = $show_hits ? $item->hits : '';

            if ($show_introtext) {
                $item->displayIntrotext = HTMLHelper::_('content.prepare', $item->introtext, '', 'mod_articles.content');
                if ($introtext_limit != 0) {
                    $item->displayIntrotext = SpecialStringHelper::truncate($item->introtext, $introtext_limit, true, false);
                }
            }

            // Remove any images belongs to the text
            if (!$params->get('image')) {
                $item->displayIntrotext = preg_replace('/<img[^>]*>/', '', $item->introtext);
            }

            // Show the Intro/Full image field of the article
            if ($params->get('img_intro_full') !== 'none') {
                $images             = (new Registry($item->images))->toObject();
                $item->imageSrc     = '';

                if ($params->get('img_intro_full') === 'intro' && !empty($images->image_intro)) {
                    $item->imageSrc = htmlspecialchars($images->image_intro, ENT_COMPAT, 'UTF-8');
                    $images->float_intro = 'mod-articles-image';
                } elseif ($params->get('img_intro_full') === 'full' && !empty($images->image_fulltext)) {
                    $item->imageSrc = htmlspecialchars($images->image_fulltext, ENT_COMPAT, 'UTF-8');
                    $images->float_fulltext = 'mod-articles-image';
                }

                $item->images = json_encode($images);
            }

            $item->displayReadmore  = $item->alternative_readmore;
        }

        // Check if items need be grouped
        $article_grouping           = $params->get('article_grouping', 'none');
        $article_grouping_direction = $params->get('article_grouping_direction', 'ksort');
        $grouped                    = $article_grouping !== 'none';

        if ($items && $grouped) {
            switch ($article_grouping) {
                case 'year':
                case 'month_year':
                    $items = ArticlesHelper::groupByDate(
                        $items,
                        $article_grouping_direction,
                        $article_grouping,
                        $params->get('month_year_format', 'F Y'),
                        $params->get('date_grouping_field', 'created')
                    );
                    break;
                case 'author':
                case 'category_title':
                    $items = ArticlesHelper::groupBy($items, $article_grouping, $article_grouping_direction);
                    break;
                case 'tags':
                    $items = ArticlesHelper::groupByTags($items, $article_grouping_direction);
                    break;
            }
        }

        return $items;
    }

    /**
     * Groups items by field
     *
     * @param   array   $list             list of items
     * @param   string  $fieldName        name of field that is used for grouping
     * @param   string  $direction        ordering direction
     * @param   null    $fieldNameToKeep  field name to keep
     *
     * @return  array
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function groupBy($list, $fieldName, $direction, $fieldNameToKeep = null)
    {
        $grouped = [];

        if (!\is_array($list)) {
            if ($list === '') {
                return $grouped;
            }

            $list = [$list];
        }

        foreach ($list as $key => $item) {
            if (!isset($grouped[$item->$fieldName])) {
                $grouped[$item->$fieldName] = [];
            }

            if ($fieldNameToKeep === null) {
                $grouped[$item->$fieldName][$key] = $item;
            } else {
                $grouped[$item->$fieldName][$key] = $item->$fieldNameToKeep;
            }

            unset($list[$key]);
        }

        $direction($grouped);

        return $grouped;
    }

    /**
     * Groups items by date
     *
     * @param   array   $list             list of items
     * @param   string  $direction        ordering direction
     * @param   string  $type             type of grouping
     * @param   string  $monthYearFormat  date format to use
     * @param   string  $field            date field to group by
     *
     * @return  array
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function groupByDate($list, $direction = 'ksort', $type = 'year', $monthYearFormat = 'F Y', $field = 'created')
    {
        $grouped = [];

        if (!\is_array($list)) {
            if ($list === '') {
                return $grouped;
            }

            $list = [$list];
        }

        foreach ($list as $key => $item) {
            switch ($type) {
                case 'month_year':
                    $month_year = StringHelper::substr($item->$field, 0, 7);

                    if (!isset($grouped[$month_year])) {
                        $grouped[$month_year] = [];
                    }

                    $grouped[$month_year][$key] = $item;
                    break;

                default:
                    $year = StringHelper::substr($item->$field, 0, 4);

                    if (!isset($grouped[$year])) {
                        $grouped[$year] = [];
                    }

                    $grouped[$year][$key] = $item;
                    break;
            }

            unset($list[$key]);
        }

        $direction($grouped);

        if ($type === 'month_year') {
            foreach ($grouped as $group => $items) {
                $date                      = new Date($group);
                $formatted_group           = $date->format($monthYearFormat);
                $grouped[$formatted_group] = $items;

                unset($grouped[$group]);
            }
        }

        return $grouped;
    }

    /**
     * Groups items by tags
     *
     * @param   array   $list       list of items
     * @param   string  $direction  ordering direction
     *
     * @return  array
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function groupByTags($list, $direction = 'ksort')
    {
        $grouped  = [];
        $untagged = [];

        if (!$list) {
            return $grouped;
        }

        foreach ($list as $item) {
            if ($item->tags->itemTags) {
                foreach ($item->tags->itemTags as $tag) {
                    $grouped[$tag->title][] = $item;
                }
            } else {
                $untagged[] = $item;
            }
        }

        $direction($grouped);

        if ($untagged) {
            $grouped['MOD_ARTICLES_UNTAGGED'] = $untagged;
        }

        return $grouped;
    }
}
