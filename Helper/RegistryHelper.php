<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Helper;

use Goomento\Core\Traits\TraitStaticCaller;
use Goomento\Core\Traits\TraitStaticInstances;
use Goomento\Core\Model\Registry;

/**
 *
 * NOTE: Use these static methods in template hook only - which wrapped in HooksHelper::doAction( 'header' ) or
 * HooksHelper::doAction( 'footer' ) ... . Otherwise might cause some issues with classes loader.
 * See https://developer.adobe.com/commerce/php/development/components/object-manager/#usage-rules
 *
 * @method static registry($key);
 * @method static register($key, $value, $graceful = false);
 */
class RegistryHelper
{
    use TraitStaticCaller;
    use TraitStaticInstances;

    /**
     * @inheritDoc
     */
    private static function getStaticInstance()
    {
        return self::getInstance(Registry::class);
    }
}
