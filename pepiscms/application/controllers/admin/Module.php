<?php

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

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module management controller
 */
class Module extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Module_model');
        $this->load->library('SimpleSessionMessage');
        $this->load->library('FormBuilder');
        $this->load->library('ConfigBuilder');
        $this->load->library('ModuleRunner');
        $this->load->library('Cachedobjectmanager');
        $this->load->library('User_agent');
        ;
        $this->assign('title', $this->lang->line('label_installed_modules'));
    }

    /** Callback * */
    protected function renderMenu()
    {
        $this->load->library('MenuRendor');

        $controller = 'utilities';
        $method = 'index';
        if ($this->input->getMethodName() == 'run') {
            return false;
        }

        return $this->menurendor->render($controller, $method, $this->input->getParam('language_code'));
    }

    public function index()
    {
        $view = $this->_get_view();
        $this->assign('view', $view);

        if ($view == 'utilities') {
            $this->assign('installed_modules_in_utilities',
                $this->Module_model->getInstalledModulesDisplayedInUtilities());
        } else {
            $this->assign('installed_modules_with_no_parent',
                $this->Module_model->getInstalledModulesHavingNoParent())
                ->assign('installed_modules_with_parrent_grouped_by_parent',
                    $this->Module_model->getInstalledModulesDisplayedInMenuHavingParentGroupedByParent());
        }

        $this->display();
    }

    public function move()
    {
        $direction = $this->input->getParam('direction');
        $module = $this->input->getParam('module');

        $view = $this->_get_view();

        $constraint_field = 'is_displayed_in_' . $view;

        $this->Generic_model->move($module, $direction, $this->config->item('database_table_modules'),
            $constraint_field, 'item_order_' . $view, 'name');

        $this->removeAllCache();

        if ($this->input->getParam('json') == 1) {
            die('{ "status": "1", "message" : "OK" }'); // TODO Serialize
        }

        // Smart redirect
        if ($this->agent->referrer()) {
            redirect($this->agent->referrer());
        } else {
            redirect(admin_url() . 'module');
        }
    }

    public function setup()
    {
        $view = $this->_get_view();

        $notinstalled_modules = array();
        $modules = ModuleRunner::getAvailableModules();

        $installed_modules = $this->Module_model->getInstalledModulesNames();

        foreach ($modules as $module) {
            if (in_array($module, $installed_modules)) {
                continue;
            }
            $notinstalled_modules[] = $module;
        }

        $this->assign('view', $view)
            ->assign('title', $this->lang->line('label_module_setup'))
            ->assign('modules', $notinstalled_modules)
            ->display();
    }

    public function do_setup()
    {
        $view = $this->_get_view();

        $module = $this->input->getParam('module');
        $is_install = $this->input->getParam('install');

        if (!$module) {
            show_404();
        }

        if ($is_install) {
            $back_url = admin_url() . 'module/setup/view-' . $view;
        } else {
            $back_url = admin_url() . 'module/index/view-' . $view;
        }

        $modules_with_no_parent = array();
        $modules_with_no_parent_tmp = $this->Module_model->getInstalledModulesDisplayedInMenuHavingNoParent();
        $modules_with_no_parent[null] = '--';
        foreach ($modules_with_no_parent_tmp as $module_with_no_parent) {
            if ($module_with_no_parent->name == $module) {
                continue;
            }
            $modules_with_no_parent[$module_with_no_parent->module_id] = $this->Module_model->getModuleLabel($module_with_no_parent->name, $this->lang->getCurrentLanguage());
        }

        $is_displayed_in_menu = false;
        $is_displayed_in_utilities = false;
        $moduleDescriptor = $this->Module_model->getModuleDescriptor($module);
        if ($moduleDescriptor) {
            $is_displayed_in_menu = $moduleDescriptor->isDisplayedInMenu();
            $is_displayed_in_utilities = $moduleDescriptor->isDisplayedInUtilities();
        }

        $definition = array(
            'module' => array(
                'label' => $this->lang->line('label_module'),
                'validation_rules' => 'required',
                'input_is_editable' => false,
            ),
            'is_displayed_in_menu' => array(
                'input_type' => FormBuilder::CHECKBOX,
                'validation_rules' => '',
                'label' => $this->lang->line('label_display_in_main_menu'),
                'input_default_value' => $is_displayed_in_menu,
            ),
            'parent_module_id' => array(
                'input_type' => FormBuilder::SELECTBOX,
                'validation_rules' => '',
                'input_is_editable' => true,
                'values' => $modules_with_no_parent,
                'foreign_key_accept_null' => true,
                'label' => $this->lang->line('label_module_parent_module_id'),
            ),
            'is_displayed_in_utilities' => array(
                'input_type' => FormBuilder::CHECKBOX,
                'validation_rules' => '',
                'label' => $this->lang->line('label_display_in_utilities'),
                'input_default_value' => $is_displayed_in_utilities,
            ),
        );

        $config_definition = $this->Module_model->getModuleConfigVariables($module);

        if ($config_definition) {
            foreach ($config_definition as $key => $config) {
                $config['input_group'] = 'Additional configuration variables';
                $key = 'config_' . $key;
                $definition[$key] = $config;
            }
        }

        $this->formbuilder->setId($module)
            ->setBackLink($back_url)
            ->setTitle($this->lang->line('label_module_setup'))
            ->setCallback(array($this, '_fb_callback_setup_on_save'), FormBuilder::CALLBACK_ON_SAVE)
            ->setCallback(array($this, '_fb_callback_setup_on_read'), FormBuilder::CALLBACK_ON_READ)
            ->setDefinition($definition);

        $this->assign('view', $view)
            ->assign('title', $this->lang->line('label_module_setup'))->assign('module_label', $this->Module_model->getModuleLabel($module,
                $this->lang->getCurrentLanguage()))
            ->assign('module', $module)
            ->assign('form', $this->formbuilder->generate())
            ->display();
    }

    public function uninstall()
    {
        $view = $this->_get_view();
        $this->assign('view', $view);

        $module = $this->uri->segment(4);

        Logger::info('Uninstalling module ' . $module, 'MODULE');

        $this->Module_model->uninstall($module);
        $this->removeAllCache();

        $this->simplesessionmessage->setFormattingFunction(SimpleSessionMessage::FUNCTION_SUCCESS);
        $this->simplesessionmessage->setMessage('global_header_success');

        // Smart redirect
        if ($this->agent->referrer()) {
            redirect($this->agent->referrer());
        } else {
            redirect(admin_url() . 'module/index/view-' . $view);
        }
    }

    public function run()
    {
        $module_name = $this->uri->segment(4);
        $method = $this->uri->segment(5);

        if (!$method) {
            $method = 'index';
        }

        if (!$this->modulerunner->runAdminModule($module_name, $method)) {
            show_404();
        }
    }

    /**
     * Must populate object
     * @param object $object
     */
    public function _fb_callback_setup_on_read(&$object)
    {
        $object = $this->Module_model->getInfoByName($this->formbuilder->getId());
        if (!$object) {
            $object = new stdClass;
        }
        $object->module = $this->formbuilder->getId();
        if (!isset($object->label) || !$object->label) {
            $object->label = str_replace('_', ' ', ucfirst($this->formbuilder->getId()));
        }
        $config_variables = $this->configbuilder->readConfig($this->getConfigPath($this->formbuilder->getId()));
        if ($config_variables) {
            foreach ($config_variables as $key => $value) {
                $key = 'config_' . $key;
                $object->{$key} = $value;
            }
        }
    }

    /**
     * Must overwrite the save procedure and return true or false
     *
     * @param object $data
     * @return boolean
     */
    public function _fb_callback_setup_on_save(&$data)
    {
        $is_install = $this->input->getParam('install') == 1;
        $module_name = $this->formbuilder->getId();

        if (!$data['is_displayed_in_menu']) {
            $data['parent_module_id'] = null;
        }

        if ($is_install) {
            $success = $this->Module_model->install($module_name, $data['is_displayed_in_menu'],
                $data['is_displayed_in_utilities'], $data['parent_module_id']);
        } else {
            $success = $this->Module_model->update($module_name, $data['is_displayed_in_menu'],
                $data['is_displayed_in_utilities'], $data['parent_module_id']);
        }

        $config_variables = $this->filterConfigData($data);

        if (count($config_variables) > 0) {
            $this->saveModuleConfig($this->filterConfigData($data), $module_name);
        }
        $this->removeAllCache();

        return $success;
    }

    /**
     * @param $module_name
     * @return string
     */
    private function getConfigPath($module_name)
    {
        $config_path = INSTALLATIONPATH . 'application/config/modules/';
        if (!file_exists($config_path)) {
            mkdir($config_path);
        }
        $config_path .= $module_name . '.php';
        return $config_path;
    }

    /**
     * Returns view type
     *
     * @return string
     */
    private function _get_view()
    {
        if (in_array($this->input->getParam('view'), array('menu', 'utilities'))) {
            return $this->input->getParam('view');
        }

        return 'menu';
    }

    /**
     * @param $array
     * @param $module_name
     * @return mixed
     */
    private function saveModuleConfig(&$array, $module_name)
    {
        $config_path = $this->getConfigPath($module_name);

        Logger::info('Configuring module ' . $module_name, 'MODULE');
        return $this->configbuilder->writeConfig($config_path, $array);
    }

    private function removeAllCache()
    {
        $this->auth->refreshSession();
        $this->cachedobjectmanager->cleanup();
        $this->db->cache_delete_all();
        ModuleRunner::flushCache();
    }

    /**
     * @param $data
     * @return array
     */
    private function filterConfigData(&$data)
    {
        $config_data = array();
        foreach ($data as $key => $value) {
            if (strpos($key, 'config_') !== 0) {
                continue;
            }
            $config_data[substr($key, 7)] = $value;
        }
        return $config_data;
    }
}
