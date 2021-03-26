<?php

/**
 * The contact form snippet.
 *
 *
 *
 * @since   2011-08-04 09:00:00
 * @param   module      The module
 * @param   snippet     The snippet
 * @param   parameters  The parameters
 * @return  HTML content
 */
function contact_us_form_snippet( &$module, &$snippet, $parameters )
{
	$conn = db::Instance();
	$locale = $module->kernel->request['locale'];
	require( dirname(__FILE__) . "/locale/{$locale}.php" );
    $module->kernel->smarty->assignByRef( 'contact_dict', $DICT );

	if(count($_POST)>0)
	{
		$error = 0;
		$body = '';

		$ajax = (bool)(array_ifnull($_POST, 'ajax', false));
		$title =  trim(array_ifnull($_POST,'title', ''));
		$firstname =  trim(array_ifnull($_POST, 'first_name', ''));
		$lastname =  trim(array_ifnull($_POST, 'last_name', ''));
		$email = trim(array_ifnull( $_POST, 'email', '' ));
		$telephone = trim(array_ifnull($_POST, 'telephone', ''));
		$address = trim(array_ifnull($_POST, 'address', ''));
		$country = trim(array_ifnull($_POST, 'country', ''));
		$city = trim(array_ifnull($_POST, 'city', ''));
		$zipcode = trim(array_ifnull($_POST, 'zipcode', ''));
		$message = trim(array_ifnull($_POST, 'message', ''));

		$errors = array();

		if(!filter_var($email, FILTER_VALIDATE_EMAIL))
			$errors['email'] = $DICT['ERROR_email_invalid'];

		$format = '';
		if($title  != '' && $firstname !='' && $lastname !='' )
		{
			$title = $DICT['SET_titles'][$title];
			if($locale == 'en')
				$format = $title." ".$firstname." ".$lastname;
			else
				$format = $lastname.$firstname.$title;


			$body .= "<b>Contact Name: </b>".$format."<br>";
		}

		if($email != '')
			$body  .= "<b>Email: </b>".$email."<br>";

		if($telephone != '')
			$body  .= "<b>Phone: </b>".$telephone."<br>";
		
		if($address != '')
			$body  .= "<b>Address: </b>".$address."<br>";
		
		if($country != '')
			$body  .= "<b>Country: </b>".$module->kernel->dict['SET_country'][$module->kernel->request['locale']][$country]."<br>";
		
		if($city != '')
			$body  .= "<b>City: </b>".$city."<br>";
		
		if($zipcode != '')
			$body  .= "<b>Zip Code: </b>".$zipcode."<br>";

		if($message != '')
			$body  .= "<b>Message: </b>".$message."<br>";

		if( $error == 0 && !$ajax)
		{
			// include some customize information
			$message = $message.json_encode(
				array(
					'Country' => $module->kernel->dict['SET_country'][$module->kernel->request['locale']][$country],
					'Address' => $address,
					'City' => $city,
					'Zip Code' => $zipcode
				)
			);
			
			$sql = 'INSERT INTO cms_contact_us_form (title, first_name, last_name, email, country_code, area_code, number, message_type, message, created_time) VALUES (';
			$sql .= $conn->escape($title).','.$conn->escape($firstname).',';
			$sql .= $conn->escape($lastname).','.$conn->escape($email).',';
			$sql .= $conn->escape('').','.$conn->escape('').',';
			$sql .= $conn->escape($telephone).','.$conn->escape('').','.$conn->escape($message).', NOW())';
			$conn->exec($sql);
			
			$target_email = $DICT['VALUE_target_email'];

			$success = FALSE;
			$module->kernel->mailer->IsHTML( TRUE );
			$module->kernel->mailer->ContentType = 'text/html';
			$module->kernel->mailer->Subject = $DICT['MESSAGE_contact_subject_line'];
			$module->kernel->mailer->Body = $body;
			//$module->kernel->mailer->From = $target_email;
			$module->kernel->mailer->FromName  = "The PuXuan Hotel and Spa - ".$format;
			$module->kernel->mailer->AddAddress($target_email);
			$module->kernel->mailer->AddReplyTo($email, $format);

			$success = $module->kernel->mailer->Send();
			$error = $module->kernel->mailer->ErrorInfo;
			$module->kernel->mailer->ClearAllRecipients();

			if($success){
				$code = '1';
			}
			else{
				$code = '2';
			}

			$module->kernel->smarty->assignByRef( 'code',$code );
			return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/submited.html" );
		}

		if($ajax)
		{
			echo $abc = json_encode(array(
				'result' => 'success',
				'errors' => $errors
			));
			exit;
		}
	}
	else
	{
		$module->kernel->smarty->assignByRef( 'request',$module->kernel->request );
		return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/index.html" );
	}
}

?>
