<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\UrlInterface;

class MediaBucket extends Template
{
    protected $_template = 'Goomento_PageBuilder::editor/media_bucket.phtml';

    /**
     * @return string
     */
    public function getMediaUrl()
    {
        return $this->_urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA]);
    }
}
