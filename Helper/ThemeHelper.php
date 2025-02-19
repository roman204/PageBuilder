<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Helper;

use Goomento\PageBuilder\Api\Data\ContentInterface;

/**
 *
 * NOTE: Use these static methods in template hook only - which wrapped in HooksHelper::doAction( 'header' ) or
 * HooksHelper::doAction( 'footer' ) ... . Otherwise might cause some issues with classes loader.
 * See https://developer.adobe.com/commerce/php/development/components/object-manager/#usage-rules
 *
 */
class ThemeHelper extends \Goomento\Core\Helper\ThemeHelper
{
    /**
     * @var ContentInterface[]
     */
    private static $contents = [];

    /**
     * Add body class to theme
     *
     * @param array|string $args
     * @return array
     */
    public static function getBodyClass($args = [])
    {
        $args['goomento-default'] = 'goomento-default';
        $args['goomento-page'] = 'goomento-page';

        if (self::hasContentOnPage()) {
            foreach (self::$contents as $id => $content) {
                $args['gmt-' . $id] = 'gmt-' . $id;
            }
        }

        return $args;
    }

    /**
     * @param ContentInterface $content
     * @return void
     */
    public static function registerContentToPage(ContentInterface $content)
    {
        self::$contents[$content->getId()] = $content;
    }

    /**
     * Check whether content existed on page
     *
     * @return bool
     */
    public static function hasContentOnPage() : bool
    {
        return !empty(self::$contents);
    }

    /**
     * Trigger all hooks for header
     *
     * @return void
     */
    public static function doHeader()
    {
        self::getStylesManager()->doItems();
        self::getScriptsManager()->doHeadItems();
    }

    /**
     * Trigger all hooks for header
     *
     * @return void
     */
    public static function doFooter()
    {
        self::getStylesManager()->doItems();
        self::getScriptsManager()->doFooterItems();
    }
}
