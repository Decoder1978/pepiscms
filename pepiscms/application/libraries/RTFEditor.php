<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * PepisCMS
 *
 * Simple content management system
 *
 * @package             PepisCMS
 * @author              Piotr Polak
 * @copyright           Copyright (c) 2007-2018, Piotr Polak
 * @license             See license.txt
 * @link                http://www.polak.ro/
 */

/**
 * RTF Editor
 *
 * @since 0.1
 */
class RTFEditor
{
    private $type;
    private $driver;
    private $available_editors = array('cke' => 'CKEditorDriver');

    /**
     * Default constructor, empty
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->type = isset($params[0]) ? $params[0] : 'cke'; // Default one

        if (isset($this->available_editors[$this->type])) {
            $driver_name = $this->available_editors[$this->type];

            $clazz = "\\Piotrpolak\\Pepiscms\\Editor\\$driver_name";
            $this->driver = new $clazz();
        } else {
            $this->driver = new \Piotrpolak\Pepiscms\Editor\CKEditorDriver(); // DEFAULT ONE
        }
    }

    /**
     * Returns list of available editors
     *
     * @return array
     */
    public function getAvailableEditors()
    {
        return $this->available_editors;
    }

    /**
     * Sets the list of available editors, 'shortcut' to class name
     *
     * @param array $available_editors
     * @return bool
     */
    public function setAvailableEditors($available_editors)
    {
        if (!is_array($available_editors)) {
            return FALSE;
        }

        $this->available_editors = array();

        foreach ($available_editors as $key => $value) {
            if ($key && $value) {
                $this->available_editors[$key] = $value;
            }
        }
        return TRUE;
    }

    /**
     * Sets config variable
     *
     * @param string $field_name
     * @param string $value
     */
    public function setConfig($field_name, $value)
    {
        $this->driver->setConfig($field_name, $value);
    }

    /**
     * Returns config variable
     *
     * @param string $field_name
     * @return string
     */
    public function getConfig($field_name)
    {
        return $this->driver->getConfig($field_name);
    }

    /**
     * Initializes default config
     *
     * @return bool
     */
    public function setupDefaultConfig()
    {
        $CI = &get_instance();

        $CI->load->config('editor');
        $descriptor['editor_css_file'] = $CI->config->item('editor_css_file');
        $descriptor['editor_css_body_id'] = $CI->config->item('editor_css_body_id');
        $descriptor['editor_css_body_class'] = $CI->config->item('editor_css_body_class');
        $descriptor['editor_styles_set_file'] = $CI->config->item('editor_styles_set_file');

        $theme_descriptor_path = './theme/' . $CI->config->item('current_theme') . '/descriptor.php';
        if (file_exists($theme_descriptor_path)) {
            require($theme_descriptor_path);
        }

        $css_file = base_url() . 'theme/' . $CI->config->item('current_theme') . '/' . $descriptor['editor_css_file'];
        if (strpos($descriptor['editor_css_file'], 'http://') !== FALSE || strpos($descriptor['editor_css_file'], 'https://') !== FALSE) {
            $css_file = $descriptor['editor_css_file'];
        } elseif ($descriptor['editor_css_file'][0] == '/') {
            $css_file = base_url() . $descriptor['editor_css_file'];
        }

        $descriptor['editor_css_file'] = $css_file;

        if ($descriptor['editor_styles_set_file']) {
            $styles_set_file = base_url() . 'theme/' . $CI->config->item('current_theme') . '/' . $descriptor['editor_styles_set_file'];
            if (strpos($descriptor['editor_styles_set_file'], 'http://') !== FALSE || strpos($descriptor['editor_styles_set_file'], 'https://') !== FALSE) {
                $styles_set_file = $descriptor['editor_styles_set_file'];
            } elseif ($descriptor['editor_styles_set_file'][0] == '/') {
                $styles_set_file = base_url() . $descriptor['editor_styles_set_file'];
            }

            $descriptor['editor_styles_set_file'] = $styles_set_file;
        }

        return $this->driver->setupDefaultConfig($descriptor);
    }

    /**
     * Generates RTF editor
     *
     * @param string $text
     * @param int $height
     * @param string $instance
     * @return string HTML
     */
    public function generate($text, $height = 500, $instance = 'editor')
    {
        return $this->driver->generate($text, $height, $instance);
    }

    /**
     * Sets editor into full mode
     *
     * @param bool $is_full
     */
    public function setFull($is_full = TRUE)
    {
        $this->driver->setFull($is_full);
    }

    /**
     * Returns whether the editor is in full mode
     *
     * @return bool
     */
    public function isFull()
    {
        return $this->driver->isFull();
    }

}
