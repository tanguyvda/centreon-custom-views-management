<?php 
$path = $centreon_path . 'www/modules/centreon-custom-views-management/core/';
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, './', $centreon_path);


if (!isset($centreon)) {
    exit();
}

if (!$centreon->user->admin) {
    $template->display('error.ihtml');
} else {
    $form = new HTML_QuickFormCustom('Form', 'post', '?p=64401');

    $contactRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_contact&action=list';
    $attrContacts = array(
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $contactRoute,
        'multiple' => false,
        'linkedObject' => 'centreonContact'
    );

    $form->addElement('select2', 'contacts', _("Users"), array(), $attrContacts);

    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
    $form->accept($renderer);
    $template->assign('form', $renderer->toArray());
    $template->display('index.ihtml');
}