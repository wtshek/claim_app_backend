<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Application-level dictionary (string bundle)                  //
// Date: 2009-06-25                                                           //
// Unless otherwise specified, all strings are plain text (e.g. not HTML)     //
////////////////////////////////////////////////////////////////////////////////

$DICT = array(
    // Actions
    'ACTION_send' => 'Send Test Email',

    // Labels
    'LABEL_active_templates_relay' => 'Active templates that are relying on this template:',
    'LABEL_communication_templates' => 'Service Communication Templates',
    'LABEL_css_file' => 'CSS file',
    'LABEL_locale_files' => 'Locale Files',
    'LABEL_platform' => 'Platform',
    'LABEL_root_template' => 'Root Template',
    'LABEL_select_template' => 'Select template',
    'LABEL_template' => 'Template',
    'LABEL_template_path' => 'Template file path',
    'LABEL_unknown' => 'Unknown',
    'LABEL_used_in_templates' => 'Templates used: ',

    // Prompts
    'PROMPT_recipient_email' => 'Please enter the recipient email.',

    // Confirmations
    'CONFIRM_submit_continue' => 'The change will take effect immediately. Are you sure to continue?',

    // Errors
    'ERROR_file_not_found' => 'Could not save the template. File not found.',
    'ERROR_template_content_empty' => 'Please input template content',
    'ERROR_write_file_failed' => 'Fail to write content to the template file. Please try again. Please contact the administrator if problem persists.'
);

?>