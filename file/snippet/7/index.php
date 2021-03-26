<?php

/**
 * The request for proposal snippet.
 *
 *
 *
 * @since   2013-08-22 09:00:00
 * @param   module      The module
 * @param   snippet     The snippet
 * @param   parameters  The parameters
 * @return  HTML content
 */
function request_for_proposal_snippet( &$module, &$snippet, $parameters )
{

		$title;
		$body = "";
		$error=0;
		
		$data = array();
		$form_data = array();
		
		$form_data['event_type'] = array("wedding","meeting","outside_catering","celebration_party");
		$event_type_str = array("Wedding","Meeting","Outside Catering","Celebration / Party");
		
		
		$form_data['cuisine'] = array("chinese","western","buffet","cocktail");
		$cuisine_str = array("Chinese","Western","Buffet","Cocktail");
		
		$form_data['meal_period'] = array("lunch","dinner","other");
		$meal_period_str = array("Lunch","Dinner","Other");
		
		
		$form_data['salutation'] = array("mr","mrs","ms");
		$salutation_str = array("Mr.","Mrs.","Ms.");
		
		$locale = $module->kernel->request['locale'];
			
		//require_once( "/locale/".$locale.".php"  );
	
		require( dirname(__FILE__) . "/locale/{$locale}.php" );
        $module->kernel->smarty->assignByRef( 'proposal_dict', $DICT );
		
		if(count($_POST)>1){
		
		$data['event_type'] = trim($_POST['event_type']);
		
		if($data['event_type'] =="wedding")
		{
			$data['expected_day_1'] =  trim($_POST['expected_day_1']);
			$data['expected_day_2'] =  trim($_POST['expected_day_2']);
			$data['expected_day_3'] =  trim($_POST['expected_day_3']);
			
			
			
			$temparray = array();
			$temperror = 0;
			
			for($i=1;$i<4;$i++)
			{
				if($data['expected_day_'.$i] != '')
					$temparray[$i] = $data['expected_day_'.$i];
				else
					$temperror++;
			}
		
			
			foreach($temparray as $key)
			{
					
				$test_arr  = explode('-', $key);
				if(count($test_arr)==3){
					if (checkdate($test_arr[1], $test_arr[2], $test_arr[0])) {				
						;		
					}
					else
						$error++;
				}
				else
				$error++;
			}
			
			
			$temp = implode(" / ", $temparray);
			
			$body  = "<b>Expected wedding day : </b>".$temp."<br>";
			

			if($temperror == 3)
				$error++;

			
		}
		else
		{
			$data['expected_start'] =  trim($_POST['expected_start']);
			$data['expected_end'] =  trim($_POST['expected_end']);
			
			
			$body  = "<b>Expected event day : </b>";
			
			if($data['expected_start'] != ''){
				$test_arr  = explode('-', $data['expected_start'] );
				if(count($test_arr)==3){
					if (checkdate($test_arr[1], $test_arr[2], $test_arr[0])) {				
						;		
					}
					else
						$error++;
				}
				else
				$error++;
				
				$body .= $data['expected_start'];
			}
			else
				$error++;
				
			if($data['expected_end'] != ''){
				$test_arr  = explode('-', $data['expected_end']  );
				if(count($test_arr)==3){
					if (checkdate($test_arr[1], $test_arr[2], $test_arr[0])) {				
						;		
					}
					else
						$error++;
				}
				else
				$error++;
				
				$body .= " - ".$data['expected_end'];
			}
			else
				$error++;
				
			$body .= "<br>";
	
			$data['company_name'] =  trim($_POST['company_name']);
			$data['purchase_type'] =  trim($_POST['purchase_type']);
		}	
		
		$data['number_of_guest'] = trim($_POST['number_of_guest']);
		$data['cuisine_type']  =  trim($_POST['cuisine_type']);
		$data['meal_period']  =  trim($_POST['meal_period']);
		$data['salutation']  = trim($_POST['salutation']);
		$data['firstname']  = trim($_POST['firstname']);
		$data['lastname']  = trim($_POST['lastname']);
		$data['email']  =  trim($_POST['email']);
		$data['primaryphone']  = trim($_POST['primaryphone']);
		$data['secondaryphone']  =  trim($_POST['secondaryphone']);
		$data['preferredtime']  =  trim($_POST['preferredtime']);
		$data['command']  = trim($_POST['command']);
		
		
		if($data['number_of_guest'] != '' &&  isInt($data['number_of_guest']))
		{
			$body .= "<b>Expected number of guests :</b>".$data['number_of_guest']."<br>";
		}
		else
			$error++;
		
		if($data['cuisine_type'] != '')
		{	
			$key= array_search($data['cuisine_type'], $form_data['cuisine']);
			$body .= "<b>Cuisine type : </b>".$cuisine_str[$key]."<br>";
		}
		else
			$error++;
		
		if($data['meal_period'] != '')
		{	
			$key= array_search($data['meal_period'], $form_data['meal_period']); 
			$body .= "<b>Meal Period : </b>".$meal_period_str[$key]."<br>";
		}
		else
			$error++;
			
		if($data['company_name'] != '')
		{
			$body .= "<b>Compmany Name : </b>".$data['company_name']."<br>";
		}
		else
			;
		
		if($data['salutation']  != '' && $data['firstname']!='' && $data['lastname'] !='')
		{	
			$key= array_search($data['salutation'], $form_data['salutation']); 
			$body .= "<b>Contact Name : </b>".$salutation_str[$key].$data['firstname']." ".$data['lastname']."<br>";
		}
		else
			$error++;
		
		if(	$data['email'] != '')
		{	
			$body .= "<b>Contact Email : </b>".$data['email']."<br>";
		}
		else
			$error++;
			
			
		 if( $data['primaryphone'] != '' ){
			if(isInt($data['primaryphone']) )
				$body .= "<b>Primary Contact Phone : </b>".$data['primaryphone']."<br>";
			else
				$error++;
		}
		
		 if( $data['secondaryphone'] != ''){
			if( isInt($data['secondaryphone']))
				$body .= "<b>SecondContact Phone : </b>".$data['secondaryphone']."<br>";
			else
				$error++;
		}
		
		if( $data['primaryphone'] == '' && $data['secondaryphone'] == '' )
			$error++;
		else
		;
		
		if( $data['preferredtime'] != '')
			$body .= "<b>Preferred Time to call : </b>".$data['preferredtime']."<br>";
		else
			$error++;
			
		if( $data['purchase_type']  != '')
			$body .= "<b>Previous Purchase : </b>".$data['purchase_type'] ."<br>";
		else
			;

		if( $data['command'] != '')
			$body .= "<b>Customer requirement  : </b>".$data['command']."<br>";
		else
		;
		
		$key= array_search($data['event_type'], $form_data['event_type']); 	
		$title = "New Request for Proposal - ".$event_type_str[$key];
		
		//echo $error;
		///echo $title;
		//echo $body;exit();
		if( $error == 0){

$success = FALSE;
$module->kernel->mailer->isHTML( TRUE );
$module->kernel->mailer->ContentType = 'text/html';
$module->kernel->mailer->Subject = $title;
$module->kernel->mailer->Body = $body;
$module->kernel->mailer->From = $parameters['email'];
$module->kernel->mailer->FromName  = "Avalade CMS";
$module->kernel->mailer->addAddress($parameters['email']);
try {
$success = $module->kernel->mailer->send();
} catch(Exception $e) {
}

$error = $module->kernel->mailer->ErrorInfo;
$module->kernel->mailer->ClearAllRecipients();


if($success){
	$code = '1';
	
	
}

else{
	$code = '2';
	
}
			
			$module->kernel->smarty->assignByRef( 'code',$code );		
			return $module->kernel->smarty->fetch( "file/snippet/{$snippet['snippet_id']}/submited.html" );
		    
	
		}
		else
		{	
			$code='3';
			$module->kernel->smarty->assignByRef( 'code',$code );		
			return $module->kernel->smarty->fetch( "file/snippet/{$snippet['snippet_id']}/submited.html" );
		}
	}
	
	else {
		 $default = array_ifnull( $_GET, 'type', 'wedding' );
		$default = trim($default);
		$key= array_search($default, $form_data['event_type']); 
	
			if($key != null){
				$form_data['default_type'] = $default;
				$form_data['default_key'] = $key+1;
			}
			else {
				$form_data['default_type'] = 'wedding';
				$key=1;
				$form_data['default_key'] = $key;
			}
				

		$module->kernel->smarty->assignByRef( 'form_data', $form_data);
		
		$space = 0;
		if($locale =='en')
			$space = 5;
		else
			$space = 20;
			
		$module->kernel->smarty->assignByRef( 'space', $space );
			//		$percentage =  array();
			//	
			//	if($locale == 'en')
			//	{
			//		$percentage['test'] = "20%";
			//		$percentage[1] = "20%";
			//		$percentage[2] = "30%";
			//		$percentage[3] = "30%";
			//	}
			//	else
			//	{
			//		$percentage[0] = "15%";
			//		$percentage[1] = "15%";
			//		$percentage[2] = "20%";
			//		$percentage[3] = "60%";
			//	}
			//	
			//	$module->kernel->smarty->assignByRef( 'percetage', $percentage );
		return $module->kernel->smarty->fetch( "file/snippet/{$snippet['id']}/index.html" );
		
	}


}

function isInt($str){
	
	 if(is_numeric($str)){
		if($str > 0)
			return true;
		else
		return false;
	 }
	 else
	 return false;
}
	
?>
