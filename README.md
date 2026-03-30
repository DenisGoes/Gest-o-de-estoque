Sistema de Gestão de Estoque
Este é um ecossistema de Backend robusto desenvolvido em PHP 8.x, utilizando a arquitetura MVC e focado em Segurança Defensiva e Integridade de Dados.

Pilares de Segurança
O sistema foi projetado para mitigar as principais vulnerabilidades da OWASP:

Proteção Ativa contra Brute Force: Implementação de uma tabela de login_attempts que rastreia falhas por IP. O sistema bloqueia automaticamente a origem após 5 tentativas falhas em um intervalo de 10 minutos.

Criptografia e Hashing:

Senhas: Utiliza password_hash com algoritmo BCRYPT.
Dados Sensíveis (CPF): Armazenados via SHA-256 Hashing, garantindo conformidade com princípios de privacidade (LGPD).

Blindagem de Dados:

SQL Injection: Uso mandatório de PDO com Prepared Statements.
XSS: Sanitização rigorosa de inputs e outputs.
Controle de Acesso (RBAC): Sistema de níveis (ADMIN vs USER). Rotas de aprovação de usuários e faturamento são restritas a administradores.


Estrutura de Pastas
/backend
├── config/ # Conexão PDO e Variáveis de Ambiente
├── controllers/ # Orquestração das requisições e lógica de fluxo
├── models/ # Abstração do Banco de Dados (Queries e Regras de Negócio)
├── utils/ # Helpers de Autenticação (Auth.php) e Segurança
├── .htaccess # Reescrita de URL (Friendly URLs)
└── index.php # Front Controller (Ponto de entrada único)
|__ database/schema.sql # Código sql do banco de dados

Integração com Front-end (React)
A API está preparada para comunicação Cross-Origin (CORS).

⚠️ Nota Importante: Para manter a sessão ativa, o cliente (Axios/Fetch) deve utilizar a flag withCredentials: true.


Lógica de Dashboard e Auditoria

O sistema não apenas salva dados, ele processa inteligência de negócio:

Faturamento Real-time: O DashboardController consome Views SQL (view_faturamento_vendas), entregando lucro bruto e totais já calculados pelo MySQL.
Alertas de Reposição: Filtro automático em quantidade_atual para produtos abaixo do limite crítico.
Trilha de Auditoria: Cada entrada ou saída de mercadoria alimenta a tabela movimentacoes_estoque via Triggers, permitindo saber quem, quando e por que o estoque mudou.


