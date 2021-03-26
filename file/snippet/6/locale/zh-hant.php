<?php

////////////////////////////////////////////////////////////////////////////////
// Description: Application-level dictionary (string bundle)                  //
// Date: 2008-11-04                                                           //
// Unless otherwise specified, all strings are plain text (e.g. not HTML)          //
////////////////////////////////////////////////////////////////////////////////

$DICT = array(
	
	'LABEL_information'  => '所有資料必須填寫',
	
	'LABEL_name' => '姓名',
	'LABEL_firstname' => '名字',
	'LABEL_lastname' => '姓氏',
	'LABEL_telephone' => '電話號碼',
	'LABEL_email' => '電郵地址',
	'LABEL_number' => '電話號碼',
	'LABEL_area_code' => '地區號碼',
	'LABEL_country_code' => '國家號碼',
	'LABEL_message_type' => '您想查詢的性質',
	'LABEL_select_msg_type' => '請選擇查詢的性質',
	'LABEL_message'  => '訊息',
	'LABEL_submit'  => '提交',

    'MESSAGE_subtitle' => '您的寶貴意見，是帶領我們前進的方向。我們希望了解您的所思所想，藉此送上最切合您個人需要的服務。請在下列方格內提供您的意見及聯絡資料，我們會盡快與您聯絡。',
	'MESSAGE_request_sent' => '感謝您的寶貴意見！我們會盡快作出跟進，並熱切期待繼續透過嘉譽禮賞，為您送上最貼心的個人化服務。',
    'MESSAGE_request_not_sent' => '很抱歉，您沒有發送成功。請再試一次。',
    'MESSAGE_request_error' => '參數錯誤。請再試一次。',
	'MESSAGE_contact_subject_line' => '新的訊息',
	
	'ERROR_firstname_blank' => '請輸入名字。',
	'ERROR_lastname_blank' => '請輸入姓氏。',
	'ERROR_email_blank' => '請輸入電郵地址。',
	'ERROR_country_code_blank' => '請輸入國家號碼。',
	'ERROR_area_code_blank' => '請輸入地區號碼。',
	'ERROR_telephone_blank' => '請輸入電話號碼。',
	'ERROR_message_type_blank' => '請選擇查詢的性質。',
	'ERROR_message_blank' => '請輸入訊息。',
	'ERROR_email_invalid' => '請輸入正確電郵地址。',
	
	'SET_titles' => array(
		"mr"=>"先生",
		"mrs"=>"太太",
		"miss"=>"小姐",
		"ms"=>"女士",
		"dr"=>"博士",
		"prof"=>"教授"
	),
	
	'SET_message_type' => array(
		1 => '一般查詢',
		2 => '登入問題',
		3 => '更新個人資料',
		4 => '忘記密碼',
		5 => '遺失會員卡',
		6 => '遺漏消費記錄'
	),
	'SET_message_type_email' => array(
		1 => 'enquiry@iprestigerewards.com',
		2 => 'login@iprestigerewards.com',
		3 => 'profile@iprestigerewards.com',
		4 => 'forgotpassword@iprestigerewards.com',
		5 => 'lostcard@iprestigerewards.com',
		6 => 'spending@iprestigerewards.com'
	)
	
);

?>