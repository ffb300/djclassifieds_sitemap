<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  jlsitemap.djclassifieds_sitemap
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Log\Log;

class PlgJlsitemapDjclassifieds_sitemap extends CMSPlugin
{
    /**
     * Plugin main entry point for JL Sitemap.
     *
     * @param object &$sitemap The sitemap object
     * @param object $params   Additional params
     * @return void
     */
    public function onJLSitemapBuild(&$sitemap, $params)
    {
        try {
            $db = Factory::getDbo();

            // Load plugin params
            $includeProducts = $this->params->get('include_products', 1);
            $maxItems = (int) $this->params->get('max_items', 100);
            $orderItems = $this->params->get('order_items', 'date_start DESC');
            $catInclude = $this->params->get('category_include', '');
            $catExclude = $this->params->get('category_exclude', '');
            $enableLogging = $this->params->get('enable_logging', 0);
            $catPriority = $this->params->get('cat_priority', '0.5');
            $catChangefreq = $this->params->get('cat_changefreq', 'weekly');
            $itemPriority = $this->params->get('link_priority', '0.5');
            $itemChangefreq = $this->params->get('link_changefreq', 'weekly');

            // Parse category include/exclude lists
            $includeIds = array_filter(array_map('intval', explode(',', $catInclude)));
            $excludeIds = array_filter(array_map('intval', explode(',', $catExclude)));

            // === Categories ===
            $query = $db->getQuery(true)
                ->select('id, name, alias')
                ->from('#__djcf_categories')
                ->where('published = 1');

            if (!empty($includeIds)) {
                $query->where('id IN (' . implode(',', $includeIds) . ')');
            }
            if (!empty($excludeIds)) {
                $query->where('id NOT IN (' . implode(',', $excludeIds) . ')');
            }

            $db->setQuery($query);
            $categories = $db->loadObjectList();

            foreach ($categories as $cat) {
                $url = Route::_('index.php?option=com_djclassifieds&view=items&cid=' . (int)$cat->id . ':' . $cat->alias, false);
                $sitemap->addItem([
                    'loc' => Uri::root() . ltrim($url, '/'),
                    'priority' => $catPriority,
                    'changefreq' => $catChangefreq,
                    'lastmod' => date('c'),
                    'title' => $cat->name
                ]);
                if ($enableLogging) {
                    Log::add("Added category to sitemap: {$cat->name}", Log::INFO, 'plg_jlsitemap_djclassifieds');
                }
            }

            if ($includeProducts) {
                // === Items ===
                $query = $db->getQuery(true)
                    ->select('id, name, alias, date_start, date_end, published')
                    ->from('#__djcf_items')
                    ->where('published = 1')
                    ->where('(date_end IS NULL OR date_end > NOW())') // Exclude expired
                    ->order($db->escape($orderItems));

                if (!empty($includeIds)) {
                    $query->where('cat_id IN (' . implode(',', $includeIds) . ')');
                }
                if (!empty($excludeIds)) {
                    $query->where('cat_id NOT IN (' . implode(',', $excludeIds) . ')');
                }

                if ($maxItems > 0) {
                    $db->setQuery($query, 0, $maxItems);
                } else {
                    $db->setQuery($query);
                }

                $items = $db->loadObjectList();

                foreach ($items as $item) {
                    $url = Route::_('index.php?option=com_djclassifieds&view=item&id=' . (int)$item->id . ':' . $item->alias, false);
                    $lastmod = !empty($item->date_end) ? $item->date_end : date('c');
                    $sitemap->addItem([
                        'loc' => Uri::root() . ltrim($url, '/'),
                        'priority' => $itemPriority,
                        'changefreq' => $itemChangefreq,
                        'lastmod' => $lastmod,
                        'title' => $item->name
                    ]);
                    if ($enableLogging) {
                        Log::add("Added item to sitemap: {$item->name}", Log::INFO, 'plg_jlsitemap_djclassifieds');
                    }
                }
            }

            // You could also fire additional events here for extensions, e.g.:
            // $this->triggerEvent('onAfterDjclassifiedsSitemapBuild', [$sitemap]);

        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage('DJ-Classifieds Sitemap plugin error: ' . $e->getMessage(), 'error');
            if ($enableLogging) {
                Log::add('Exception: ' . $e->getMessage(), Log::ERROR, 'plg_jlsitemap_djclassifieds');
            }
        }
    }
}
