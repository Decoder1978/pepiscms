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
 * Parent class for all admin controllers (not modules).
 * Provides a simple user verification.
 */
abstract class AdminController extends EnhancedController
{

    /**
     * Default constructor
     *
     * @param bool $render_menu
     * @param bool $skip_authorization
     */
    public function __construct($render_menu = true, $skip_authorization = false)
    {
        parent::__construct();

        $this->benchmark->mark('admin_controller_construct_start');

        $this->load->library('Auth');
        $this->load->library('Logger');
        $this->load->library('SecurityPolicy');
        $this->load->library('SecurityManager');
        $this->load->library('ModuleRunner');
        $this->load->helper('cookie');

        // Authorization
        if (!$skip_authorization) {
            $this->benchmark->mark('authorization_start');
            if (!$this->auth->isAuthorized()) {
                $_SESSION['request_redirect'] = $this->uri->uri_string();
                redirect(admin_url() . 'login/sessionexpired');
            }
            $this->benchmark->mark('authorization_end');
        }

        // Determining user language
        $language = $this->lang->getCurrentLanguage();
        if (!$language) {
            $language = $this->config->item('language');
        }

        $this->lang->load('core', $language);
        $this->lang->load('modules', $language);
        $this->lang->load('login', $language);
        $this->lang->load('utilities', $language);
        $this->lang->load('global', $language);
        $this->lang->load('changepassword', $language);

        // Determining controller and method names
        $controller = $this->uri->segment(2);
        $method = $this->uri->segment(3);
        if (empty($method)) {
            $method = 'index';
        }

        // Assigning controller and method name
        $this->setControllerName($controller);
        $this->setMethodName($method);


        // Validating if password is expired
        if ($this->auth->isUserPasswordExpired() && $controller != 'changepassword' && $controller != 'logout') {
            redirect(admin_url() . 'changepassword');
        }

        // Determining whether layout is of popup type
        $popup_layout = ($this->input->getParam('layout') == 'popup');

        // Assigning core variables
        $this->assign('body_id', strtolower('controller-' . $controller));
        $this->assign('popup_layout', $popup_layout);
        $this->assign('user', $this->auth->getUser());
        $this->assign('lang', $this->lang);
        $this->assign('application_languages', $this->lang->getEnabledAdminLanguages());
        $this->assign('current_language', $language);
        $this->assign('site_name', $this->config->item('site_name'));

        // Rendering the menu if specified
        if ($render_menu) {
            $this->assign('adminmenu', ''); // Just in case

            // Rendering menu only if the layout is different from popup
            if (!$popup_layout) {
                $this->benchmark->mark('menu_render_start');
                $rendered_menu = null;

                // Checking whether the hook method exists
                if (method_exists($this, 'renderMenu')) {
                    $rendered_menu = $this->renderMenu();
                    $this->assign('adminmenu', $rendered_menu);
                }

                // Only if the menu was not rendered before
                if (!$rendered_menu) {
                    $this->load->library('MenuRendor');
                    $this->assign('adminmenu', $this->menurendor->render($controller, $method, $this->input->getParam('language_code')));
                }
                $this->benchmark->mark('menu_render_end');
            }
        }

        // Checking user access rights
        $this->assign('security_policy_violaton', false);
        if (!SecurityManager::hasAccess($controller, $method)) {
            Logger::warning('Security policy violation ' . $controller . '/' . $method, 'SECURITY');

            ob_start();
            $this->assign('security_policy_violaton', true);
            $this->display('admin/no_sufficient_priviliges', true, true);
            $out = ob_get_contents();
            ob_end_clean();
            die($out);
        }

        $this->benchmark->mark('admin_controller_construct_end');
    }

    /**
     * Loads and displays view.
     *
     * @param string|bool $view
     * @param boolean $display_header
     * @param boolean $display_footer
     * @param boolean $return
     * @return bool
     */
    public function display($view = false, $display_header = true, $display_footer = true, $return = false)
    {
        $return_html = true;
        if ($this->already_displayed && !$return) {
            return false;
        }

        if (!$view) {
            $view = 'admin/' . $this->getControllerName() . '_' . $this->getMethodName();
        }

        // Displaying header if specified
        if ($display_header) {
            $last_view_html = $this->load->view('/admin/application_header', $this->response_attributes, $return);
            if ($return) {
                $return_html .= $last_view_html;
            }
        }

        $last_view_html = $this->load->view($view, $this->response_attributes, $return);
        if ($return) {
            $return_html .= $last_view_html;
        }

        // Displaying footer if specified
        if ($display_footer) {
            $last_view_html = $this->load->view('/admin/application_footer', $this->response_attributes, $return);
            if ($return) {
                $return_html .= $last_view_html;
            }
        }

        // Resetting
        $this->response_attributes = array();
        $this->already_displayed = true;

        // Returning HTML
        if ($return) {
            return $return_html;
        }
    }
}
