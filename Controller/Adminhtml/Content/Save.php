<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Controller\Adminhtml\Content;

use Exception;
use Goomento\PageBuilder\Api\Data\ContentInterface;
use Goomento\PageBuilder\Helper\DataHelper;
use Goomento\PageBuilder\Helper\EncryptorHelper;
use Goomento\PageBuilder\Helper\EscaperHelper;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends AbstractContent implements HttpPostActionInterface
{
    use TraitContent;

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $data = (array) $this->getRequest()->getPostValue();

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if (!empty($data)) {
            try {
                $contentType = $this->getContentType();
                $data['type'] = $contentType;

                $data = EscaperHelper::filter($data);
                $isNewObject = !((int) $data['content_id']);

                $content = $this->getContent(!$isNewObject);

                if (!$this->_authorization->isAllowed($this->getContentResourceName('save'))) {
                    throw new LocalizedException(
                        __('Sorry, you need permissions to save this content.')
                    );
                }

                if (!$content) {
                    $content = $this->contentFactory->create();
                } elseif (empty($contentType) || $content->getType() !== $contentType) {
                    throw new LocalizedException(
                        __('Invalid content type: %1', $contentType)
                    );
                }
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setRefererUrl();
            } catch (Exception $e) {
                $this->logger->error($e);
                $this->messageManager->addErrorMessage(__('Something went wrong when saving the content.'));
                return $resultRedirect->setRefererUrl();
            }

            try {
                if (!$this->dataProcessor->validate($data)) {
                    return $resultRedirect->setPath('*/*/edit', ['page_id' => $content->getId(), '_current' => true]);
                }

                $content->setType($contentType);
                $content->setIsActive((bool) $data['is_active']);

                if (isset($data['store_id']) && $data['store_id']) {
                    $content->setStoreIds($data['store_id']);
                }

                if ($content->isObjectNew()) {
                    $content->setAuthorId(
                        $this->userHelper->getCurrentAdminUser()->getId()
                    );
                }

                $content->setLastEditorId(
                    $this->userHelper->getCurrentAdminUser()->getId()
                );

                $content->setTitle($data['title']);

                if (!empty($data['content_data'])) {
                    $contentData = base64_decode($data['content_data']);
                    if ($contentData && DataHelper::isJson($contentData)) {
                        $contentData = \Zend_Json::decode($contentData);
                        if ($contentData !== $content->getElements()) {
                            $content->setElements($contentData);
                        }
                    } else {
                        throw new LocalizedException(
                            __('Data content invalidated. Make sure you copy the right way.')
                        );
                    }
                } else {
                    $content->setElements([]);
                }

                $identifier = isset($data['identifier']) ? trim($data['identifier']) : '';
                if (empty($identifier)) {
                    $identifier = $content->getType() . '-' . EncryptorHelper::uniqueString();
                }

                if ($content->getIdentifier() !== $identifier) {
                    $content->setIdentifier($identifier);
                }

                $this->proceedContent($content, $data);

                $this->contentManagement->refreshContentAssets($content);
                $content = $this->contentRepository->save($content);

                $this->messageManager->addSuccessMessage(
                    __('You saved the content.')
                );
                return $this->processResultRedirect($content, $resultRedirect, $data);
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?: $e);
            } catch (Exception $e) {
                $this->logger->error($e);
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the content.'));
            }

            $this->dataPersistor->set('pagebuilder_content', $data);
            return $resultRedirect->setPath('*/*/edit', [
                'content_id' => $this->getRequest()->getParam('content_id'),
                'type' => $contentType,
            ]);
        }

        return $resultRedirect->setRefererUrl();
    }

    /**
     * Proceed content data
     *
     * @param ContentInterface $content
     * @param $data
     * @return void
     */
    protected function proceedContent(ContentInterface $content, $data)
    {
        switch ($content->getType()) {
            case ContentInterface::TYPE_PAGE:
                $this->proceedPageContent($content, $data);
                break;
            case ContentInterface::TYPE_TEMPLATE:
                $this->proceedTemplateContent($content, $data);
                break;
            default:
                $this->proceedSectionContent($content, $data);
                break;
        }
    }


    /**
     * Proceed the Content type Page
     *
     * @param ContentInterface $content
     * @param $data
     */
    private function proceedPageContent(ContentInterface $content, $data)
    {
        if (!empty($data['status'])) {
            $content->setStatus($data['status']);
        } else {
            $content->setStatus(ContentInterface::STATUS_PENDING);
        }
        $content->setMetaTitle($data[ContentInterface::META_TITLE]);
        $content->setMetaDescription($data[ContentInterface::META_DESCRIPTION]);
        $content->setMetaKeywords($data[ContentInterface::META_KEYWORDS]);
    }

    /**
     * Proceed the Content type Section
     *
     * @param ContentInterface $content
     * @param $data
     */
    private function proceedSectionContent(ContentInterface $content, $data)
    {
        $this->proceedTemplateContent($content, $data);
    }

    /**
     * Proceed the Content type Template
     *
     * @param ContentInterface $content
     * @param $data
     */
    private function proceedTemplateContent(ContentInterface $content, $data)
    {
        $content->setStatus(ContentInterface::STATUS_PENDING);
    }

    /**
     * @param ContentInterface $model
     * @param $resultRedirect
     * @param $data
     * @return mixed
     * @throws LocalizedException
     */
    protected function processResultRedirect($model, $resultRedirect, $data)
    {
        if ($this->getRequest()->getParam('back', false) === 'duplicate') {
            $content = $this->contentFactory->create(['data' => $data]);
            $content->setId(null);
            $title = $content->getTitle();
            $title .= ' ' .  __('(Duplicated)');

            $content->setTitle($title);
            $content->setIdentifier('');
            $content->setElements($model->getElements());
            $content->setSettings($model->getSettings());
            $this->proceedContent($content, $data);
            $content->setStatus(ContentInterface::STATUS_PENDING);

            $this->contentRepository->save($content);
            $this->messageManager->addSuccessMessage(__('You duplicated the content.'));
            return $resultRedirect->setPath(
                '*/*/edit',
                [
                    'content_id' => $content->getId(),
                    'type' => $content->getType(),
                    '_current' => true
                ]
            );
        }

        $this->dataPersistor->clear('pagebuilder_content');

        if ($this->getRequest()->getParam('back')) {
            return $resultRedirect->setPath('*/*/edit', [
                'content_id' => $model->getId(),
                'type' => $model->getType(),
                '_current' => true
            ]);
        }

        return $resultRedirect->setPath('*/*/grid', [
            'type' => $model->getType()
        ]);
    }
}
