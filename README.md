# nocPro2
 NOC Proactive versão 2.0.
# Descrição
 O NOC Proativo ou simplesmente NOCPro é o assistente do Centreon baseado no modulo OpenTicket e no sistema de notificações para integração com a ferramenta de ITSM da RNP.
#Instalação/Configuração
- instalar modulo open ticket no centreon - https://documentation.centreon.com/docs/centreon-open-tickets/en/latest/installation/index.html
$ yum install centreon-open-tickets

- Habilitar modulo e widGets open ticket.
- criar diretorio /usr/share/centreon/www/modules/centreon-open-tickets/providers/Topdesk/nocPro/
- copiar arquivo topdesk_class.php para o diretorio acima.
