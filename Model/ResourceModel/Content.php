<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Model\ResourceModel;

use Goomento\PageBuilder\Api\Data\ContentInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Content extends AbstractDb
{
    /**
     * @inheriDoc
     */
    protected $_serializableFields = [
        ContentInterface::SETTINGS => [null, []],
        ContentInterface::ELEMENTS => [null, []],
    ];

    /**
     * @inheriDoc
     */
    protected function _construct()
    {
        $this->_init('pagebuilder_content', 'content_id');
    }

    /**
     * @inheriDoc
     */
    protected function _afterLoad(AbstractModel $object)
    {
        parent::_afterLoad($object);
        // Set stores Ids to object
        $object->setData(ContentInterface::STORE_IDS, $this->lookupStoreIds((int) $object->getId()));
        $object->setHasDataChanges(false);
    }

    /**
     * @param DataObject $object
     * @return DataObject
     */
    public function unserializeData(DataObject $object)
    {
        foreach ($this->_serializableFields as $field => $parameters) {
            list($serializeDefault, $unserializeDefault) = $parameters;
            $this->_unserializeField($object, $field, $unserializeDefault);
        }
        return $object;
    }

    /**
     * @param DataObject $object
     * @return DataObject
     */
    public function serializeData(DataObject $object)
    {
        foreach ($this->_serializableFields as $field => $parameters) {
            list($serializeDefault, $unserializeDefault) = $parameters;
            $this->_serializeField($object, $field, $serializeDefault, isset($parameters[2]));
        }
        return $object;
    }


    /**
     * Get store ids to which specified item is assigned
     *
     * @param int $contentId
     * @return array
     * @throws LocalizedException
     */
    public function lookupStoreIds($contentId)
    {
        $connection = $this->getConnection();

        $linkField = ContentInterface::CONTENT_ID;

        $select = $connection->select()
            ->from(['s' => $this->getTable('pagebuilder_content_store')], 'store_id')
            ->join(
                ['t' => $this->getMainTable()],
                's.' . $linkField . ' = t.' . $linkField,
                []
            )
            ->where('t.' . $linkField . ' = :content_id');

        return $connection->fetchCol($select, ['content_id' => (int)$contentId]);
    }

    /**
     * @inheriDoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        parent::_afterSave($object);
        $this->updateStoreIds($object);
        return $this;
    }

    /**
     * @param AbstractModel $object
     * @return void
     */
    public function unserializeFields(\Magento\Framework\Model\AbstractModel $object)
    {
        parent::unserializeFields($object);
    }

    /**
     * @param AbstractModel $object
     * @throws LocalizedException
     */
    private function updateStoreIds(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $oldStores = $this->lookupStoreIds((int)$object->getId());
        $newStores = (array) $object->getData(ContentInterface::STORE_IDS);
        $linkField = ContentInterface::CONTENT_ID;

        $table = $this->getTable('pagebuilder_content_store');

        $delete = array_diff($oldStores, $newStores);
        if ($delete) {
            $where = [
                $linkField . ' = ?' => (int)$object->getData($linkField),
                'store_id IN (?)' => $delete,
            ];
            $connection->delete($table, $where);
        }

        $insert = array_diff($newStores, $oldStores);
        if ($insert) {
            $data = [];
            foreach ($insert as $storeId) {
                $data[] = [
                    $linkField => (int)$object->getData($linkField),
                    'store_id' => (int)$storeId
                ];
            }
            $connection->insertMultiple($table, $data);
        }
    }
}
