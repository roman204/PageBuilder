<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Model\ResourceModel;

use Goomento\PageBuilder\Api\Data\RevisionInterface;
use Goomento\PageBuilder\Helper\DataHelper;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Revision extends AbstractDb
{
    /**
     * @inheriDoc
     */
    protected function _construct()
    {
        $this->_init('pagebuilder_content_revision', 'revision_id');
    }

    /**
     * @inheriDoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        parent::_afterSave($object);
        $this->cleanRevisions($object);
        return $this;
    }

    /**
     * Remove revisions
     */
    private function cleanRevisions(AbstractModel $object)
    {
        $contentId = (int) $object->getData(RevisionInterface::CONTENT_ID);
        if ($contentId) {
            $maxRevision = DataHelper::getBuilderConfig('editor/number_of_revision') ?: 100;
            $connection = $this->getConnection();
            $selectCount = $connection->select()->from(
                $this->getMainTable(),
                'COUNT(*)'
            )->where('content_id = ?', $contentId);
            $count = (int) $connection->fetchOne($selectCount);

            if ($count && $count > $maxRevision) {
                $removeNumber = $count - $maxRevision;
                $connection->deleteFromSelect(
                    $connection->select()->from(
                        $this->getMainTable(),
                        'revision_id'
                    )
                        ->where('content_id = ?', $contentId)
                        ->where('status = ?', 'revision')
                        ->limit($removeNumber)
                        ->order('content_id asc'),
                    $this->getMainTable()
                );
            }
        }
    }
}
