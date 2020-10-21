<?php

//old
function recuperaSessao(){
	$arquivo = fopen("/usr/share/centreon/nocPro/sessao.txt", "r");
	$linha = fgets($arquivo);
	$linha = rtrim($linha);
	$linha = ltrim($linha);
    return $linha;
}

function salvaSessao($sessao_id){
	$arquivo = fopen("/usr/share/centreon/nocPro/sessao.txt", "w");
	fwrite($arquivo, $sessao_id);
    fclose($arquivo);
}

function netosAck($ativo_id, $db_storage, $serviceOuHost){

	$ativo_id = "'%principal::" . $ativo_id . "%'";
	if ($serviceOuHost == "Host"){
		$query = "SELECT 
		
		 host_id as id

		FROM hosts

		where notes like" . $ativo_id;
	}else{
		$query = "select 
		
		service_id as id

		from services 
		
		where notes like" . $ativo_id;
		
		
	}
	
	$res = $db_storage->query($query);	
	//$tabNetos = array();
	while ($row = $res->fetch()) {
		
		//$tabNetos[] = $row['id'];
		//$tabNetos[] = netos($row['id'], $db_storage, $serviceOuHost);
				
		$ativo = $row['id'];
		$neto = netosAck($row['id'], $db_storage, $serviceOuHost);
		$netosString = $ativo . "," . $neto . $netosString;

	}
	return $netosString;
}

function netos($ativo_id, $db_storage, $serviceOuHost){

	$ativo_id = "'%principal::" . $ativo_id . "%'";
	if ($serviceOuHost == "Host"){
		$query = "SELECT 
		
		 host_id as id,
		CONCAT(
				hosts.name, ' - ',
				(select GROUP_CONCAT(services.description SEPARATOR ', ') as services_afetados from services where services.host_id = hosts.host_id)
		 ) as ativo

		FROM hosts

		where notes like" . $ativo_id;
	}else{
		$query = "select 
		service_id as id,
		CONCAT(
					(select name from hosts where hosts.host_id = services.host_id), 
					' - ',
					description
		) as ativo

		from services 
		
		where notes like" . $ativo_id;
		
		
	}
	
	$res = $db_storage->query($query);	
	//$tabNetos = array();
	while ($row = $res->fetch()) {
		
		//$tabNetos[] = $row['ativo'];
		//$tabNetos[] = netos($row['ativo'], $db_storage, $serviceOuHost);
				
		$ativo = $row['ativo'];
		$neto = netos($row['id'], $db_storage, $serviceOuHost);
		$netosString = $ativo . "<br/>" . $neto . $netosString;

	}
	return $netosString;
}

function servicesAfetados($host_id, $db_storage, $serviceOuHost){
	//$pai_array = explode("::", $ticket_arguments);
	//$pai_id = $pai_array[3];
	//if ($serviceOuHost == "Host"){
		//sec_to_time((now() - last_hard_state_change)) as last_hard_state_change_duration, 
	$query = "SELECT host_id as id, name, state, output, last_hard_state_change, name as host_name,(select GROUP_CONCAT(services.description SEPARATOR '<br/>') as services_afetados from services where services.host_id = hosts.host_id) services_afetados FROM hosts WHERE host_id = " . $host_id;
	//}else{
	//	$query = "SELECT service_id as id, description as name, state, output, last_hard_state_change, (select hosts.name from hosts where hosts.host_id = services.host_id) as host_name, description as services_afetados FROM services WHERE service_id = " . $pai_id;
	//}
	//$res = $this->db->query($query);
	$res = $db_storage->query($query);
	$tabServicesAfetados = array();
	while ($row = $res->fetch()) {
		$tabServicesAfetados['id'] = $row['id'];
		$tabServicesAfetados['name'] = $row['name'];
		//0 ="OK", 1 = "WARNING", 2 = "CRITICAL", 3 = "UNKNOWN"
		$tabServicesAfetados['state'] = $row['state'];
		$tabServicesAfetados['ic'] = $pai_array[1];
		$tabServicesAfetados['last_hard_state_change_duration'] = CentreonDuration::toString(time() - $row['last_hard_state_change']);
		$tabServicesAfetados['output'] = $row['output'];
		$tabServicesAfetados['last_hard_state_change'] = $row['last_hard_state_change'];
		$epoch = $tabServicesAfetados['last_hard_state_change'];
		$dt = new DateTime("@$epoch");
		$tabServicesAfetados['last_hard_state_change'] = $dt->format('Y-m-d H:i:s');
		$tabServicesAfetados['host_name'] = $row['host_name'];
		$tabServicesAfetados['services_afetados'] = $row['services_afetados'];
		
	}
	return $tabServicesAfetados;
}

function relacionamentoFalhasEquivalentes($valuerelacionamentos_ids, $db_storage, $serviceOuHost){
	//$pai_array = explode("::", $ticket_arguments);
	$pai_id = $valuerelacionamentos_ids;
	if ($serviceOuHost == "Host"){
		//sec_to_time((now() - last_hard_state_change)) as last_hard_state_change_duration, 
		$query = "SELECT host_id as id, name, state, output, last_hard_state_change, name as host_name,(select GROUP_CONCAT(services.description SEPARATOR '<br/>') as services_afetados from services where services.host_id = hosts.host_id) services_afetados, notes FROM hosts WHERE host_id = " . $pai_id;
	}else{
		$query = "SELECT service_id as id, description as name, state, output, last_hard_state_change, (select hosts.name from hosts where hosts.host_id = services.host_id) as host_name, description as services_afetados, notes FROM services WHERE service_id = " . $pai_id;
	}
	//$res = $this->db->query($query);
	$res = $db_storage->query($query);
	$tabRelacionamento = array();
	while ($row = $res->fetch()) {
		$tabRelacionamento['id'] = $row['id'];
		$tabRelacionamento['name'] = $row['name'];
		//0 ="OK", 1 = "WARNING", 2 = "CRITICAL", 3 = "UNKNOWN"
		$tabRelacionamento['state'] = $row['state'];
		$tabRelacionamento['ic'] = $row['notes'];
		$tabRelacionamento['last_hard_state_change_duration'] = CentreonDuration::toString(time() - $row['last_hard_state_change']);
		$tabRelacionamento['output'] = $row['output'];
		$tabRelacionamento['last_hard_state_change'] = $row['last_hard_state_change'];
		$epoch = $tabRelacionamento['last_hard_state_change'];
		$dt = new DateTime("@$epoch");
		$tabRelacionamento['last_hard_state_change'] = $dt->format('Y-m-d H:i:s');
		$tabRelacionamento['host_name'] = $row['host_name'];
		$tabRelacionamento['services_afetados'] = $row['services_afetados'];
		
	}
	return $tabRelacionamento;
}

function relacionamentoFalhas($ticket_arguments, $db_storage, $serviceOuHost){
	$pai_array = explode("::", $ticket_arguments);
	$pai_id = $pai_array[3];
	if ($serviceOuHost == "Host"){
		//sec_to_time((now() - last_hard_state_change)) as last_hard_state_change_duration, 
		$query = "SELECT host_id as id, name, state, output, last_hard_state_change, name as host_name,(select GROUP_CONCAT(services.description SEPARATOR '<br/>') as services_afetados from services where services.host_id = hosts.host_id) services_afetados FROM hosts WHERE host_id = " . $pai_id;
	}else{
		$query = "SELECT service_id as id, description as name, state, output, last_hard_state_change, (select hosts.name from hosts where hosts.host_id = services.host_id) as host_name, description as services_afetados FROM services WHERE service_id = " . $pai_id;
	}
	//$res = $this->db->query($query);
	$res = $db_storage->query($query);
	$tabRelacionamento = array();
	while ($row = $res->fetch()) {
		$tabRelacionamento['id'] = $row['id'];
		$tabRelacionamento['name'] = $row['name'];
		//0 ="OK", 1 = "WARNING", 2 = "CRITICAL", 3 = "UNKNOWN"
		$tabRelacionamento['state'] = $row['state'];
		$tabRelacionamento['ic'] = $pai_array[1];
		$tabRelacionamento['last_hard_state_change_duration'] = CentreonDuration::toString(time() - $row['last_hard_state_change']);
		$tabRelacionamento['output'] = $row['output'];
		$tabRelacionamento['last_hard_state_change'] = $row['last_hard_state_change'];
		$epoch = $tabRelacionamento['last_hard_state_change'];
		$dt = new DateTime("@$epoch");
		$tabRelacionamento['last_hard_state_change'] = $dt->format('Y-m-d H:i:s');
		$tabRelacionamento['host_name'] = $row['host_name'];
		$tabRelacionamento['services_afetados'] = $row['services_afetados'];
		
	}
	return $tabRelacionamento;
}

function infoIc($ic_local_recuperado_data_id_key){
	$base_url = 'https://dev-atendimento.rnp.br/otrs/nph-genericinterface.pl/Webservice/nocPro/ConfigItem/' . $ic_local_recuperado_data_id_key;
	$ch = curl_init($base_url);
	if ($ch == false) {
		$this->setWsError("cannot init curl object");
		return 1;
	}
	
	$argument = array(
			'SessionID' => recuperaSessao()
            //'TicketNumber' => '40303767'
			//'StateType' => ['open', 'new', 'pending reminder', 'pending auto'],
			//'Object' => 'ITSMConfigItem', 
			//Key' => $ic_recuperado_id, 
			//'State' => 'Valid', 
			//'UserID' => '1', 
			//'Object2' => 'ITSMConfigItem'
			
			//'Object' => array(
            //'Class' => $ic_recupera_class,
			//'Number' => $service_note_centreon
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
		//retorna nehum ic encontrado
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

function recuperaAssociacao($ic_recuperado_id){
	$base_url = 'https://dev-atendimento.rnp.br/otrs/nph-genericinterface.pl/Webservice/nocPro/Ticket/LinkList/';
	$ch = curl_init($base_url);
	if ($ch == false) {
		$this->setWsError("cannot init curl object");
		return 1;
	}
	
	$argument = array(
			'SessionID' => recuperaSessao(),
            //'TicketNumber' => '40303767'
			//'StateType' => ['open', 'new', 'pending reminder', 'pending auto'],
			'Object' => 'ITSMConfigItem', 
			'Key' => $ic_recuperado_id, 
			'State' => 'Valid', 
			'UserID' => '1', 
			'Object2' => 'ITSMConfigItem'
			
			//'Object' => array(
            //'Class' => $ic_recupera_class,
			//'Number' => $service_note_centreon
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
		//retorna nehuma associação
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

function ticketCliente($ic_recuperado_id, $regra_tipo){
	if($regra_tipo == "ultimamilha"){
		$ic_local_recuperado_data_id_key = recuperaAssociacao($ic_recuperado_id);
		$ic_local_recuperado_data_id_key = key($ic_local_recuperado_data_id_key['Result']['ITSMConfigItem']['AlternativeTo']['Source']);
	}else{
		$ic_local_recuperado_data_id_key = $ic_recuperado_id;
	}
	
	$infor_ic = infoIc($ic_local_recuperado_data_id_key);
	
	$ic_number = $infor_ic['ConfigItem'][0]['Number'];
	$ic_name = $infor_ic['ConfigItem'][0]['Name'];
	$ic_class = $infor_ic['ConfigItem'][0]['Class'];
	
	if($ic_class == "Location"){
		$ic_email = $infor_ic['ConfigItem'][0]['CIXMLData']['E-Mail'];
		$ic_uf = $infor_ic['ConfigItem'][0]['CIXMLData']['UF'];
	}else{
		$ic_email = "";
		$ic_uf = "";
	}
	
	if($ic_class == "Backbone"){
		$ic_designacao = $infor_ic['ConfigItem'][0]['CIXMLData']['Designation'];
	}else{
		$ic_designacao = null;
	}

	return array($ic_number, $ic_name, $ic_email, $ic_uf, $ic_designacao);
	
}

function consultaIc($service_note_centreon, $regra_tipo, $serviceOuHost){
	$base_url = 'https://dev-atendimento.rnp.br/otrs/nph-genericinterface.pl/Webservice/nocPro/ConfigItem/Search/';
	$ch = curl_init($base_url);
	if ($ch == false) {
		$this->setWsError("cannot init curl object");
		return 1;
	}
	if($regra_tipo == "backbone" && $serviceOuHost == "Service"){
        $ic_recupera_class = "Backbone";
	}
	elseif($regra_tipo == "backbone" && $serviceOuHost == "Host"){
		$ic_recupera_class = "Location";
	}
	elseif($regra_tipo == "infrapop"){
		$ic_recupera_class = "Location";
	}
    else{
        $ic_recupera_class = "Conectividade";
	}
	$argument = array(
			'SessionID' => recuperaSessao(),
            //'TicketNumber' => '40303767'
			//'StateType' => ['open', 'new', 'pending reminder', 'pending auto'],
			'ConfigItem' => array(
            'Class' => $ic_recupera_class,
			'Number' => $service_note_centreon
        )
    );
	$argument_json = json_encode($argument);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $argument_json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
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
		//sem retorno
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

//new
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
        'Authorization: Basic ' . $Authorization,
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
    
	return $decoded_result;
	
}

function createTicketExemplo($rule_data=array()){
    $argument = array(
        //'Title'           => $ticket_arguments['Subject'],
        'action'            => $titulo,
        'request'           => $ticket_arguments['Body'],
        'briefDescription'  => $titulo,
        //'Queue'             => $ticket_arguments['Queue'],
        'operatorGroup'     =>  array('name' => $ticket_arguments['Queue']),
        //'State'             => $ticket_arguments['State'],
        'processingStatus'  =>  array('name' => $ticket_arguments['State']),
        //'Priority'          => $ticket_arguments['Priority'],
        'priority'          =>  array('name' =>  $ticket_arguments['Priority']),
        //'TypeID'          => 123,
        //'Type'              => $ticket_arguments['Type'],					
        'callType'          => 'Incidente', //o campo type refere-se ao tipo de chamado, incidente, requisição, etc. No contexto do nocpro ele será usada para outro fim e todos os chamado serão do tipo Incidente
        //'OwnerID'         => 123,
        //'Owner'             => $ticket_arguments['Owner'],
        //'operador'            => array('name':  $ticket_arguments['Owner']),
        //'ResponsibleID'   => 123,
        //'Responsible'       => $ticket_arguments['Responsible'],
        //'CustomerUser'      => $ticket_arguments['CustomerUser'],
        //'CustomerUser'      => $email_cliente,
        'caller'            =>  array('dynamicName' =>  $email_cliente),
        //'ServiceID'         => $ServiceID
        'category'          =>  array('id' => $ServiceID)
        
    );

    $argument_json = json_encode($argument);

    $base_url = 'https://';
    $base_url .= $rule_data['address'];
    $base_url .= $rule_data['path'] . '/api';
    $base_url .= '/incidents';
        $ch = curl_init($base_url);
        if ($ch == false) {
            $this->setWsError("cannot init curl object");
            return 1;
        }
        
        $Authorization = base64_encode($rule_data['username'] . ":" . $rule_data['password']);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $argument_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . $Authorization,
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($argument_json))
        );


}

echo json_encode( createTicketExemplo(array('address' => 'rnp.topdesk.net', 'path' => '/tas','username' => 'carlos.sousa', 'password' => '3zvg5-iu2rr-njqo6-mytig-wbnal')
) 
);

