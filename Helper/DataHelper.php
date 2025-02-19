<?php
/**
 * @package Goomento_PageBuilder
 * @link https://github.com/Goomento/PageBuilder
 */

declare(strict_types=1);

namespace Goomento\PageBuilder\Helper;

use Goomento\Core\Traits\TraitStaticCaller;
use Goomento\Core\Traits\TraitStaticInstances;
use Magento\Store\Model\ScopeInterface;
use Goomento\PageBuilder\Helper\Data;

/**
 * @see \Goomento\PageBuilder\Helper\Data
 * @method static mixed getConfig($path, $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null)
 * @see Data::getConfig()
 * @method static mixed getBuilderConfig($path, $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null)
 * @see Data::getBuilderConfig()
 * @method static bool getAllowedDownloadImage() Whether download image or not
 * @see Data::getAllowedDownloadImage()
 * @method static bool isActive() Deprecated Decide to use should be following the WYSIWYG or On/Off option each content
 * @see Data::isActive()
 * @method static string getDownloadFolder()
 * @see Data::getDownloadFolder()
 * @method static bool useInlineCss()
 * @see Data::useInlineCss()
 * @method static string getFbAppId()
 * @see Data::getFbAppId()
 * @method static string getGoogleMapsKey()
 * @see Data::getGoogleMapsKey()
 * @method static bool isLocalFont()
 * @see Data::isLocalFont()
 * @method static bool isDebugMode()
 * @see Data::isDebugMode()
 * @method static bool addResourceGlobally()
 * @see Data::addResourceGlobally()
 * @method static bool isCssMinifyFilesEnabled()
 * @see Data::isCssMinifyFilesEnabled()
 * @method static bool isJsMinifyFilesEnabled()
 * @see Data::isJsMinifyFilesEnabled()
 */
class DataHelper
{
    use TraitStaticInstances;
    use TraitStaticCaller;

    /**
     * @return bool
     */
    public static function isRtl(): bool
    {
        return (bool) self::getBuilderConfig('editor/is_rtl');
    }

    /**
     * @param array $array
     * @param $path
     * @param $value
     * @param string $delimiter
     */
    public static function arraySetValue(array &$array, $path, $value, string $delimiter = '/')
    {
        if (!is_array($path)) {
            $path = explode($delimiter, (string)$path);
        }

        $ref = &$array;

        foreach ($path as $parent) {
            if (isset($ref) && !is_array($ref)) {
                $ref = [];
            }

            $ref = &$ref[$parent];
        }

        $ref = $value;
    }

    /**
     * @param $array
     * @param $path
     * @param string $delimiter
     */
    public static function arrayUnsetValue(&$array, $path, $delimiter = '/')
    {
        if (!is_array($path)) {
            $path = explode($delimiter, $path);
        }

        $key = array_shift($path);

        if (empty($path)) {
            unset($array[$key]);
        } else {
            self::arrayUnsetValue($array[$key], $path);
        }
    }

    /**
     * @param array $array
     * @param $path
     * @param string $delimiter
     * @return array|mixed|null
     */
    public static function arrayGetValue(array &$array, $path, string $delimiter = '/')
    {
        if (!is_array($path)) {
            $path = explode($delimiter, $path);
        }

        $ref = &$array;

        foreach ((array)$path as $parent) {
            if (is_array($ref) && array_key_exists($parent, $ref)) {
                $ref = &$ref[$parent];
            } else {
                return null;
            }
        }
        return $ref;
    }

    /**
     * @param $datetime
     * @param false $full
     * @return string
     * @throws \Exception
     */
    public static function timeElapsedString($datetime, bool $full = false)
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];

        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        $string = $string ? implode(', ', $string) . ' ago' : 'just now';
        $string .= ' (' . $ago->format('F j, Y, g:i a') . ')';
        return $string;
    }

    /**
     * @param $string
     * @return bool
     */
    public static function isJson($string)
    {
        return !(json_decode($string, true) == null);
    }

    /**
     * Inject data to array
     *
     * @param $array
     * @param $key
     * @param $insert
     * @return array
     */
    public static function arrayInject($array, $key, $insert)
    {
        $length = array_search($key, array_keys($array), true) + 1;

        return array_slice($array, 0, $length, true) +
            $insert +
            array_slice($array, $length, null, true);
    }

    /**
     *
     * @param string $handle
     * @param string $js_var
     * @param mixed $config
     */
    public static function printJsConfig($handle, $js_var, $config)
    {
        $config = json_encode($config, JSON_INVALID_UTF8_SUBSTITUTE);

        $config = str_replace('}},"', '}},' . PHP_EOL . '"', $config);

        $script_data = 'var ' . $js_var . ' = ' . $config . ';';

        ThemeHelper::inlineScript($handle, $script_data, 'before');
    }

    /**
     * Get placeholder image source.
     *
     * Retrieve the source of the placeholder image.
     *
     *
     * @return string The source of the default placeholder image used by SagoTheme.
     */
    public static function getPlaceholderImageSrc()
    {
        $placeholder_image = UrlBuilderHelper::urlStaticBuilder('Goomento_PageBuilder::images/placeholder.png');

        /**
         * Get placeholder image source.
         *
         * Filters the source of the default placeholder image used by SagoTheme.
         *
         *
         * @param string $placeholder_image The source of the default placeholder image.
         */
        return HooksHelper::applyFilters('pagebuilder/utils/get_placeholder_image_src', $placeholder_image);
    }

    /**
     * Render html attributes
     *
     * @param array $attributes
     *
     * @return string
     */
    public static function renderHtmlAttributes(array $attributes)
    {
        $renderedAttributes = [];

        foreach ($attributes as $attributeKey => $attributeValues) {
            if (is_array($attributeValues)) {
                foreach ($attributeValues as &$value) {
                    if (!is_scalar($value)) {
                        if (is_array($value)) {
                            $value = \Zend_Json::encode($value);
                        } elseif (is_object($value) && method_exists($value, '__toString')) {
                            $value = $value->__toString();
                        } else {
                            try {
                                $value = (string) $value;
                            } catch (\Exception $e) {
                                $value = '';
                            }
                        }
                    }
                }
                $attributeValues = implode(' ', $attributeValues);
            }

            $renderedAttributes[] = sprintf('%1$s="%2$s"', $attributeKey, EscaperHelper::escapeHtmlAttr($attributeValues));
        }

        return implode(' ', $renderedAttributes);
    }

    /**
     * Generate random string.
     *
     * Returns a string containing a hexadecimal representation of random number.
     *
     *
     * @return string Random string.
     */
    public static function generateRandomString()
    {
        return dechex(rand());
    }

    /**
     * Compare conditions.
     *
     * Whether the two values comply the comparison operator.
     *
     *
     * @param mixed  $left_value  First value to compare.
     * @param mixed  $right_value Second value to compare.
     * @param string $operator    Comparison operator.
     *
     * @return bool Whether the two values complies the comparison operator.
     */
    public static function compare($left_value, $right_value, $operator)
    {
        switch ($operator) {
            case '==':
                return $left_value == $right_value;
            case '!=':
                return $left_value != $right_value;
            case '!==':
                return $left_value !== $right_value;
            case 'in':
                return in_array($left_value, $right_value, true);
            case '!in':
                return !in_array($left_value, $right_value, true);
            case 'contains':
                return in_array($right_value, $left_value, true);
            case '!contains':
                return !in_array($right_value, $left_value, true);
            case '<':
                return $left_value < $right_value;
            case '<=':
                return $left_value <= $right_value;
            case '>':
                return $left_value > $right_value;
            case '>=':
                return $left_value >= $right_value;
            default:
                return $left_value === $right_value;
        }
    }

    /**
     * Check conditions.
     *
     * Whether the comparison conditions comply.
     *
     *
     * @param array $conditions The conditions to check.
     * @param array $comparison The comparison parameter.
     *
     * @return bool Whether the comparison conditions comply.
     */
    public static function check(array $conditions, array $comparison)
    {
        $is_or_condition = isset($conditions['relation']) && 'or' === $conditions['relation'];

        $condition_succeed = !$is_or_condition;

        foreach ($conditions['terms'] as $term) {
            if (!empty($term['terms'])) {
                $comparison_result = self::check($term, $comparison);
            } else {
                preg_match('/(\w+)(?:\[(\w+)])?/', $term['name'], $parsed_name);

                $value = $comparison[$parsed_name[1]];

                if (!empty($parsed_name[2])) {
                    $value = $value[$parsed_name[2]];
                }

                $operator = null;

                if (!empty($term['operator'])) {
                    $operator = $term['operator'];
                }

                $comparison_result = self::compare($value, $term['value'], $operator);
            }

            if ($is_or_condition) {
                if ($comparison_result) {
                    return true;
                }
            } elseif (!$comparison_result) {
                return false;
            }
        }

        return $condition_succeed;
    }

    /**
     * @param string $str
     * @param $separator
     * @return array
     */
    public static function extractStr(string $str, $separator = ',') : array
    {
        $data = explode($separator, $str);
        if (!empty($data)) {
            $data = array_map('trim', $data);
            $data = array_filter($data);
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    static protected function getStaticInstance()
    {
        return Data::class;
    }
}
