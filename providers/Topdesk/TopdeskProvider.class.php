<?php
/*
 * Copyright 2016 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0  
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
include '/usr/share/centreon/www/modules/centreon-open-tickets/providers/Topdesk/nocPro/topdesk_class.php';
include '/usr/share/centreon/www/modules/centreon-open-tickets/providers/Topdesk/nocPro/_access.php';

class TopdeskProvider extends AbstractProvider {
    protected $_otrs_connected = 0;
    protected $_otrs_session = null;
    protected $_attach_files = 1;
    protected $_close_advanced = 1;
    
    const OTRS_QUEUE_TYPE = 10;
    const OTRS_PRIORITY_TYPE = 11;
    const OTRS_STATE_TYPE = 12;
    const OTRS_TYPE_TYPE = 13;
    const OTRS_CUSTOMERUSER_TYPE = 14;
    const OTRS_OWNER_TYPE = 15;
    const OTRS_RESPONSIBLE_TYPE = 16;
    
    const ARG_QUEUE = 1;
    const ARG_PRIORITY = 2;
    const ARG_STATE = 3;
    const ARG_TYPE = 4;
    const ARG_CUSTOMERUSER = 5;
    const ARG_SUBJECT = 6;
    const ARG_BODY = 7;
    const ARG_FROM = 8;
    const ARG_CONTENTTYPE = 9;
    const ARG_OWNER = 17;
    const ARG_RESPONSIBLE = 18;
    
    protected $_internal_arg_name = array(
        self::ARG_QUEUE => 'Queue',
        self::ARG_PRIORITY => 'Priority',
        self::ARG_STATE => 'State',
        self::ARG_TYPE => 'Type',
        self::ARG_CUSTOMERUSER => 'CustomerUser',
        self::ARG_SUBJECT => 'Subject',
        self::ARG_BODY => 'Body',
        self::ARG_FROM => 'From',
        self::ARG_CONTENTTYPE => 'ContentType',
        self::ARG_OWNER => 'Owner',
        self::ARG_RESPONSIBLE => 'Responsible',
    );

    function __destruct() {
    }
    
    /**
     * Set default extra value 
     *
     * @return void
     */
    protected function _setDefaultValueExtra() {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['path'] = '/tas';
        $this->default_data['rest_link'] = 'api';
        $this->default_data['webservice_name'] = 'incidents';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;
        
        $this->default_data['clones']['mappingTicket'] = array(
            array('Arg' => self::ARG_SUBJECT, 'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers/Abstract/templates/display_title.ihtml"}'),
            array('Arg' => self::ARG_BODY, 'Value' => '{$body}'),
            array('Arg' => self::ARG_FROM, 'Value' => '{$user.email}'),
            array('Arg' => self::ARG_QUEUE, 'Value' => '{$select.otrs_queue.value}'),
            array('Arg' => self::ARG_PRIORITY, 'Value' => '{$select.otrs_priority.value}'),
            array('Arg' => self::ARG_STATE, 'Value' => '{$select.otrs_state.value}'),
            array('Arg' => self::ARG_TYPE, 'Value' => '{$select.otrs_type.value}'),
            array('Arg' => self::ARG_CUSTOMERUSER, 'Value' => '{$select.otrs_customeruser.value}'),
            array('Arg' => self::ARG_CONTENTTYPE, 'Value' => 'text/html; charset=utf8'),
        );
    }
    
    protected function _setDefaultValueMain($body_html = 0) {
        parent::_setDefaultValueMain(1);
        
        $this->default_data['url'] = 'https://{$address}/tas/secure/contained/incident?action=show&unid={$ticket_id}';        
        $this->default_data['clones']['groupList'] = array(
            array('Id' => 'otrs_queue', 'Label' => _('Otrs queue'), 'Type' => self::OTRS_QUEUE_TYPE, 'Filter' => '', 'Mandatory' => '1'),
            array('Id' => 'otrs_priority', 'Label' => _('Otrs priority'), 'Type' => self::OTRS_PRIORITY_TYPE, 'Filter' => '', 'Mandatory' => '1'),
            array('Id' => 'otrs_state', 'Label' => _('Otrs state'), 'Type' => self::OTRS_STATE_TYPE, 'Filter' => '', 'Mandatory' => '1'),
            array('Id' => 'otrs_type', 'Label' => _('Otrs type'), 'Type' => self::OTRS_TYPE_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'otrs_customeruser', 'Label' => _('Otrs customer user'), 'Type' => self::OTRS_CUSTOMERUSER_TYPE, 'Filter' => '', 'Mandatory' => '1'),
        );
    }
    
    /**
     * Check form
     *
     * @return a string
     */
    protected function _checkConfigForm() {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';
        
        $this->_checkFormValue('address', "Please set 'Address' value");
        $this->_checkFormValue('rest_link', "Please set 'Rest Link' value");
        $this->_checkFormValue('webservice_name', "Please set 'Webservice Name' value");
        $this->_checkFormValue('timeout', "Please set 'Timeout' value");
        $this->_checkFormValue('username', "Please set 'Username' value");
        $this->_checkFormValue('password', "Please set 'Password' value");
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->_checkFormInteger('timeout', "'Timeout' must be a number");
        $this->_checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");
        
        $this->_checkLists();
        
        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
    }
    
    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function _getConfigContainer1Extra() {
        $tpl = $this->initSmartyTemplate('providers/Topdesk/templates');
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("topdesk" => _("TOPdesk")));
        
        // Form
        $address_html = '<input size="50" name="address" type="text" value="' . $this->_getFormValue('address') . '" />';
        $path_html = '<input size="50" name="path" type="text" value="' . $this->_getFormValue('path') . '" />';
        $rest_link_html = '<input size="50" name="rest_link" type="text" value="' . $this->_getFormValue('rest_link') . '" />';
        $webservice_name_html = '<input size="50" name="webservice_name" type="text" value="' . $this->_getFormValue('webservice_name') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' . $this->_getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' . $this->_getFormValue('password') . '" autocomplete="off" />';
        $https_html = '<input type="checkbox" name="https" value="yes" ' . ($this->_getFormValue('https') == 'yes' ? 'checked' : '') . '/>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' . $this->_getFormValue('timeout') . '" />';

        $array_form = array(
            'address' => array('label' => _("Address") . $this->_required_field, 'html' => $address_html),
            'path' => array('label' => _("Path"), 'html' => $path_html),
            'rest_link' => array('label' => _("Rest link") . $this->_required_field, 'html' => $rest_link_html),
            'webservice_name' => array('label' => _("Webservice name") . $this->_required_field, 'html' => $webservice_name_html),
            'username' => array('label' => _("Username") . $this->_required_field, 'html' => $username_html),
            'password' => array('label' => _("Password") . $this->_required_field, 'html' => $password_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
            'mappingticketdynamicfield' => array('label' => _("Mapping ticket dynamic field")),
        );
        
        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" type="select-one">' .
        '<option value="' . self::ARG_QUEUE . '">' . _('Queue') . '</options>' .
        '<option value="' . self::ARG_PRIORITY . '">' . _('Priority') . '</options>' .
        '<option value="' . self::ARG_STATE . '">' . _('State') . '</options>' .
        '<option value="' . self::ARG_TYPE . '">' . _('Type') . '</options>' .
        '<option value="' . self::ARG_CUSTOMERUSER . '">' . _('Customer user') . '</options>' .
        '<option value="' . self::ARG_OWNER . '">' . _('Owner') . '</options>' .
        '<option value="' . self::ARG_RESPONSIBLE . '">' . _('Responsible') . '</options>' .
        '<option value="' . self::ARG_FROM . '">' . _('From') . '</options>' .
        '<option value="' . self::ARG_SUBJECT . '">' . _('Subject') . '</options>' .
        '<option value="' . self::ARG_BODY . '">' . _('Body') . '</options>' .
        '<option value="' . self::ARG_CONTENTTYPE . '">' . _('Content Type') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );
        
        // mapping Ticket DynamicField
        $mappingTicketDynamicFieldName_html = '<input id="mappingTicketDynamicFieldName_#index#" name="mappingTicketDynamicFieldName[#index#]" size="20"  type="text" />';
        $mappingTicketDynamicFieldValue_html = '<input id="mappingTicketDynamicFieldValue_#index#" name="mappingTicketDynamicFieldValue[#index#]" size="20"  type="text" />';
        $array_form['mappingTicketDynamicField'] = array(
            array('label' => _("Name"), 'html' => $mappingTicketDynamicFieldName_html),
            array('label' => _("Value"), 'html' => $mappingTicketDynamicFieldValue_html),
        );
        
        $tpl->assign('form', $array_form);
        
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        
        $this->_config['clones']['mappingTicket'] = $this->_getCloneValue('mappingTicket');
        $this->_config['clones']['mappingTicketDynamicField'] = $this->_getCloneValue('mappingTicketDynamicField');
    }
    
    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function _getConfigContainer2Extra() {
    }
    
    protected function saveConfigExtra() {
        $this->_save_config['simple']['address'] = $this->_submitted_config['address'];
        $this->_save_config['simple']['path'] = $this->_submitted_config['path'];
        $this->_save_config['simple']['rest_link'] = $this->_submitted_config['rest_link'];
        $this->_save_config['simple']['webservice_name'] = $this->_submitted_config['webservice_name'];
        $this->_save_config['simple']['username'] = $this->_submitted_config['username'];
        $this->_save_config['simple']['password'] = $this->_submitted_config['password'];
        $this->_save_config['simple']['https'] = (isset($this->_submitted_config['https']) && $this->_submitted_config['https'] == 'yes') ? 
            $this->_submitted_config['https'] : '';
        $this->_save_config['simple']['timeout'] = $this->_submitted_config['timeout'];
        
        $this->_save_config['clones']['mappingTicket'] = $this->_getCloneSubmitted('mappingTicket', array('Arg', 'Value'));
        $this->_save_config['clones']['mappingTicketDynamicField'] = $this->_getCloneSubmitted('mappingTicketDynamicField', array('Name', 'Value'));
    }
    
    protected function getGroupListOptions() {        
        $str = '<option value="' . self::OTRS_QUEUE_TYPE . '">Otrs queue</options>' .
        '<option value="' . self::OTRS_PRIORITY_TYPE . '">Otrs priority</options>' .
        '<option value="' . self::OTRS_STATE_TYPE . '">Otrs state</options>' .
        '<option value="' . self::OTRS_CUSTOMERUSER_TYPE . '">Otrs customer user</options>' .
        '<option value="' . self::OTRS_TYPE_TYPE . '">Otrs type</options>' .
        '<option value="' . self::OTRS_OWNER_TYPE . '">Otrs owner</options>' .
        '<option value="' . self::OTRS_RESPONSIBLE_TYPE . '">Otrs responsible</options>';
        return $str;
    }
    
    protected function assignOtrsQueue($entry, &$groups_order, &$groups) {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listQueueOtrs();
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }
            
            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }
        
        $this->saveSession('otrs_queue', $this->_otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }
    
    protected function assignOtrsPriority($entry, &$groups_order, &$groups) {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listPriorityOtrs();
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }
            
            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }
        
        $this->saveSession('otrs_priority', $this->_otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }
    
    protected function assignOtrsState($entry, &$groups_order, &$groups) {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listStateOtrs();
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }
            
            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }
        
        $this->saveSession('otrs_state', $this->_otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }
    
    protected function assignOtrsType($entry, &$groups_order, &$groups) {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listTypeOtrs();
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }
            
            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }
        
        $this->saveSession('otrs_type', $this->_otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }
    
    protected function assignOtrsCustomerUser($entry, &$groups_order, &$groups) {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listCustomerUserOtrs();
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }
            
            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }
        
        $this->saveSession('otrs_customeruser', $this->_otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }
    
    protected function assignOtrsUser($entry, &$groups_order, &$groups, $label_session) {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listUserOtrs();
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_otrs_call_response['response'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }
            
            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }
        
        $this->saveSession($label_session, $this->_otrs_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }
        
    protected function assignOthers($entry, &$groups_order, &$groups) {
        if ($entry['Type'] == self::OTRS_QUEUE_TYPE) {
            $this->assignOtrsQueue($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_PRIORITY_TYPE) {
            $this->assignOtrsPriority($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_STATE_TYPE) {
            $this->assignOtrsState($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_TYPE_TYPE) {
            $this->assignOtrsType($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_CUSTOMERUSER_TYPE) {
            $this->assignOtrsCustomerUser($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::OTRS_OWNER_TYPE) {
            $this->assignOtrsUser($entry, $groups_order, $groups, 'otrs_owner');
        } elseif ($entry['Type'] == self::OTRS_RESPONSIBLE_TYPE) {
            $this->assignOtrsUser($entry, $groups_order, $groups, 'otrs_responsible');
        }
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        $this->validateFormatPopupLists($result);
        
        return $result;
    }
    
    protected function assignSubmittedValuesSelectMore($select_input_id, $selected_id) {
        $session_name = null;
        foreach ($this->rule_data['clones']['groupList'] as $value) {
            if ($value['Id'] == $select_input_id) {                    
                if ($value['Type'] == self::OTRS_QUEUE_TYPE) {
                    $session_name = 'otrs_queue';
                } elseif ($value['Type'] == self::OTRS_PRIORITY_TYPE) {
                    $session_name = 'otrs_priority';
                } elseif ($value['Type'] == self::OTRS_STATE_TYPE) {
                    $session_name = 'otrs_state';
                } elseif ($value['Type'] == self::OTRS_TYPE_TYPE) {
                    $session_name = 'otrs_type';
                } elseif ($value['Type'] == self::OTRS_CUSTOMERUSER_TYPE) {
                    $session_name = 'otrs_customeruser';
                } elseif ($value['Type'] == self::OTRS_OWNER_TYPE) {
                    $session_name = 'otrs_owner';
                } elseif ($value['Type'] == self::OTRS_RESPONSIBLE_TYPE) {
                    $session_name = 'otrs_responsible';
                }
            }
        }
        
        if (is_null($session_name) && $selected_id == -1) {
            return array();
        }
        if ($selected_id == -1) {
            return array('id' => null, 'value' => null);
        }
        
        $result = $this->getSession($session_name);
        
        if (is_null($result)) {
            return array();
        }

        foreach ($result as $value)  {
            if ($value['id'] == $selected_id) {                
                return $value;
            }
        }
        
        return array();
    }
    
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems) {
        
		if (isset($service_problems) && $service_problems != null) {
			$item_problems = $service_problems;
			$serviceOuHost = "Service";
		}
		if (isset($host_problems) && $host_problems != null) {
			$item_problems = $host_problems;
			$serviceOuHost = "Host";
		}
			
		
		foreach ($item_problems as $fieldProblem => $valueProblem) {
            
            $result = array('ticket_id' => null, 'ticket_error_message' => null,
            'ticket_is_ok' => 0, 'ticket_time' => time());

			//state 0 = ok
			if($valueProblem['state'] != 0){

				//recupera services afetados pelo host
				if($serviceOuHost == "Host"){
					$tabServicesAfetados = servicesAfetados($valueProblem['host_id'], $db_storage, $serviceOuHost);
					//o campo host_alias será usado para os service afetados.
					$valueProblem['alias'] = $tabServicesAfetados['services_afetados'];
					
					//verifica se ativo tem filhos ou netos
					//$tabFilhosNetos = filhosNetos($valueProblem['host_id'], $db_storage, $serviceOuHost);
					//$valueProblem['alias'] = $valueProblem['alias'] . "<br/><br/><b>dependentes:</b><br/>" . $tabFilhosNetos['dependentes'];
					
					$stringNetos = netos($valueProblem['host_id'], $db_storage, $serviceOuHost);
					$valueProblem['alias'] = $valueProblem['alias'] . "<br/><br/><b>dependentes:</b><br/>" . $stringNetos;
					
				}else{
						
					//$tabFilhosNetos = filhosNetos($valueProblem['service_id'], $db_storage, $serviceOuHost);
					//$valueProblem['display_name'] = $tabFilhosNetos['dependentes'];
					
					$stringNetos = netos($valueProblem['service_id'], $db_storage, $serviceOuHost);
					$valueProblem['display_name'] = $stringNetos;
					
					$stringNetosAck = netosAck($valueProblem['service_id'], $db_storage, $serviceOuHost);
						
				}
				
				if(strpos($valueProblem['notes'], "equivalente") !== false){
					
					$relacionamentos_array = explode("::", $valueProblem['notes']);
					$relacionamentos_ids = explode(",", $relacionamentos_array[3]);
					if(is_null($relacionamentos_ids)){
						$relacionamentos_ids = array($relacionamentos_array[3]);
					}
									
					foreach ($relacionamentos_ids as $valuerelacionamentos_ids) {
										
						//$valueProblem['name'] = $valueProblem['name'];
						$tabRelacionamento = relacionamentoFalhasEquivalentes($valuerelacionamentos_ids, $db_storage, $serviceOuHost);
						if($tabRelacionamento['state'] != 0){
							if($serviceOuHost == "Host"){
								$valueProblem['name'] = $valueProblem['name'] . "<br/>" . $tabRelacionamento['name'];
								$valueProblem['alias'] = $valueProblem['alias'] . "<br/><br/>" . $tabRelacionamento['services_afetados'];
							}else{
								$valueProblem['description'] = $valueProblem['description'] . "<br/>" . $tabRelacionamento['name'];
								$valueProblem['host_name'] = $valueProblem['host_name'] . "<br/>" . $tabRelacionamento['host_name'];
							}
							$valueProblem['state'] = $valueProblem['state'] . "<br/>" . $tabRelacionamento['state'];
							$valueProblem['last_hard_state_change_duration'] = $valueProblem['last_hard_state_change_duration'] . "<br/>" . $tabRelacionamento['last_hard_state_change_duration'];
							$valueProblem['output'] = $valueProblem['output'] . "<br/>" . $tabRelacionamento['output'];
							
							
						}
					}
				}
				//$service_id = $valueServiceProblem;
			

				
				$tpl = $this->initSmartyTemplate();

				$tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
				$tpl->assign('user', $contact);
				if($serviceOuHost == "Host"){
					//$tpl->assign('host_selected', $host_problems);
					$tpl->assign('host_selected', array($fieldProblem => $valueProblem));
					$host_problems_hist = array($fieldProblem => $valueProblem);
				}
				if($serviceOuHost == "Service"){
					//$tpl->assign('service_selected', $service_problems);
					$tpl->assign('service_selected', array($fieldProblem => $valueProblem));
					$service_problems_hist = array($fieldProblem => $valueProblem);
				}
				
				$this->assignSubmittedValues($tpl);
				
				$ticket_arguments = array();
				if (isset($this->rule_data['clones']['mappingTicket'])) {
					foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
						$tpl->assign('string', $value['Value']);
						$result_str = $tpl->fetch('eval.ihtml');
						
						if ($result_str == '') {
							$result_str = null;
						}
						
						$ticket_arguments[$this->_internal_arg_name[$value['Arg']]] = $result_str;
					}
				}
				$ticket_dynamic_fields = array();
				if (isset($this->rule_data['clones']['mappingTicketDynamicField'])) {
					foreach ($this->rule_data['clones']['mappingTicketDynamicField'] as $value) {
						if ($value['Name'] == '' ||  $value['Value'] == '') {
							continue;
						}
						$array_tmp = array();
						$tpl->assign('string', $value['Name']);
						$array_tmp = array('Name' => $tpl->fetch('eval.ihtml'));
						
						$tpl->assign('string', $value['Value']);
						$array_tmp['Value'] = $tpl->fetch('eval.ihtml');
						
						$ticket_dynamic_fields[] = $array_tmp;
					}
				}
				//$ic_campo_notes = $ticket_arguments['CustomerUser'];
				//$regra_tipo = $ticket_arguments['Type']; //o campo type refere-se ao tipo de chamado, incidente, requisição, etc. No contexto do nocpro ele será usada para outro fim e todos os chamado serão do tipo Incidente
				if(strpos($ticket_arguments['CustomerUser'], "principal") !== false){
					
					$tabRelacionamento = relacionamentoFalhas($ticket_arguments['CustomerUser'], $db_storage, $serviceOuHost);
					$ticket_arguments['CustomerUser'] = $tabRelacionamento['ic'];
					
					if($tabRelacionamento['state'] != 0 ){
						$ticketsPopUp[] = "O principal está indisponível, consulte a topologia.<br/>Principal: " . $tabRelacionamento['name'];
						$this->_otrs_call_response['TicketNumber'] = "O principal está indisponível, consulte a topologia.<br/>Principal: " . $tabRelacionamento['name'];
						//$result['ticket_error_message'] = "O principal está indisponível, consulte a topologia.<br/>Principal: " . $tabRelacionamento['name'];
					}else{
						$code = $this->createTicketTopdesk($ticket_arguments, $ticket_dynamic_fields, $serviceOuHost, null);
						if ($code == -1) {
							$result['ticket_error_message'] = $this->ws_error;
							return $result;
						}
						$ticketsPopUp[] =  $this->_otrs_call_response['TicketNumber'] . "_" . $stringNetosAck;
						
					}
				}elseif(strpos($ticket_arguments['CustomerUser'], "equivalente") !== false){
									
					$relacionamentos_array = explode("::", $valueProblem['notes']);
					$relacionamentos_ids = explode(",", $relacionamentos_array[3]);
					if(is_null($relacionamentos_ids)){
						$relacionamentos_ids = array($relacionamentos_array[3]);
					}
								
					foreach ($relacionamentos_ids as $valuerelacionamentos_ids) {
						$tabRelacionamento = relacionamentoFalhasEquivalentes($valuerelacionamentos_ids, $db_storage, $serviceOuHost);
						$tabRelacionamentoFull[] = array($tabRelacionamento);
					}
					
					$ticket_arguments['CustomerUser'] = $relacionamentos_array[1];
					$code = $this->createTicketTopdesk($ticket_arguments, $ticket_dynamic_fields, $serviceOuHost, $tabRelacionamentoFull);
					if ($code == -1) {
						$result['ticket_error_message'] = $this->ws_error;
						return $result;
					}
					$ticketsPopUp[] =  $this->_otrs_call_response['TicketNumber'] . "_" . $stringNetosAck;
                //remover esse elseif
				}elseif(strpos($ticket_arguments['CustomerUser'], "acionamento") !== false){

					$relacionamentos_array = explode("::", $valueProblem['notes']);
					$relacionamentos_ids = explode(",", $relacionamentos_array[3]);
					if(is_null($relacionamentos_ids)){
						$relacionamentos_ids = array($relacionamentos_array[3]);
					}
								
					foreach ($relacionamentos_ids as $valuerelacionamentos_ids) {
						$tabRelacionamento = relacionamentoFalhasEquivalentes($valuerelacionamentos_ids, $db_storage, $serviceOuHost);
						$tabRelacionamentoFull[] = array($tabRelacionamento);

					}

                    //$ticketsPopUp[] = Json_encode($tabRelacionamentoFull);
                    //$this->_otrs_call_response['TicketNumber'] = Json_encode($tabRelacionamentoFull);

                    $ticket_arguments['CustomerUser'] = $relacionamentos_array[1];
                    $code = $this->createTicketTopdesk($ticket_arguments, $ticket_dynamic_fields, $serviceOuHost, $tabRelacionamentoFull);
                    if ($code == -1) {
                        $result['ticket_error_message'] = $this->ws_error;
                        return $result;
                    }
                    $ticketsPopUp[] =  $this->_otrs_call_response['TicketNumber'] . "_" . $stringNetosAck;


                }else{
				
					$code = $this->createTicketTopdesk($ticket_arguments, $ticket_dynamic_fields, $serviceOuHost, null);
					if ($code == -1) {
						$result['ticket_error_message'] = $this->ws_error;
						return $result;
					}
					$ticketsPopUp[] =  $this->_otrs_call_response['TicketNumber'] . "_" . $stringNetosAck;
					
				}
			
			}else{
				$ticketsPopUp[] = "O " . $serviceOuHost . " não está indisponível";
				$this->_otrs_call_response['TicketNumber'] = "O " . $serviceOuHost . " não está indisponível";
			}
				
			$this->saveHistory($db_storage, $result, array('contact' => $contact, 'host_problems' => $host_problems_hist, 'service_problems' => $service_problems_hist, 
			'ticketsPopUp' => $ticketsPopUp, 'ticket_value' => $this->_otrs_call_response['TicketNumber'], 'subject' => $ticket_arguments['Subject'], 
			'data_type' => self::DATA_TYPE_JSON, 'data' => json_encode(array('arguments' => $ticket_arguments, 'dynamic_fields' => $ticket_dynamic_fields))));
			

			
		}
		
        return $result;
    }

    /*
     *
     * REST API
     *
     */
    protected function setWsError($error) {
        $this->ws_error = $error;
    }
    
    
    protected function closeTicketOtrs($ticket_number) {
        if ($this->_otrs_connected == 0) {
            if ($this->loginOtrs() == -1) {
                return -1;
            }
        }
        
        $argument = array(
            'SessionID' => $this->_otrs_session,
            'TicketNumber' => $ticket_number,
            'Ticket' => array(
                'State' => 'closed successful',
            ),
        );

        if ($this->callRest('TicketUpdate', $argument) == 1) {
            return -1;
        }
        
        return 0;
    }
    
    protected function createTicketTopdesk($ticket_arguments, $ticket_dynamic_fields, $serviceOuHost, $tabRelacionamentoFull) {
        
		//verificar se tem ticket pare ele mesmo
		//$ticket_existente = verificaTicket($ticket_dynamic_fields[0]['Value'], $ticket_dynamic_fields[1]['Value']);
		$ticket_existenteTopdesk = verificaTicketTopdesk($ticket_arguments['CustomerUser'], $ticket_dynamic_fields[1]['Value'], 
            array(
                'address' => $this->rule_data['address'],
                'path' =>  $this->rule_data['path'],
                'username' =>  $this->rule_data['username'], 
                'password' =>  $this->rule_data['password']
                )
            );

		//if ($ticket_existente == 2){
			
			//if ($this->_otrs_connected == 0) {
			//	if ($this->loginOtrs() == -1) {
			//		return -1;
			//	}
			//}
		//	$this->loginOtrs();	
			//$this->_otrs_session = criaSessao();
		//	$ticket_existente = verificaTicket($ticket_dynamic_fields[0]['Value'], $ticket_dynamic_fields[1]['Value']);
				
		//}
		
		//verifica se existe ticket para relacionamentos
		if($ticket_existenteTopdesk == 1 && $tabRelacionamentoFull !== null){
			$ticket_existeAnterior = 1;
			foreach($tabRelacionamentoFull as $valuetabRelacionamento){
				
                //$ticket_existente = verificaTicket($valuetabRelacionamento[0]['id'], $ticket_dynamic_fields[1]['Value']);
                $relacionamentos_array = explode("::", $valuetabRelacionamento[0]['ic']);
				$ticket_existenteTopdesk = verificaTicketTopdesk($relacionamentos_array[1], $ticket_dynamic_fields[1]['Value'], 
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
                );
				if($ticket_existenteTopdesk !== 1){
					$ticket_existeAnterior = $ticket_existenteTopdesk;
					//$ticket_existe = $ticket_existeAnterior;
					
					// se a falha equivalente for após ela sobrepoe 
					if($valuetabRelacionamento[0]['last_hard_state_change'] > $ticket_dynamic_fields[1]['Value']){
						$ticket_dynamic_fields[1]['Value'] = $valuetabRelacionamento[0]['last_hard_state_change'];
					}
				}
				$ticket_existenteTopdesk = $ticket_existeAnterior;
				
                //VERIFICA acionamento personalizado
                if($valuetabRelacionamento[0]['state'] != 0 ){
                    $acionamento_personalizado = "<br/>O <b>" .
                    $valuetabRelacionamento[0]['name'] . "</b> está indisponível, realizar acionamento personalizado." . $acionamento_personalizado;
                }
				
			}
		}
			            
        
       

        if($ticket_existenteTopdesk == 1 || is_null($ticket_existenteTopdesk)){

            #loga no otrs para recuperar informações do IC.
		    // descontinuado $this->loginOtrs();

            $regra_tipo = $ticket_arguments['Type']; 
            //o campo type refere-se ao tipo de chamado, incidente, requisição, etc. 
            //No contexto do nocpro ele será usada para outro fim e todos os chamado serão do tipo Incidente. stigsc, stigti,ultimamilha, backbone

			
			//valida se e conectividade ou serviços / se tem ic ou não
			// CustomerUser campo notes numero do IC
			if(is_null($ticket_arguments['CustomerUser']) || $ticket_arguments['CustomerUser'] == "" || $ticket_arguments['CustomerUser'] == " "){
                //defini IC padrão do tipo Centreon
                $ticket_arguments['CustomerUser'] = "CENTREON_" . $ticket_dynamic_fields[0]['Value'];
                //verificar depois para usar ICs de hosts
                if($serviceOuHost == "Host"){
                    $ticket_arguments['CustomerUser'] = null;
                }
			}

            //RECUPERAR DADOS DO IC e do PAI
            $ic_recuperado_td = consultaIcTopdesk($ticket_arguments['CustomerUser'], $regra_tipo, $serviceOuHost,
            array(
                'address' => $this->rule_data['address'],
                'path' =>  $this->rule_data['path'],
                'username' =>  $this->rule_data['username'], 
                'password' =>  $this->rule_data['password']
                )
            );
            //recupera dados dos parents
            $ic_parents_td = consultaICsAssociadosTopdesk($ic_recuperado_td[0]['unid'],
            array(
                'address' => $this->rule_data['address'],
                'path' =>  $this->rule_data['path'],
                'username' =>  $this->rule_data['username'], 
                'password' =>  $this->rule_data['password']
                )
            );
            if(is_null($ic_recuperado_td[0]['unid']) || $ic_recuperado_td == ""){
				
                $email_cliente = $ticket_arguments['From'];
                $ticket_arguments['CustomerUser'] = null;
               //$this->_otrs_call_response['TicketNumber'] = json_encode($ic_recuperado_td);
               //return 0;
            
            }
            //ACIONAMENTOS
            //recupera id e nome do filho
            foreach($ic_parents_td as $value_ic_parents_td){

                if ($value_ic_parents_td["linkType"] == "child"){
                    $child_name = $value_ic_parents_td["name"];
                    $child_assetId = $value_ic_parents_td["assetId"];
                }
        
            }
            //tratar espaços para url
            $child_name = str_replace(" ", "%20", $child_name);
            $ic_child_td = consultaIcTopdesk($child_name, $regra_tipo, $serviceOuHost,
            array(
                'address' => $this->rule_data['address'],
                'path' =>  $this->rule_data['path'],
                'username' =>  $this->rule_data['username'], 
                'password' =>  $this->rule_data['password']
                )
            );
            //incliur acionamentos no action do chamado
            $action_acionamentos = "<b>Acionamento:</b><br/>";
            $action_acionamentos .= 'Informações: ' . $ic_child_td[0]['cnt-informacoes'] . "<br/>";
            $action_acionamentos .= 'Horário de acionamento: ' . $ic_child_td[0]['cnt-horario-de-acionamento'] . "<br/>";
            $action_acionamentos .= 'Plantonistas: ' . $ic_child_td[0]['cnt-plantonistas'] . "<br/><br/>";
            $action_acionamentos .= "<b>Contatos:</b><br/>";
            $action_acionamentos .= $ic_child_td[0]['nome-completo'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-email'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-telefone'] . "<br/><br/>";
            //$action_acionamentos .= "<b>contato II:</b><br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-2-nome-completo'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-2-email'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-2-telefone'] . "<br/><br/>";
            //$action_acionamentos .= "<b>contato III:</b><br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-3-nome-completo'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-3-email'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-3-telefone'] . "<br/><br/>";
            //$action_acionamentos .= "<b>contato IV:</b><br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-4-nome-completo'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-4-email'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-4-telefone'] . "<br/><br/>";
            //$action_acionamentos .= "<b>contato V:</b><br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-5-nome-completo'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-5-email'] . "<br/>";
            $action_acionamentos .= $ic_child_td[0]['conectividade-5-telefone'] . "<br/><br/>";
            //FIM ACIONAMENTOS
            

            //checa tipo de ticket um/backbone/sti
			if($regra_tipo == "ultimamilha"){
                //acionamentos desativado
                //$action_acionamentos = null;


                //$ticketCliente_td_email = ticketCliente_td($ic_parents_td,
                //array(
                //    'address' => $this->rule_data['address'],
                //    'path' =>  $this->rule_data['path'],
                //    'username' =>  $this->rule_data['username'], 
                //    'password' =>  $this->rule_data['password']
                //    )
                //);

                //RECUPERADO INFOMAÇÔES do POP e da Operadora para a nota de acionameto
                foreach($ic_parents_td as $value_ic_pai_td){

                    if ($value_ic_pai_td["linkType"] == "parent"){
                        $pai_name = $value_ic_pai_td["name"];
                        $pai_assetId = $value_ic_pai_td["assetId"];
                    }
            
                }
                $ic_pai_td = consultaIcTopdesk($pai_name, $regra_tipo, $serviceOuHost,
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
                );
                
                $action_acionamentos .= "<b>Localização(PoP):</b><br/>";
                $action_acionamentos .= $ic_pai_td[0]['telefone'] . "<br/>";
                $action_acionamentos .= $ic_pai_td[0]['telefone-2'] . "<br/>";
                $action_acionamentos .= $ic_pai_td[0]['email'] . "<br/>";
                //$action_acionamentos .= $ic_pai_td[0]['uf'] . "<br/>";
                $action_acionamentos .= $ic_pai_td[0]['endereco'] . "<br/>";
                $action_acionamentos .= $ic_pai_td[0]['informacao'] . "<br/><br/>";

                //operadora
                $contato_id = $child_assetId;
                $ic_operadora_td = consultaICsAssociadosTopdesk($contato_id,
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
                );
                foreach($ic_operadora_td as $value_ic_operadora_td){

                    if ($value_ic_operadora_td["linkType"] == "child"){
                        $operadora_name = $value_ic_operadora_td["name"];
                        $operadora_assetId = $value_ic_operadora_td["assetId"];
                    }
            
                }
                $operadora_name = str_replace(" ", "%20", $operadora_name);
                $ic_operadora_td = consultaIcTopdesk($operadora_name, $regra_tipo, $serviceOuHost,
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
                );
                
                $action_acionamentos .= "<b>Operadora:</b><br/>";
                $action_acionamentos .= $ic_operadora_td[0]['op-nome'] . "<br/>";
                $action_acionamentos .= $ic_operadora_td[0]['op-telefone'] . "<br/>";
                $action_acionamentos .= $ic_operadora_td[0]['op-e-mail'] . "<br/>";
                //$action_acionamentos .= $ic_pai_td[0]['uf'] . "<br/>";
                $action_acionamentos .= 'Portal: ' . $ic_operadora_td[0]['op-portal'] . "<br/>";
                $action_acionamentos .= 'Observações: ' . $ic_operadora_td[0]['op-observacoes'] . "<br/><br/>";

                //FIM RECUPERADO INFOMAÇÔES do POP e operadora
                

                //$email_cliente = $ticketCliente_td_email;
                $email_cliente = $ic_pai_td[0]['email'];  

                // quando o IC não está cadastrado no OTRS abrir o chamado com o cliente do operador.
                if(is_null($email_cliente) || $email_cliente == ""){
                    $email_cliente = $ticket_arguments['From'];
                }

                $titulo = $ticket_arguments['Subject'];
                //Serviço de Conectividade = 989624e9-4b7f-4bef-ab65-aa6135d52299
                //Indisponibilidade = a0a77087-9029-4dcd-a8ab-13a40c8df466
                // sla = Última milha = d682a47f-3160-4001-b9ab-56814bd3e864
                $ServiceID = '989624e9-4b7f-4bef-ab65-aa6135d52299';
                $subcategory = 'a0a77087-9029-4dcd-a8ab-13a40c8df466';
                $sla = 'd682a47f-3160-4001-b9ab-56814bd3e864';
						
				// descontinuado $ticket_dynamic_fields[2]['Value'] = $ic_uf;
				//$email_cliente = $email_cliente;
				
			}elseif($regra_tipo == "ultimamilhateste"){
                //acionamentos desativado
                //$action_acionamentos = null;

                $action_acionamentos = $acionamento_personalizado . "<br/>" . $action_acionamentos;

                //RECUPERADO INFOMAÇÔES do POP e da Operadora para a nota de acionameto
                foreach($ic_parents_td as $value_ic_pai_td){

                    if ($value_ic_pai_td["linkType"] == "parent"){
                        $pai_name = $value_ic_pai_td["name"];
                        $pai_assetId = $value_ic_pai_td["assetId"];
                    }
            
                }
                $ic_pai_td = consultaIcTopdesk($pai_name, $regra_tipo, $serviceOuHost,
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
                );
                
                $action_acionamentos .= "<b>Localização(PoP):</b><br/>";
                $action_acionamentos .= $ic_pai_td[0]['telefone'] . "<br/>";
                $action_acionamentos .= $ic_pai_td[0]['telefone-2'] . "<br/>";
                $action_acionamentos .= $ic_pai_td[0]['email'] . "<br/>";
                //$action_acionamentos .= $ic_pai_td[0]['uf'] . "<br/>";
                $action_acionamentos .= $ic_pai_td[0]['endereco'] . "<br/>";
                $action_acionamentos .= $ic_pai_td[0]['informacao'] . "<br/><br/>";

                //operadora
                $contato_id = $child_assetId;
                $ic_operadora_td = consultaICsAssociadosTopdesk($contato_id,
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
                );
                foreach($ic_operadora_td as $value_ic_operadora_td){

                    if ($value_ic_operadora_td["linkType"] == "child"){
                        $operadora_name = $value_ic_operadora_td["name"];
                        $operadora_assetId = $value_ic_operadora_td["assetId"];
                    }
            
                }
                $operadora_name = str_replace(" ", "%20", $operadora_name);
                $ic_operadora_td = consultaIcTopdesk($operadora_name, $regra_tipo, $serviceOuHost,
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
                );
                
                $action_acionamentos .= "<b>Operadora:</b><br/>";
                $action_acionamentos .= $ic_operadora_td[0]['op-nome'] . "<br/>";
                $action_acionamentos .= $ic_operadora_td[0]['op-telefone'] . "<br/>";
                $action_acionamentos .= $ic_operadora_td[0]['op-e-mail'] . "<br/>";
                //$action_acionamentos .= $ic_pai_td[0]['uf'] . "<br/>";
                $action_acionamentos .= 'Portal: ' . $ic_operadora_td[0]['op-portal'] . "<br/>";
                $action_acionamentos .= 'Observações: ' . $ic_operadora_td[0]['op-observacoes'] . "<br/><br/>";

                //FIM RECUPERADO INFOMAÇÔES do POP e operadora



                // quando o IC não está cadastrado no OTRS abrir o chamado com o cliente do operador.
                if(is_null($email_cliente) || $email_cliente == ""){
                    $email_cliente = $ticket_arguments['From'];
                }

                $titulo = $ticket_arguments['Subject'];
                //Serviço de Conectividade = 989624e9-4b7f-4bef-ab65-aa6135d52299
                //Indisponibilidade = a0a77087-9029-4dcd-a8ab-13a40c8df466
                // sla = Última milha = d682a47f-3160-4001-b9ab-56814bd3e864
                $ServiceID = '989624e9-4b7f-4bef-ab65-aa6135d52299';
                $subcategory = 'a0a77087-9029-4dcd-a8ab-13a40c8df466';
                $sla = 'd682a47f-3160-4001-b9ab-56814bd3e864';
						
				// descontinuado $ticket_dynamic_fields[2]['Value'] = $ic_uf;
				//$email_cliente = $email_cliente;

            }elseif($regra_tipo == "infrapop"){
                //acionamentos desativado
                $action_acionamentos = null;

				$titulo = $ticket_arguments['Subject'];
				//Infraestrutura::PoP = 2921
				$ServiceID = 2921;
				// descontinuado $ticket_dynamic_fields[2]['Value'] = $ic_uf;

			}elseif($regra_tipo == "backbone" && $serviceOuHost == "Service"){
                //acionamentos desativado
                //$action_acionamentos = null;

                $action_acionamentos = $acionamento_personalizado . "<br/>" . $action_acionamentos;

                $ic_designacao = $ic_recuperado_td[0]['designacao'];

                $titulo = $ticket_arguments['Subject'] . " (" . $ic_designacao . ")";
                
				//Serviço de Conectividade = 989624e9-4b7f-4bef-ab65-aa6135d52299
                //Indisponibilidade = a0a77087-9029-4dcd-a8ab-13a40c8df466
                // sla = Backbone = 89ca953a-5643-4a63-b2ef-46434e0fa2b4
                $ServiceID = '989624e9-4b7f-4bef-ab65-aa6135d52299';
                $subcategory = 'a0a77087-9029-4dcd-a8ab-13a40c8df466';
                $sla = '89ca953a-5643-4a63-b2ef-46434e0fa2b4';

				$email_cliente = $ticket_arguments['From'];
			}elseif($regra_tipo == "backbone" && $serviceOuHost == "Host"){
                //acionamentos desativado
                $action_acionamentos = null;

                $titulo = $ticket_arguments['Subject'];
                
				//Serviço de Conectividade = 989624e9-4b7f-4bef-ab65-aa6135d52299
                //Indisponibilidade = a0a77087-9029-4dcd-a8ab-13a40c8df466
                // sla = Backbone = 89ca953a-5643-4a63-b2ef-46434e0fa2b4
                $ServiceID = '989624e9-4b7f-4bef-ab65-aa6135d52299';
                $subcategory = 'a0a77087-9029-4dcd-a8ab-13a40c8df466';
                $sla = '89ca953a-5643-4a63-b2ef-46434e0fa2b4';

				$email_cliente = $ticket_arguments['From'];
				
			}elseif($regra_tipo == "stigti" || $regra_tipo == "sticentreon"){
                //acionamentos desativado
                $action_acionamentos = null;

				$titulo = $ticket_arguments['Subject'];
				//Infraestrutura = a0c472d6-fa00-47d7-9003-b386aa564ab4
                //Servidor = c3a90c32-07cc-48a7-85b7-11ead8ba3888
                // sla = Backbone = 2e589a5e-4d5d-4cf5-ba19-6a95b7ce892b
                $ServiceID = 'a0c472d6-fa00-47d7-9003-b386aa564ab4';
                $subcategory = 'c3a90c32-07cc-48a7-85b7-11ead8ba3888';
                $sla = null;

				$email_cliente = $ticket_arguments['From'];
			}elseif($regra_tipo == "stigsc"){
                //acionamentos desativado
                $action_acionamentos = null;

                foreach($ic_parents_td as $value_ic_parents_td){

                    if ($value_ic_parents_td["linkType"] == "parent"){
                        $parent_name = $value_ic_parents_td["name"] . ' / ' . $parent_name;
                        $parent_assetId = $value_ic_parents_td["assetId"];
                    }
            
                }
                $ic_avo_td = consultaICsAssociadosTopdesk($parent_assetId,
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
                );
                foreach($ic_avo_td as $value_ic_avo_td){

                    if ($value_ic_avo_td["linkType"] == "parent"){
                        
                        $avo_assetId = $value_ic_avo_td["assetId"];
                        //pessoas
                        $avo_persons = consultaIcAtribuicaoes($avo_assetId,
                        array(
                            'address' => $this->rule_data['address'],
                            'path' =>  $this->rule_data['path'],
                            'username' =>  $this->rule_data['username'], 
                            'password' =>  $this->rule_data['password']
                            )
                        );
                        //Responsáveis
                        foreach($avo_persons as $value_avo_persons){
                            $avo_persons_name = '@' . $value_avo_persons["person"]["name"];
                            $avo_r_name = $avo_persons_name . ' / ' . $avo_r_name;

                        }
                        //fim pessoas
                        $avo_name = '<br/>' . $value_ic_avo_td["name"] . ' - Responsável(eis): ' . $avo_r_name . $avo_name;
                        $avo_r_name = null;
                    }
            
                }
                $ic_bisa_td = consultaICsAssociadosTopdesk($avo_assetId,
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
                );
                foreach($ic_bisa_td as $value_ic_bisa_td){

                    if ($value_ic_bisa_td["linkType"] == "parent"){
                        //$bisa_name = $value_ic_bisa_td["name"] . ' / ' . $bisa_name;
                        $bisa_assetId = $value_ic_bisa_td["assetId"];
                        //pessoas
                        $bisa_persons = consultaIcAtribuicaoes($bisa_assetId,
                        array(
                            'address' => $this->rule_data['address'],
                            'path' =>  $this->rule_data['path'],
                            'username' =>  $this->rule_data['username'], 
                            'password' =>  $this->rule_data['password']
                            )
                        );
                        //Responsáveis
                        foreach($bisa_persons as $value_bisa_persons){
                            $bisa_persons_name = '@' . $value_bisa_persons["person"]["name"];
                            $bisa_r_name = $bisa_persons_name . ' / ' . $bisa_r_name;
                        }
                        //fim pessoas
                        $bisa_name = '<br/>' . $value_ic_bisa_td["name"] . ' - Responsável(eis): ' . $bisa_r_name . $bisa_name;
                        $bisa_r_name = null;
                    }
            
                }
                //incliur associados no corpo do chamado
                if($serviceOuHost == "Host"){
                    $ticket_arguments['Body'] = splitBody($ticket_arguments['Body'], $parent_name, $avo_name);
                    //$titulo = $avo_name . " - " . $ticket_arguments['Subject'];
                }else{
                    $ticket_arguments['Body'] = splitBody($ticket_arguments['Body'], $avo_name, $bisa_name);
                    //$titulo = $bisa_name . " - " . $ticket_arguments['Subject'];
                }

				$titulo = $ticket_arguments['Subject'];
				//Infraestrutura = a0c472d6-fa00-47d7-9003-b386aa564ab4
                //Servidor = c3a90c32-07cc-48a7-85b7-11ead8ba3888
                // sla = Backbone = 2e589a5e-4d5d-4cf5-ba19-6a95b7ce892b
                $ServiceID = 'a0c472d6-fa00-47d7-9003-b386aa564ab4';
                $subcategory = 'c3a90c32-07cc-48a7-85b7-11ead8ba3888';
                $sla = null;

				$email_cliente = $ticket_arguments['From'];

            }

			
            $titulo = str_replace("<br/>", " ", $titulo);
            //$titulo = str_replace(" Enlace ", " / ", $titulo);
            $titulo = str_replace("Abrir chamado na Operadora", " ", $titulo);

            if (strlen($titulo) > 80){
                $titulo = substr($titulo,0,80);
            };
            
            // imagem precisa ser redimencioanda topdesk suporta apenas 450x450
            /**$urlImg = 'https://monitoramento-hml.rnp.br/centreon/include/views/graphs/generateGraphs/generateImage.php?username=nocpro&token=pou1CDQwpr&start=1603653145&end=1603739545&hostname=MONITORAMENTO-PE-HML&service=Disk-/';
            $imgBase64 = getImageDataFromUrl($urlImg);
            $ticket_arguments['Body'] .= '<br/>Histórico:<br/><img src="' . $imgBase64 . '">';**/

			
			$argument = array(
                    'action'            => $action_acionamentos,
                    'request'           => $ticket_arguments['Body'], /** .  date('Y-m-d\TH:i:s.u', strtotime($ticket_dynamic_fields[1]['Value'])),*/
                    //'request'           => 'corpo do chamdo',
                    'briefDescription'  => $titulo,
                    //'caller'            =>  array('id' =>  'c3870881-03fa-41b5-a88d-2d65aed12ea8'),
                    'callerLookup'      => array(
                        'email' => $email_cliente
                        ),
                    //o campo type refere-se ao tipo de chamado, incidente, requisição, etc. No contexto do nocpro ele será usada para outro fim e todos os chamado serão do tipo Incidente
                    'callType'  => array(
                        'name' => 'Incidente'
                    ),
                    'category' => array(
                        'id' => $ServiceID
                    ),
                    'subcategory' => array(
                        'id' => $subcategory
                    ),
                    'object' => array(
                        'name' => $ticket_arguments['CustomerUser']
                    ),
                    'sla' => array(
                        'id' => $sla
                    ),
                    'operator' => array(
                        'id' => $ticket_arguments['Queue']
                    ),
                    'operatorGroup' => array(
                        'id' => $ticket_arguments['Queue']
                    ),
                    'processingStatus' => array(
                        'id' => $ticket_arguments['State']
                    ),
                    'optionalFields1' => array(
                        'date1' => date('Y-m-d\TH:i:s.uP', strtotime($ticket_dynamic_fields[1]['Value']))
                    ),
                    'entryType' => array(
                         'name' => 'Monitoramento'
                     )
                    
            );
			
			
            $topdesk_call_response = callRestTopdesk(
                $argument,
                array(
                    'address' => $this->rule_data['address'],
                    'path' =>  $this->rule_data['path'],
                    'username' =>  $this->rule_data['username'], 
                    'password' =>  $this->rule_data['password']
                    )
            );

            if (json_decode($topdesk_call_response, FALSE) || is_null($topdesk_call_response['id'])){
                $this->_otrs_call_response['TicketNumber'] = json_encode($topdesk_call_response);
            }else{
                $this->_otrs_call_response['TicketNumber'] = $topdesk_call_response['number'] . "::" . $topdesk_call_response['id'];
            }

        }
        else{
			
            $this->_otrs_call_response['TicketNumber'] = "ticket já existe::" . $ticket_existenteTopdesk[0]['number'] . "::" . $ticket_existenteTopdesk[0]['id'];
            
        }
        
        return 0;
    }
    
    protected function loginOtrs() {
        global $otrs_api_user, $otrs_api_senha;
        if ($this->_otrs_connected == 1) {
            return 0;
        }

        if (!extension_loaded("curl")) {
            $this->setWsError("cannot load curl extension");
            return -1;
        }
        
        $argument = array('UserLogin' => $otrs_api_user, 'Password' => $otrs_api_senha);
        if ($this->callRest('Session', $argument) == 1) {
            return -1;
        }
        
        $this->_otrs_session = $this->_otrs_call_response['SessionID'];
        $this->_otrs_connected = 1;
		salvaSessao($this->_otrs_session);
        return 0;
    }
    
    protected function callRest($function, $argument) {
        global $otrs_address ;
        $this->_otrs_call_response = null;
       
        $proto = 'http';
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
        }
        
        $argument_json = json_encode($argument);
        $base_url = $proto . '://' . $otrs_address . '/otrs' . '/' . 'nph-genericinterface.pl/Webservice' . '/' . 'nocPro' . '/' . $function ;
        $ch = curl_init($base_url);
        if ($ch == false) {
            $this->setWsError("cannot init curl object");
            return 1;
        }
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $argument_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($argument_json))
        );
        $result = curl_exec($ch);
        if ($result == false) {
            $this->setWsError(curl_error($ch));
            curl_close($ch);
            return 1;
        }
                
        $decoded_result = json_decode($result, TRUE);
        if (is_null($decoded_result) || $decoded_result == false) {
            $this->setWsError($result);
            return 1;
        }
        
        curl_close($ch);
        
        if (isset($decoded_result['Error'])) {
            $this->setWsError($decoded_result['Error']['ErrorMessage']);
            return 1;
        }
        
        $this->_otrs_call_response = $decoded_result;
        return 0;
    }    
    
    public function closeTicket(&$tickets) {
        if ($this->doCloseTicket()) {
            foreach ($tickets as $k => $v) {
                if ($this->closeTicketOtrs($k) == 0) {
                    $tickets[$k]['status'] = 2;
                } else {
                    $tickets[$k]['status'] = -1;
                    $tickets[$k]['msg_error'] = $this->ws_error;
                }
            }
        } else {
            parent::closeTicket($tickets);
        }
    }
}
