<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Builder\Managers;

use Exception;
use Goomento\PageBuilder\Api\Data\ContentInterface;
use Goomento\PageBuilder\Builder\Sources\Local;
use Goomento\PageBuilder\Builder\Base\AbstractDocument;
use Goomento\PageBuilder\Builder\Modules\Ajax as Ajax;
use Goomento\PageBuilder\Builder\DocumentTypes\Page;
use Goomento\PageBuilder\Builder\DocumentTypes\Section;
use Goomento\PageBuilder\Builder\DocumentTypes\Template;
use Goomento\PageBuilder\Helper\HooksHelper;
use Goomento\PageBuilder\Helper\ContentHelper;
use Goomento\PageBuilder\Helper\ObjectManagerHelper;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class Documents
{
    /**
     * Registered types.
     *
     * Holds the list of all the registered types.
     *
     *
     * @var AbstractDocument[]
     */
    protected $types = [];

    /**
     * Registered documents.
     *
     * Holds the list of all the registered documents.
     *
     *
     * @var AbstractDocument[]
     */
    protected $documents = [];

    /**
     * Documents manager constructor.
     *
     * Initializing the SagoTheme documents manager.
     *
     */
    public function __construct()
    {
        HooksHelper::addAction('pagebuilder/documents/register', [ $this, 'registerDefaultTypes' ], 0);
        HooksHelper::addAction('pagebuilder/ajax/register_actions', [ $this, 'registerAjaxActions' ]);
    }

    /**
     * Register ajax actions.
     *
     * Process ajax action handles when saving data and discarding changes.
     *
     * @param Ajax $ajaxManager An instance of the ajax manager.
     * @throws LocalizedException
     * @throws Exception
     */
    public function registerAjaxActions($ajaxManager)
    {
        $ajaxManager->registerAjaxAction('save_builder', [ $this, 'ajaxSave' ]);
        $ajaxManager->registerAjaxAction('discard_changes', [ $this, 'ajaxDiscardChanges' ]);
    }

    /**
     * Register default types.
     *
     * Registers the default document types.
     *
     */
    public function registerDefaultTypes()
    {
        $default_types = [
            'page' => Page::class,
            'template' => Template::class,
            'section' => Section::class,
        ];

        foreach ($default_types as $type => $class) {
            $this->registerDocumentType($type, $class);
        }
    }

    /**
     * @param $type
     * @param $class
     * @return $this
     */
    public function registerDocumentType($type, $class)
    {
        $this->types[ $type ] = $class;

        if ($class::getProperty('register_type')) {
            Local::addTemplateType($type);
        }

        return $this;
    }

    /**
     * Get document.
     *
     * Retrieve the document data based on a content ID.
     *
     *
     * @param $id
     * @param bool $from_cache Optional. Whether to retrieve cached data. Default is true.
     *
     * @return false|AbstractDocument AbstractDocument data or false if content ID was not entered.
     */
    public function get($id, $from_cache = true)
    {
        $this->registerTypes();

        $model = ContentHelper::get($id);

        if (!$id || ! $model) {
            return false;
        }

        if (!$from_cache || ! isset($this->documents[$id])) {
            $doc_type_class = $this->getDocumentType($model->getType());
            $this->documents[$id] = ObjectManagerHelper::create($doc_type_class, ['data' => ['id' => $id]]);
        }

        return $this->documents[$id];
    }

    /**
     * @param $type
     * @return false|AbstractDocument
     */
    public function getDocumentType($type)
    {
        $types = $this->getDocumentTypes();
        if (isset($types[ $type ])) {
            return $types[ $type ];
        }

        return false;
    }

    /**
     * Get document types.
     *
     * Retrieve the all the registered document types.
     *
     * @return AbstractDocument[] All the registered document types.
     *
     */
    public function getDocumentTypes()
    {
        $this->registerTypes();

        return $this->types;
    }

    /**
     * Get document types with their properties.
     *
     * @return array A list of properties arrays indexed by the type.
     */
    public function getTypesProperties()
    {
        $types_properties = [];

        foreach ($this->getDocumentTypes() as $type => $class) {
            $types_properties[ $type ] = $class::getProperties();
        }
        return $types_properties;
    }

    /**
     * Create a document.
     *
     * Create a new document using any given parameters.
     *
     *
     * @param string $type AbstractDocument type.
     * @param array $data An array containing the post data.
     * @return AbstractDocument The type of the document.
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function create($type, $data = [])
    {
        $class = $this->getDocumentType($type);

        if (!$class) {
            throw new Exception(
                sprintf('Type %s does not exist.', $type)
            );
        }

        if (!isset($data['status'])) {
            $data['status'] = ContentInterface::STATUS_PENDING;
        }

        $data['type'] = $type;

        $content = ContentHelper::create($data);

        /** @var AbstractDocument $document */
        $document = ObjectManagerHelper::create($class, ['data' => ['id' => $content->getId()]]);

        $document->save([]);

        return $document;
    }

    /**
     * Save document data using ajax.
     *
     * Save the document on the builder using ajax, when saving the changes, and refresh the editor.
     *
     *
     *
     * @throws Exception If current user don't have permissions to edit the post or the post is not using SagoTheme.
     *
     * @return array The document data after saving.
     */
    public function ajaxSave($request)
    {
        $document = $this->get($request['content_id']);

        $data = [
            'elements' => $request['elements'],
            'settings' => $request['settings'],
        ];

        $document->save($data);

        $return_data = [
            'config' => [
                'document' => [
                    'last_edited' => $document->getLastEdited(),
                    'urls' => [
                        'system_preview' => $document->getSystemPreviewUrl(),
                    ],
                ],
            ],
        ];

        /**
         * Returned documents ajax saved data.
         *
         * Filters the ajax data returned when saving the post on the builder.
         *
         *
         * @param array    $return_data The returned data.
         * @param AbstractDocument $document    The document instance.
         */
        return HooksHelper::applyFilters('pagebuilder/documents/ajax_save/return_data', $return_data, $document);
    }

    /**
     * Ajax discard changes.
     *
     * Load the document data from an autosave, deleting unsaved changes.
     *
     *
     * @param $request
     *
     * @return bool True if changes discarded, False otherwise.
     */
    public function ajaxDiscardChanges($request)
    {
        return $success = false;
    }

    /**
     * @return void
     */
    private function registerTypes()
    {
        if (! HooksHelper::didAction('pagebuilder/documents/register')) {
            /**
             * Register SagoTheme documents.
             *
             *
             * @param Documents $this The document manager instance.
             */
            HooksHelper::doAction('pagebuilder/documents/register', $this);
        }
    }
}
