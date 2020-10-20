<?php

function verificaTicket($id_relacinamento, $horadafalha, $rule_data=array()){
    $base_url = 'https://';
    $base_url .= $rule_data['address'];
    $base_url .= $rule_data['path'] . '/api';
    $base_url .= '/incidents?fields=id,number,optionalFields1.date1&';
    $base_url .= 'object_name=' . $id_relacinamento . '&';
    //estados em atendimento e abertos
    $base_url .= 'processing_status=2817418e-5afc-4a8e-b2e4-7e4ff104e095&processing_status=a3e2ad64-16e2-4fe3-9c66-9e50ad9c4d69';
    
    $Authorization = base64_encode($rule_data['username'] . ":" . $rule_data['password']);
    
	$ch = curl_init($base_url);
	if ($ch == false) {
		$this->setWsError("cannot init curl object");
		return 1;
	}
    
    //este array não é usado os campos são passados todos na url
	$argument = array(
			
            //'processing_status' => '2817418e-5afc-4a8e-b2e4-7e4ff104e095',
            //'processing_status' => 'a3e2ad64-16e2-4fe3-9c66-9e50ad9c4d69',
            
            //'object_name' => '123'

			//'DynamicField_nocproserviceid' => array(
			//	'Equals' => [$id_relacinamento]
            //),
            //'optionalFields1' => array('date1' => '2015-11-15T14:00:00.000+0200')
			//'DynamicField_HoradaFalha' => array(
				//'SmallerThan' => $horadafalha + 3600
			//	'GreaterThan' => date('Y-m-d H:i:s', strtotime('-60 minute', strtotime($horadafalha)))

			//)
    );
	$argument_json = json_encode($argument);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $argument_json);
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

    // verifica se a falha em aberto é maior que hora
    $horadafalha_ticket_existente =  strtotime($decoded_result[0]['optionalFields1']['date1']);
    //$horadafalha_ticket_existente = date('Y-m-d H:i:s', $horadafalha_ticket_existente);
    $horadafalha_menos1Hora = strtotime('-60 minute', strtotime($horadafalha));
    if($horadafalha_ticket_existente > $horadafalha_menos1Hora || is_null($decoded_result[0]['optionalFields1']['date1']) || $decoded_result[0]['optionalFields1']['date1'] == 'null'){
        return 1;
    }   
    $decoded_result = $Authorization;
	//$this->_otrs_call_response = $decoded_result;
	return $decoded_result;
	
}

echo json_encode( verificaTicket('123','2020-11-19 01:16:00', 
array('address' => 'rnp.topdesk.net', 'path' => '/tas','username' => 'carlos.sousa', 'password' => '3zvg5-iu2rr-njqo6-mytig-wbnal')
) 
);