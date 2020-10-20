<?php

function verificaTicket($id_relacinamento, $horadafalha){
    $base_url = 'https://rnp.topdesk.net/tas/api/incidents?' .
    'fields=id,number,optionalFields1.date1&' .
    'object_name=123&' .
    'processing_status=2817418e-5afc-4a8e-b2e4-7e4ff104e095&processing_status=a3e2ad64-16e2-4fe3-9c66-9e50ad9c4d69';
	$ch = curl_init($base_url);
	if ($ch == false) {
		$this->setWsError("cannot init curl object");
		return 1;
	}
	
	$argument = array(
			
            //'TicketNumber' => '40303767'
            //'StateType' => ['open', 'new', 'pending reminder', 'pending auto'],
            'processingStatus' => array('id' => '2817418e-5afc-4a8e-b2e4-7e4ff104e095'),
            'processingStatus' => array('id' => 'a3e2ad64-16e2-4fe3-9c66-9e50ad9c4d69'),
            
            'object_name'=> '1232',
			//'DynamicField_nocproserviceid' => array(
			//	'Equals' => [$id_relacinamento]
            //),
            'optionalFields1' => array('date1' => '2015-11-15T14:00:00.000+0200')
			//'DynamicField_HoradaFalha' => array(
				//'SmallerThan' => $horadafalha + 3600
			//	'GreaterThan' => date('Y-m-d H:i:s', strtotime('-60 minute', strtotime($horadafalha)))

			//)
    );
	$argument_json = json_encode($argument);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	//curl_setopt($ch, CURLOPT_POSTFIELDS, $argument_json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Basic Y2FybG9zLnNvdXNhOjN6dmc1LWl1MnJyLW5qcW82LW15dGlnLXdibmFs',
		'Content-Type: application/json',
		'Accept: application/json',
		'Content-Length: ' . strlen($argument_json),
		'Connection: close', 
		'Cache-Control: no-cache')
	);
	
	$result = curl_exec($ch);
	if ($result == false) {
		//$this->setWsError(curl_error($ch));
		curl_close($ch);
		return 1;
	}
                
	$decoded_result = json_decode($result, TRUE);
	if (is_null($decoded_result) || $decoded_result == false) {
		//$this->setWsError($result);
		//retorna nehum ticket
		return 1;
	}
	
	curl_close($ch);
	
	if (isset($decoded_result['Error'])) {
            //$this->setWsError($decoded_result['Error']['ErrorMessage']);
			//erro autenticação
            return 2;
        }
        
	//$this->_otrs_call_response = $decoded_result;
	return $decoded_result;
	
}

echo json_encode(verificaTicket('123','data'));