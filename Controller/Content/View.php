<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Controller\Content;

use Goomento\PageBuilder\Helper\HooksHelper;
use Goomento\PageBuilder\Controller\AbstractAction;
use Goomento\PageBuilder\Traits\TraitHttpPage;
use Magento\Framework\Exception\LocalizedException;

/**
 * For Admin Preview purpose, therefore, there is all content will be rendered
 * This page will not be cached
 */
class View extends AbstractAction
{
    use TraitHttpPage;

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            $this->registry->register('pagebuilder_content', $this->getContent(true));
            HooksHelper::doAction('pagebuilder/content/view');
            return $this->renderPage();
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage(
                $e->getMessage()
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong when render content view.')
            );
        } finally {
            if (!empty($e)) {
                $this->logger->error($e);
                if ($this->dataHelper->isDebugMode()) {
                    throw $e;
                }
            }
        }

        return $this->redirect404Page();
    }

    /**
     * @inheritdoc
     */
    protected function getPageConfig()
    {
        $content = $this->getContent(true);
        $layout = $content->getSetting('layout') ?: 'pagebuilder_content_1column';

        return [
            'editable_title' => '%1',
            'handler' => $layout,
        ];
    }
}
