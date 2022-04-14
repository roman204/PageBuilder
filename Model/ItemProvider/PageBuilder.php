<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Model\ItemProvider;

use Goomento\PageBuilder\Api\Data\ContentInterface;
use Magento\Sitemap\Model\ItemProvider\CmsPageConfigReader;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Goomento\PageBuilder\Model\ResourceModel\Content\Collection;
use Goomento\PageBuilder\Model\ResourceModel\Content\CollectionFactory;
use Goomento\PageBuilder\Logger\Logger;

class PageBuilder implements ItemProviderInterface
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;
    /**
     * @var SitemapItemInterfaceFactory
     */
    private $itemFactory;
    /**
     * @var CmsPageConfigReader
     */
    private $configReader;
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param CollectionFactory $collectionFactory
     * @param SitemapItemInterfaceFactory $itemFactory
     * @param CmsPageConfigReader $configReader
     * @param Logger $logger
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        SitemapItemInterfaceFactory $itemFactory,
        CmsPageConfigReader $configReader,
        Logger $logger
    )
    {
        $this->collectionFactory = $collectionFactory;
        $this->itemFactory = $itemFactory;
        $this->configReader = $configReader;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function getItems($storeId)
    {
        try {
            /** @var Collection $collection */
            $collection = $this->collectionFactory->create();
            $collection
                ->addStoreFilter((int) $storeId, false)
                ->addFilter(ContentInterface::TYPE, ContentInterface::TYPE_PAGE)
                ->addFilter(ContentInterface::STATUS, ContentInterface::STATUS_PUBLISHED)
                ->addFilter(ContentInterface::ENABLED, ContentInterface::ENABLED);

            $items = $collection->getItems();
            return array_map(function ($item) use ($storeId) {
                /** @var ContentInterface $item */
                return $this->itemFactory->create([
                    'url' => $item->getIdentifier(),
                    'updatedAt' => $item->getUpdateTime(),
                    'images' => null,
                    'priority' => $this->configReader->getPriority($storeId),
                    'changeFrequency' => $this->configReader->getChangeFrequency($storeId),
                ]);
            }, $items);
        } catch (\Exception $e) {
            $this->logger->error($e);
        }
    }
}
