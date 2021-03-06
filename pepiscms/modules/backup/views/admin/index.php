<?php $is_utilities_only_module = FALSE; ?>
<?php if ($this->input->getParam('layout') != 'popup'): ?>
    <?php
    $breadcrumb_array = array(module_url() => $title);

    if( ModuleRunner::isModuleDisplayedInMenu() )
    {
        $parent_module_name = ModuleRunner::getParentModuleName();
        if( $parent_module_name )
        {
            $breadcrumb_array = array_merge(array(module_url($parent_module_name) => $this->Module_model->getModuleLabel($parent_module_name, $this->lang->getCurrentLanguage())), $breadcrumb_array);
        }
    }
    else
    {
        // If module is displayed in UTILITIES and not in MENU then display a back link
        if( ModuleRunner::isModuleDisplayedInUtilities($this->modulerunner->getRunningModuleName()) )
        {
            $is_utilities_only_module = TRUE;
            $breadcrumb_array = array_merge(array(admin_url() . 'utilities' => $this->lang->line('label_utilities_and_settings')), $breadcrumb_array);
        }
    }
    ?>

    <?= display_breadcrumb($breadcrumb_array, module_icon_url()) ?>
<?php endif; ?>

<?php
$actions = array();
if( $is_utilities_only_module )
{
    $actions[] = array(
        'name' => $this->lang->line('global_button_back_to_utilities'),
        'link' => admin_url() . 'utilities',
        'icon' => 'pepiscms/theme/img/dialog/actions/back_16.png', // 'pepiscms/theme/img/dialog/actions/action_16.png', 'pepiscms/theme/img/dialog/actions/add_16.png'
        //'class' => ($this->input->getParam('layout') == 'popup' ? 'popup' : ''),
    );
}
?>
<?php if(count($actions)): ?>
    <?= display_action_bar($actions) ?>
<?php endif; ?>

<?= display_session_message() ?>

<?= display_tip($this->lang->line('backup_index_tip')) ?>

<?php if ($this->config->item('cms_enable_pages')): ?>
    <h1 class="contrasted"><?=$this->lang->line('backup_sql_backup')?></h1>
<?php endif; ?>

<ul class="dashboard_actions clear">
    <?php foreach($this->Module_model->getModuleAdminSubmenuElements($this->modulerunner->getRunningModuleName(), 'dontcare') as $descriptor): ?>
        <?= dashboard_box($descriptor['label'], module_url($descriptor['controller']) . $descriptor['method'], str_replace('_16.', '_32.', $descriptor['icon_url']), $descriptor['description']) ?>
    <?php endforeach ?>
</ul>

<?php if ($this->config->item('cms_enable_pages')): ?>
<h1 class="contrasted"><?=$this->lang->line('backup_xml_backup')?></h1>

<ul class="dashboard_actions clear">
    <?= dashboard_box($this->lang->line('backup_xml_do'), module_url() . 'xml_do', module_resources_url() . 'do_backup_32.png', $this->lang->line('backup_xml_do_description')) ?>
    <?= dashboard_box($this->lang->line('backup_xml_restore'), module_url() . 'xml_restore', module_resources_url() . 'restore_backup_32.png', $this->lang->line('backup_xml_restore_description')) ?>
</ul>
<?php endif; ?>

<?=$this->widget->create('logs', 'logs')->render( 'BACKUP' );