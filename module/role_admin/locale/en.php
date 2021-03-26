<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Application-level dictionary (string bundle)                  //
// Date: 2008-11-06                                                           //
// Unless otherwise specified, all strings are plain text (e.g. not HTML)          //
////////////////////////////////////////////////////////////////////////////////

$DICT = array(
    'LABEL_role_name' => 'Role Name',
    'LABEL_parent_role' => 'Parent Role',
    'LABEL_edm_role' => 'Role in avalade e-Marketing',
    'LABEL_view' => 'View',
    'LABEL_create' => 'Create',
    'LABEL_edit' => 'Edit',
    'LABEL_approve' => 'Approve',
    'LABEL_publish' => 'Publish',
    'LABEL_export' => 'Export',
    'LABEL_admin_site_permission' => 'Admin site permission',
    'LABEL_webpage_admin_permission' => 'Webpage admin permission',
    'LABEL_accessible_webpages' => 'Accessible web page sections',

    'ERROR_role_name_empty' => 'Please enter a role name',
    'ERROR_parent_role_empty' => 'You must specify a parent role',
    'ERROR_parent_role_invalid' => 'Invalid parent role. Please make sure it is selected and of the same type.',
    'ERROR_role_name_exists' => 'Role Name already exists, please specify another role name',
    'ERROR_role_rights_invalid' => 'Invalid Role Rights. Please make sure the parent has the rights you specified',
    'ERROR_root_role_cannot_disable' => 'This is a root role and cannot be disabled',

    'TITLE_admin_role_info' => 'Admin role information',
    'TITLE_admin_roles' => 'Admin Roles',
    'TITLE_public_role_info' => 'Public role information',
    'TITLE_public_roles' => 'Public Roles',
    
    'MESSAGE_cannot_disabled' => 'This role cannot be disabled currently because there are active users under it.',

    'SET_edm_roles' => array(
        'system_administrator' => 'System Adminstrator',
        'promotion_manager' => 'Promotion Manager',
        'client' => 'Client'
    ),
    'SET_role_statuses' => array(
        1 => 'Enabled',
        0 => 'Disabled'
    )
);

?>