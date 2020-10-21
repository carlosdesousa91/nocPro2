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

Com isso o Provider Topdesk ficará disponível no Centreon todo baseado no provider do Otrs.

### Classe TopdeskProvider

### Verificar Ticket

``` function verificaTicket($id_relacinamento, $horadafalha, $rule_data=array()) ```

- A verificação do ticket é feita comparando o "Objeto" do chamado com o campo notes do service/host do centreon.
- Se um chamado for encontrado é verificado se a falha já ultrapassou uma hora.