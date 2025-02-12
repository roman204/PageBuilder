<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Builder\Base;

use Exception;
use Goomento\PageBuilder\Builder\Modules\Ajax as Ajax;
use Goomento\PageBuilder\Builder\Base\AbstractSettings as BaseModel;
use Goomento\PageBuilder\Helper\HooksHelper;
use Goomento\PageBuilder\Helper\EscaperHelper;

abstract class AbstractSettingsManager extends AbstractEntity
{

    const NAME = 'base';

    const TYPE = 'settings_manager';
    /**
     * Models cache.
     *
     * Holds all the models.
     *
     *
     * @var BaseModel[]
     */
    private $models_cache = [];

    /**
     * Settings base manager constructor.
     *
     * Initializing SagoTheme settings base manager.
     *
     */
    public function __construct()
    {
        HooksHelper::addAction('pagebuilder/editor/footer', [ $this,'addTemplate' ]);

        HooksHelper::addAction('pagebuilder/ajax/register_actions', [ $this,'registerAjaxActions' ]);

        $name = $this->getCssFileName();

        HooksHelper::addAction("pagebuilder/css-file/{$name}/parse", [ $this, 'addSettingsCssRules' ]);
    }

    /**
     * Register ajax actions.
     *
     * Add new actions to handle data after an ajax requests returned.
     *
     *
     *
     * @param Ajax $ajax_manager
     * @throws Exception
     */
    public function registerAjaxActions($ajax_manager)
    {
        $name = static::NAME;
        $ajax_manager->registerAjaxAction("save_{$name}_settings", [ $this, 'ajaxSaveSettings' ]);
    }

    /**
     * Get model for config.
     *
     * Retrieve the model for settings configuration.
     *
     * @abstract
     *
     * @return AbstractSettings The model object.
     */
    abstract public function getModelForConfig();

    /**
     * Get model.
     *
     * Retrieve the model for any given model ID.
     *
     *
     * @param int $id
     * @return BaseModel
     */
    final public function getSettingModel(int $id = 0)
    {
        if (!isset($this->models_cache[$id])) {
            $this->models_cache[$id] = $this->createModel($id);
        }

        return $this->models_cache[ $id ];
    }

    /**
     * Ajax request to save settings.
     *
     * Save settings using an ajax request.
     *
     *
     * @param array $request Ajax request.
     *
     * @return array Ajax response data.
     */
    final public function ajaxSaveSettings($request)
    {
        $data = $request['data'];

        $id = 0;

        if (!empty($request['id'])) {
            $id = $request['id'];
        }

        $this->beforeSaveSettings($data, $id);
        $this->saveSettings($data, $id);

        $settings_name = static::NAME;

        $success_response_data = [];

        /**
         * Settings success response data.
         *
         * Filters the success response data when saving settings using ajax.
         *
         * The dynamic portion of the hook name, `$settings_name`, refers to the settings name.
         *
         *
         * @param array $success_response_data Success response data.
         * @param int   $id                    Settings ID.
         * @param array $data                  Settings data.
         */
        return HooksHelper::applyFilters("pagebuilder/settings/{$settings_name}/success_response_data", $success_response_data, $id, $data);
    }

    /**
     * Save settings.
     *
     * Save settings to the database and update the CSS file.
     *
     *
     * @param array $settings Settings.
     * @param int $id Optional. ContentCss ID. Default is `0`.
     * @return AbstractSettingsManager
     */
    final public function saveSettings(array $settings, $id = 0)
    {
        $special_settings = $this->getSpecialSettingsNames();

        $settings_to_save = $settings;

        foreach ($special_settings as $special_setting) {
            if (isset($settings_to_save[ $special_setting ])) {
                unset($settings_to_save[ $special_setting ]);
            }
        }

        $this->saveSettingsToDb($settings_to_save, $id);

        return $this;
    }

    /**
     * Add settings CSS rules.
     *
     * Add new CSS rules to the settings manager.
     *
     * @param AbstractCss $css_file The requested CSS file.
     */
    public function addSettingsCssRules(AbstractCss $css_file)
    {
        $model = $this->getModelForCssFile($css_file);

        $css_file->addControlsStackStyleRules(
            $model,
            $model->getStyleControls(),
            $model->getSettings(),
            [ '{{WRAPPER}}' ],
            [ $model->getCssWrapperSelector() ]
        );
    }

    /**
     * On SagoTheme init.
     *
     * Add editor template for the settings
     *
     */
    public function addTemplate()
    {
        $name = static::NAME;
        ?>
        <script type="text/template" id="tmpl-gmt-panel-<?= EscaperHelper::escapeHtml($name); ?>-settings">
            <?php $this->printEditorTemplateContent($name); ?>
        </script>
        <?php
    }

    /**
     * Get saved settings.
     *
     * Retrieve the saved settings from the database.
     *
     * @abstract
     *
     * @param int $id ContentCss ID.
     */
    abstract protected function getSavedSettings($id);

    /**
     * Get CSS file name.
     *
     * Retrieve CSS file name for the settings base manager.
     *
     * @abstract
     */
    abstract protected function getCssFileName();

    /**
     * Save settings to DB.
     *
     * Save settings to the database.
     *
     * @abstract
     *
     * @param array $settings Settings.
     * @param int   $id       ContentCss ID.
     */
    abstract protected function saveSettingsToDb(array $settings, $id);

    /**
     * Get model for CSS file.
     *
     * Retrieve the model for the CSS file.
     *
     * @abstract
     *
     * @param AbstractCss $cssFile The requested CSS file.
     */
    abstract protected function getModelForCssFile(AbstractCss $cssFile);

    /**
     * Get CSS file for update.
     *
     * Retrieve the CSS file before updating it.
     *
     * @abstract
     *
     * @param int $id ContentCss ID.
     */
    abstract protected function getCssFileForUpdate($id);

    /**
     * Get special settings names.
     *
     * Retrieve the names of the special settings that are not saved as regular
     * settings. Those settings have a separate saving process.
     *
     *
     * @return array Special settings names.
     */
    protected function getSpecialSettingsNames()
    {
        return [];
    }

    /**
     * Ajax before saving settings.
     *
     * Validate the data before saving it and updating the data in the database.
     *
     *
     * @param array $data ContentCss data.
     * @param int   $id   ContentCss ID.
     */
    public function beforeSaveSettings(array $data, $id)
    {
        return $this;
    }

    /**
     * Print the setting template content in the editor.
     *
     * Used to generate the control HTML in the editor using Underscore JS
     * template. The variables for the class are available using `data` JS
     * object.
     *
     *
     * @param string $name Settings panel name.
     */
    protected function printEditorTemplateContent($name)
    {
        ?>
		<div class="gmt-panel-navigation">
			<# _.each( goomento.config.settings.<?= EscaperHelper::escapeHtml($name); ?>.tabs, function( tabTitle, tabSlug ) {
				$e.bc.ensureTab( 'panel/<?= EscaperHelper::escapeHtml($name); ?>-settings', tabSlug );
			#>
				<div class="gmt-component-tab gmt-panel-navigation-tab gmt-tab-control-{{ tabSlug }}" data-tab="{{ tabSlug }}">
					<a href="#">{{{ tabTitle }}}</a>
				</div>
				<# } ); #>
		</div>
		<div id="gmt-panel-<?= EscaperHelper::escapeHtml($name); ?>-settings-controls"></div>
		<?php
    }

    /**
     * Create model.
     *
     * Create a new model object for any given model ID and store the object in
     * models cache property for later use.
     */
    public abstract function createModel(?int $id) : AbstractSettings;

    /**
     * Get editor template.
     *
     * Retrieve the final HTML for the editor.
     *
     *
     * @return string Settings editor template.
     */
    private function getEditorTemplate()
    {
        $name = static::NAME;

        ob_start(); ?>
		<script type="text/template" id="tmpl-gmt-panel-<?= EscaperHelper::escapeHtml($name); ?>-settings">
			<?php $this->printEditorTemplateContent($name); ?>
		</script>
		<?php

        return ob_get_clean();
    }
}
