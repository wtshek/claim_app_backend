<?php
/**
 * File: functions.php
 * User: Patrick Yeung <patrick{at}avalade{dot}com>
 * Date: 03/07/2013 10:22
 * Description: 
 */

class tmpSmarty {
    public $smarty;

    /**
     * Call this method to get singleton
     *
     * @return tmpSmarty
     */
    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new tmpSmarty();
        } else {
            $inst->smarty->clearAllAssign();
        }
        return $inst;
    }

    /**
     * Private ctor so nobody else can instance it
     *
     */
    private function __construct()
    {
        $this->smarty = new Smarty();

        $this->smarty->config_dir = kernel::getInstance()->sets['paths']['app_root'] . "/conf";
        $this->smarty->template_dir = __DIR__. "/ui";
        $this->smarty->compile_dir = kernel::getInstance()->sets['paths']['temp_root'] . "/templates_c/2";
        $this->smarty->cache_dir = kernel::getInstance()->sets['paths']['temp_root'] . "/cache/2";
        $this->smarty->caching = false;
        $this->smarty->cache_modified_check = true;
        if ( !force_mkdir(kernel::getInstance()->sets['paths']['temp_root']) )
        {
            kernel::getInstance()->quit( 'Error creating temporary directory.', kernel::getInstance()->sets['paths']['temp_root'] );
        }
        force_mkdir( $this->smarty->compile_dir );
        force_mkdir( $this->smarty->cache_dir );
    }

    public function assignParams($params) {

        foreach($params as $k => $v) {
            if(preg_match('#^data\-rule#i', $k))
                $params['extra'][] = sprintf('%s = "%s"', $k, htmlspecialchars($v));
            else
                $this->smarty->assign($k, $v);
        }

        if(count($params['extra']) > 0) {
            $this->smarty->assign('extra', $params['extra']);
        }
    }

}

function generate_smarty_block_output($params, $type, $tpl) {
    /** @var tmpSmarty $tmp */
    $tmp = tmpSmarty::Instance();
    $params['hasError'] = false;

    if(!isset($params['wrap']))
        $params['wrap'] = true;

    $params['extra'] = array();

    if(isset($params['ratio'])) {
        $ratio = explode(':', $params['ratio']);
        $params['ratio_title'] = $ratio[0];
        $params['ratio_content'] = $ratio[1];
    } else {
        $params['ratio_title'] = 4;
        $params['ratio_content'] = 8;
    }

    if(isset($params['error']) && !is_null($params['error'])) {
        $params['hasError'] = true;
        $params['errorMsg'] = is_array($params['error']) ? $params['error'][0] : $params['error'];
    }

    if(!in_array($type, array('text')) && isset($params['default']) &&
        (is_null($params['selected']) || $params['selected'] === "")
        && $params['default']) {
        $params['selected'] = $params['default'];
    }

    $tmp->assignParams($params);

    $tmp->smarty->assign('field_type', $type);
    $cache_id = md5(serialize($params)) . $tpl;

    $tmp->smarty->assign('content', $tmp->smarty->fetch($tpl, $cache_id));

    return $tmp->smarty->fetch('field-wrapper.tpl', $cache_id . "_wrap");
}

function smarty_block_field_text($params, $smarty) {
    $params = array_merge(array('name' => null, 'title' => null, 'id' => null, 'value' => null, 'multiple' => false, 'type' => null, 'maxlength' => null, 'placeholder' => false, 'view_only' => false, 'class' => '', 'default' => '', 'style' => ''), $params);
    return generate_smarty_block_output($params, "text", "field_text.tpl");
}

function smarty_block_field_radio($params, $smarty) {
    $params = array_merge(array('name' => null, 'title' => null, 'id' => null, 'value' => null, 'multiple' => false, 'type' => null, 'selected' => null, 'checked' => false, 'options' => array(), 'class' => '', 'placeholder' => false, 'view_only' => false, 'default' => '', 'style' => ''), $params);
    return generate_smarty_block_output($params, "radio", "field_radio.tpl");
}

function smarty_block_field_select($params, $smarty) {
    $params = array_merge(array('name' => null, 'title' => null, 'id' => null, 'value' => null, 'multiple' => false, 'type' => null, 'has_empty' => false, 'selected' => '', 'options' => array(), 'disabled_items' => array(), 'placeholder' => false, 'view_only' => false, 'class' => '', 'default' => '', 'style' => ''), $params);
    return generate_smarty_block_output($params, "select", "field_select.tpl");
}

function smarty_block_field_checkbox($params, $smarty) {
    $params = array_merge(array('name' => null, 'title' => null, 'id' => null, 'value' => null, 'multiple' => false, 'type' => null, 'checked' => false, 'class' => '', 'placeholder' => false, 'style' => ''), $params);
    return generate_smarty_block_output($params, "checkbox", "field_checkbox.tpl");
}

function smarty_block_field_calendar($params, $smarty) {
    $params = array_merge(array('name' => null, 'title' => null, 'id' => null, 'format' => '%Y-%m-%d %H:%M', 'showsTime' => true, 'value' => null, 'multiple' => false, 'checked' => false, 'view_only' => false, 'class' => '', 'placeholder' => false, 'style' => ''), $params);
    return generate_smarty_block_output($params, "calendar", "field_calendar.tpl");
}

function smarty_block_field_calendar_old_lib($params, $smarty) {
    $params = array_merge(array('name' => null, 'title' => null, 'id' => null, 'format' => '%Y-%m-%d %H:%M', 'showsTime' => true, 'value' => null, 'multiple' => false, 'checked' => false, 'view_only' => false, 'class' => '', 'placeholder' => false, 'style' => ''), $params);
    return generate_smarty_block_output($params, "calendar", "field_calendar_old_lib.tpl");
}

function smarty_block_field_textarea($params, $smarty) {
    $params = array_merge(array('name' => null, 'title' => null, 'id' => null, 'value' => null, 'multiple' => false, 'type' => null, 'maxlength' => null, 'placeholder' => false, 'view_only' => false, 'class' => '', 'style' => ''), $params);
    return generate_smarty_block_output($params, "textarea", "field_textarea.tpl");
}