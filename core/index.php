<?php 

$file = fopen("/var/opt/rh/rh-php72/log/php-fpm/ccvm", "a") or die ("Unable to open file!");
// fwrite($file, print_r($centreon,true));

$path = $centreon_path . 'www/modules/centreon-custom-views-management/core/';
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, './', $centreon_path);


if (!isset($centreon)) {
    exit();
}

if (!$centreon->user->admin) {
    echo "<pre>you're not admin</pre>";
} else {
    echo "<pre>you're admin</pre>";
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
    // fwrite($file, print_r($renderer->toArray(), true));
    $template->display('index.ihtml');
}


fclose($file);