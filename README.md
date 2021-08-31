# nocPro2
 NOC Proactive versão 2.0.
# Descrição
 O NOC Proativo ou simplesmente NOCPro é o assistente do Centreon baseado no modulo OpenTicket e no sistema de notificações para integração com a ferramenta de ITSM da RNP.
## Instalação/Configuração
- instalar modulo open ticket no centreon - https://documentation.centreon.com/docs/centreon-open-tickets/en/latest/installation/index.html

``` $ yum install centreon-open-tickets ```

- Habilitar modulo e widGets open ticket.

### ToPDeskProvider

- Registrar o provider Topdesk no arquivo /usr/share/centreon/www/modules/centreon-open-tickets/providers/register.php

``` $register_providers['Topdesk'] = 14; ```

- Copiar o diretório providers/Topdesk para /usr/share/centreon/www/modules/centreon-open-tickets/providers/Topdesk

- confirmar se nome da classe instanciada do arquivo abstract é o mesmo do nome do arquivo. TopdeskProvider.class.php

Com isso o Provider Topdesk ficará disponível no Centreon todo baseado no provider do Otrs.

### Classe TopdeskProvider

### Verificar Ticket

``` function verificaTicket($id_relacinamento, $horadafalha, $rule_data=array()) ```

- A verificação do ticket é feita comparando o "Objeto" do chamado com o campo notes do service/host do centreon.
- Se um chamado for encontrado é verificado se a falha já ultrapassou uma hora.

### Criação do Ticket

``` function createTicketTopdesk($ticket_arguments, $ticket_dynamic_fields, $serviceOuHost, $tabRelacionamentoFull) ```

``` callRestTopdesk($argument, $rule_data=array()) ```

#### criar "Rule" "nocPro2_UM"(regra para última milha):

##### agumentos:

	Subject: {if $host_selected|@count gt 0}{foreach from=$host_selected item=host}{$host.name}{/foreach}{/if}{if $service_selected|@count gt 0}{foreach from=$service_selected item=service}{$service.description}{/foreach}{/if}
	Body: {$body}
	From: {$user.email}
	Queue: [incluir id do grupo de operadores vindo do topdesk]
	Priority: 3 normal
	State: [incluir id do processingStatus "Aberto" vindo topdesk]
	Type: ultimamilha (obs: o campo type refere-se ao tipo de chamado, incidente, requisição, etc. No contexto do nocpro ele será usada para outro fim e todos os chamado serão do tipo Incidente).
	Custumer user: {if $host_selected|@count gt 0}{foreach from=$host_selected item=host}{$host.notes}{/foreach}{/if}{if $service_selected|@count gt 0}{foreach from=$service_selected item=service}{$service.notes}{/foreach}{/if}
	Content Type: text/html; charset=utf8
	#Campos dinamicos (devem ser criado na ordem abaixo):
		nocproserviceid: {if $host_selected|@count gt 0}{foreach from=$host_selected item=host}{$host.host_id}{/foreach}{/if}{if $service_selected|@count gt 0}{foreach from=$service_selected item=service}{$service.host_id}_{$service.service_id}{/foreach}{/if}
		HoradaFalha: {if $host_selected|@count gt 0}{foreach from=$host_selected item=host}{$host.last_hard_state_change|date_format:'%Y-%m-%d %H:%M:%S'}{/foreach}{/if}{if $service_selected|@count gt 0}{foreach from=$service_selected item=service}{$service.last_hard_state_change|date_format:'%Y-%m-%d %H:%M:%S'}{/foreach}{/if}
		PoP: uf
		Conexao: {if $host_selected|@count gt 0}{foreach from=$host_selected item=host}Host{/foreach}{/if}{if $service_selected|@count gt 0}{foreach from=$service_selected item=service}Enlace{/foreach}{/if}		
		URL: https://{$address}/tas/secure/contained/incident?action=show&unid={$ticket_id}

###### Confirm message popup:

copiar conteúdo do arquivo confirm_message_popup.html para esse campo.


###### Body list definition -> default

copiar conteúdo do arquivo body_um.html para esse campo.	
	
 - remover todas as opções do "Lists".
 - na guia "Advanced"  deixa o campo "guia Formatting popup" em branco. 

 - criar o arquivo '/usr/share/centreon/www/modules/centreon-open-tickets/providers/Topdesk/nocPro/_access.php' se necessário coletar os dados dos ics vindos do OTRS.

``` <?php $otrs_address = ''; $otrs_api_user = ''; $otrs_api_senha = ''; ?> ```

#### criar "Rule" "nocPro2_Backbone"(regra para Backbone):

Criar regra baseada na regra de última milha anterando os pontos necessários.

#### criar "Rules" "nocPro2_GTI", "nocPro2_GSC" e outras:

Criar regras baseadas na regra de última milha anterando os pontos necessários.

### ACK / Pop-up

no arquivo /usr/share/centreon/www/modules/centreon-open-tickets/providers/Abstract/AbstractProvider.class.php atualizar o function saveHistory.

#### inserir número do ticket no ack automatico

no arquivo /usr/share/centreon/www/modules/centreon-open-tickets/views/rules/ajax/actions/submitTicket.php passar a posição do array.

criar as variáveis $index_host e $index_service para contar os chamados e passar no array do ticket.

criar o laço com os services filhos e netos para receberem o ack.

# NOCPro relacionamentos de Ativos

## Funcionamento

- O NOCPro identificará os ativos Pais, Avós e Bisavós dos services(Services).
- O NOCPro identificará os ativos Pais e Avós dos Hosts(Centreon).
- Quando o chamado for aberto para um service(CPU, Ping, etc) o NOCPro incluirá no corpo do chamado as informações abaixo, sendo o Sistema o bisavô e o Servidor o avô:
``` 
Sistema: ESR - Site Público
Servidor: ESR-SITE PÚBLICO-PRD
```
- Quando o chamado for aberto para um Host(200.130......., etc) o NOCPro incluirá no corpo do chamado as informações abaixo, sendo o Sistema o avô e o Servidor o pai:
``` 
Sistema: ESR - Site Público
Servidor: ESR-SITE PÚBLICO-PRD
```
## exemplo de relacionamento:
 
- Quando um chamado for aberto para o CENTREON_4543 ou para um de seus filhos(CENTREON_4543_29565, CENTREON_4543_29576, etc) o NOCPro identificará o ESR-SITE PÚBLICO-PRD(Servidor) e o ESR - Site Público(Sistema).

## Procedimento

- Para a automação reconhecer os relacionamento é necessário somente vincular os Servidores como pais  dos seus respectivos Centreon - Hosts no Topdesk.

# Acionamentos

- 

# Erros Comuns

### No topdesk existem mais de um "Solicitante" com o e-mail padrão do ativo do PoP. O ativo do PoP deve possuir um e-mail exclusivo.
```"message":"Multiple callers were found with the provided lookup value"```
- Solução: Solicitar ao Service desk alteração do e-mail do Solicitante duplicado ou que peçam ao PoP um e-mail exclusivo.

### O operador deve ser cadastrado como solicitante no Topdesk. Quando o NOCPro não localiza um solicitante padrão para o chamado ele incluir o operador que está abrindo o chamado.
```message":"No caller could be found with the provided lookup value"```
- Solução: Realizar acesso no topdesk no portal de autoatendimento e validar se o e-mail está cadastrado corretamente.

### O IC não foi cadastrado ainda no Topdesk, a rotina de atualização é feita uma vez no dia.
```message":"The value 'CENTREON_4760_34668' for the field 'name' cannot be found."``` Ou
```message":"The value 'CENTREON."```
- Solução: Solicitar a importação manual dos ICs(tratamento para esse erro em desenvolvimento)

### ICK automático não funciona.
- Solução: Testar ACK manual, se não funcionar acionar equipe de infraestrutura da GTI.