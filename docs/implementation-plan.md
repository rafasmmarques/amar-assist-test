# Plano de implementacao

## 1. Resumo executivo

Este plano prepara um teste tecnico para um sistema simples de cobrancas em monorepo. A implementacao futura deve usar Laravel 9, Vue 3 com Vite e Composition API, MySQL, Redis, filas Redis, Horizon, Docker Compose, Sanctum SPA, PHPUnit e documentacao em PT-BR.

Nesta etapa nao devem ser criados Laravel, Vue, Dockerfiles, migrations, models, controllers, componentes, endpoints nem regras funcionais. O trabalho inicial e especificar antes de implementar, seguindo Spec-Driven Development.

Estado de ambiente confirmado em 2026-08-16:

- diretorio: `/home/rafaelmarques/projects/amar-assist-test`;
- WSL2 Ubuntu: `Linux ... microsoft-standard-WSL2`;
- Git: `/usr/bin/git`, versao `2.43.0`;
- `AGENTS.md` e `docs/implementation-plan.md` acessiveis.

## 2. Requisitos confirmados

- Monorepo com `backend/`, `frontend/`, `docs/`, `.codex/`, `AGENTS.md`, `docker-compose.yml` e `README.md`.
- Backend Laravel fixado em `^9.0`, sem sugestao de upgrade.
- Frontend Vue 3, Vite e Composition API.
- MySQL como banco principal.
- Redis para cache e filas.
- Horizon para monitoramento protegido das filas.
- Sanctum para autenticacao SPA.
- PHPUnit como suite principal de testes.
- PT-BR na interface, mensagens, documentacao, commits e PRs.
- Arquitetura monolitica modular, SOLID pragmatico, Clean Code, YAGNI e Spec-Driven Development.
- Telas obrigatorias: login, clientes e cobrancas.
- Entidades principais: usuarios, clientes, contratos, cobrancas e detalhes do meio de pagamento.

## 3. Ambiguidades, decisoes e questoes de negocio

- O repositorio pode ainda precisar de inicializacao Git; decidir antes do primeiro commit.
- O teste menciona no minimo quatro tabelas; a solucao nao deve forcar exatamente quatro quando autenticacao, idempotencia e normalizacao exigirem mais.
- Pagamento simulado sera incluido porque a regra de cobranca paga, snapshot e idempotencia dependem disso.
- Regra inicial de desativacao: qualquer contrato associado ao cliente impede desativacao. Nao limitar a contratos ativos sem autorizacao explicita.
- Questao de negocio futura: avaliar se somente contratos ativos deveriam impedir desativacao. Essa interpretacao nao e regra confirmada.
- Contrato deve manter `person_type` com valores `PF` e `PJ`, apesar da redundancia com CPF/CNPJ do cliente, porque e requisito explicito do teste.
- `person_type` deve ser validado contra o documento do cliente: CPF exige `PF`; CNPJ exige `PJ`.
- Cobranca que vence na data de referencia ainda nao esta atrasada; atraso existe somente quando `data_referencia > data_vencimento`.
- Datas de calculo usam datas civis em `America/Sao_Paulo`, sem diferenca por horas.

## 4. Compatibilidade Laravel 9

- Criar backend com `composer create-project laravel/laravel backend "^9.0"`.
- Verificar a versao de PHP exigida pelo Laravel 9 antes de instalar dependencias. Laravel 9 requer PHP compativel com a serie 9, em geral PHP `^8.0.2` conforme constraints do framework.
- Fixar Sanctum em versao compativel com Laravel 9, evitando instalar automaticamente a linha atual se ela exigir Laravel mais novo.
- Fixar Horizon em versao compativel com Laravel 9, evitando instalar automaticamente a linha atual se ela exigir Laravel mais novo.
- Registrar no README que Laravel 9 esta fora do suporte atual, mas e requisito obrigatorio do teste.
- Nao sugerir upgrade de Laravel como alternativa.

## 5. Decisoes arquiteturais

- Laravel 9 sera a fonte do dominio e da API.
- Vue consumira a API por um cliente HTTP centralizado.
- Nao usar repository pattern por padrao; Eloquent cobre acesso a dados no escopo do teste.
- Services ou Actions serao usados para calculo de vencimento, calculo de encargos, geracao idempotente e pagamento idempotente.
- Policies serao usadas para autorizacao das rotas funcionais.
- Enums ou estruturas equivalentes compativeis com Laravel 9 representarao estados e tipos fixos.
- Dados especificos do meio de pagamento devem privilegiar normalizacao e seguranca: usar tabela 1:1 `charge_payment_details` como solucao minima.
- Nao criar catalogo para os tres tipos fixos de pagamento.
- Nunca armazenar PAN completo ou CVV em banco, logs ou respostas.

## 6. Modelo de dados planejado

### `users`

- `id` bigint PK
- `name` varchar(160)
- `email` varchar(180) unique
- `password` varchar(255)
- timestamps

### `clients`

- `id` bigint PK
- `name` varchar(180)
- `document_type` enum/string validado: `cpf`, `cnpj`
- `document` varchar(14), normalizado apenas com digitos
- `address` json controlado
- `contact` json controlado
- `status` enum/string validado: `active`, `inactive`
- timestamps
- unique `document`
- indices para `status`, `name` e `status, name`

### `contracts`

- `id` bigint PK
- `client_id` FK para `clients.id`
- `person_type` enum/string validado: `PF`, `PJ`
- `billing_cycle_day` tinyint unsigned, 1 a 31
- `status` enum/string validado: `active`, `ended`
- `started_at` date
- `ended_at` date nullable
- timestamps
- indice `client_id`
- indice composto `client_id, status`
- regra: `ended_at` obrigatorio quando `status = ended`
- regra: `person_type` coerente com CPF/CNPJ do cliente

### `charges`

- `id` bigint PK
- `uuid` char(36) ou string unique para identificador publico
- `contract_id` FK para `contracts.id`
- `billing_period` representando competencia mensal, preferencialmente `date` no primeiro dia do mes ou coluna equivalente claramente documentada
- `payment_method` enum/string validado: `boleto`, `card`, `pix`
- `original_amount` decimal(12,2)
- `fixed_fee_amount` decimal(12,2), default 0
- `due_date` date
- `status` enum/string validado: `open`, `paid`
- `paid_at` timestamp nullable
- snapshot imutavel de pagamento: `paid_original_amount`, `paid_fixed_fee_amount`, `paid_late_interest_amount`, `paid_total_amount`, `paid_at`
- `idempotency_key` varchar(120) nullable unique quando utilizada
- timestamps
- unique `uuid`
- unique `contract_id, billing_period`
- indices para `contract_id`, `status`, `due_date` e `status, due_date, id`

### `charge_payment_details`

- `id` bigint PK
- `charge_id` FK unique para `charges.id`
- `boleto_barcode` varchar nullable
- `pix_key` varchar nullable
- `pix_transaction_id` varchar nullable
- `card_reference` varchar nullable
- `card_brand` varchar nullable
- `card_last4` char(4) nullable
- timestamps

Comparativo de alternativas para meio de pagamento:

- JSON validado: menor atrito, mas menor integridade relacional e maior risco de vazar campos indevidos.
- Tabela 1:1 `charge_payment_details`: solucao minima escolhida; representa boleto, Pix e cartao tokenizado com colunas explicitas e permite validacao por tipo.
- Uma tabela por meio: maior normalizacao, mas complexidade desnecessaria para tres formatos pequenos no teste.

## 7. Regras de negocio formalizadas

### Clientes

- CPF/CNPJ aceito com ou sem mascara e salvo apenas com digitos.
- Validar tamanho e digitos verificadores de CPF/CNPJ.
- `document` unico globalmente.
- Estados: `active` e `inactive`.
- Cliente com qualquer contrato associado nao pode ser desativado.
- A desativacao deve ocorrer em transacao e verificar contratos no banco no momento da mudanca.

### Contratos

- Contrato pertence a um cliente.
- `person_type` e requisito explicito e deve ser preservado no contrato.
- CPF do cliente exige contrato `PF`; CNPJ do cliente exige contrato `PJ`.
- `billing_cycle_day` representa dia mensal de vencimento, 1 a 31.
- Calculo de vencimento: usar o dia configurado quando existir no mes; caso contrario, usar o ultimo dia valido, incluindo ano bissexto.

### Cobrancas e encargos

Formula inicial obrigatoria:

```text
dias_atraso = max(0, data_referencia - data_vencimento)
juros_atraso = valor_original x 0,01 x dias_atraso
total_atualizado = valor_original + multa_fixa + juros_atraso
```

- Multa fixa nao integra a base dos juros.
- Atraso existe somente quando `data_referencia > data_vencimento`.
- Uma cobranca vencendo na data de referencia nao esta atrasada.
- Usar datas civis no timezone `America/Sao_Paulo`, sem calcular dias por diferenca de horas.
- Usar decimal seguro e arredondamento half-up para duas casas; nunca `float`.
- Cobranca paga nao pode ter total recalculado usando a data atual; deve retornar o snapshot persistido no pagamento.

### Pagamento idempotente

O pagamento deve:

1. abrir uma transacao;
2. bloquear a cobranca para atualizacao;
3. verificar se ela ja foi paga;
4. calcular valores usando a data do pagamento;
5. persistir snapshot imutavel;
6. marcar a cobranca como paga;
7. retornar o mesmo resultado em chamadas repetidas.

### Geracao idempotente

- Toda cobranca deve ter `billing_period` mensal explicita.
- A geracao deve possuir restricao unica `contract_id + billing_period`.
- Nao usar `payment_method` para permitir duas faturas do mesmo contrato e competencia.
- `idempotency_key`, quando usada em endpoints de escrita, deve ter restricao `unique`.
- Geracao deve usar transacao e tratar concorrencia.

### Ordenacao de cobrancas

Formalizar no backend antes da paginacao:

1. abertas e vencidas;
2. abertas nao vencidas;
3. pagas;
4. dentro do grupo: vencimento crescente;
5. desempate: identificador crescente.

Planejar expressao SQL com `CASE` ou equivalente, garantindo ordenacao antes de `paginate()`.

## 8. Contratos principais da API

Prefixo previsto: `/api`.

Rotas publicas minimas:

- `GET /sanctum/csrf-cookie`
- `POST /login`

Todas as demais rotas funcionais exigem autenticacao.

Sessao:

- `POST /logout`
- `GET /me`

Clientes:

- `GET /clients?page=&per_page=&name=&status=&document=`
- `POST /clients`
- `GET /clients/{client}`
- `PUT/PATCH /clients/{client}`
- `PATCH /clients/{client}/activate`
- `PATCH /clients/{client}/deactivate`

Contratos:

- `GET /clients/{client}/contracts`
- `POST /clients/{client}/contracts`
- `GET /contracts/{contract}`
- `PATCH /contracts/{contract}/end`, se encerramento fizer parte da especificacao final

Cobrancas:

- `GET /charges?page=&per_page=&status=&payment_method=&client=&contract=&due_from=&due_to=`
- `GET /charges/{charge}`
- `POST /charges/generate`
- `POST /charges/{charge}/pay`

Contratos da API:

- `per_page` deve ter limite minimo e maximo, por exemplo 1 a 100.
- Filtros devem ser validados e rejeitar valores desconhecidos.
- Erros devem seguir formato JSON consistente em PT-BR, sem vazar detalhes internos.
- Paginacao deve retornar estrutura previsivel de dados e metadados.

## 9. Arquitetura do backend

Estrutura prevista em `backend/`:

- `app/Models`: Client, Contract, Charge, ChargePaymentDetail, User.
- `app/Http/Controllers/Api`: controllers finos.
- `app/Http/Requests`: validacao de payloads.
- `app/Http/Resources`: serializacao da API.
- `app/Policies`: autorizacao.
- `app/Actions` ou `app/Services`: vencimento, encargos, geracao e pagamento.
- `app/Jobs`: geracao em lote por competencia.
- `app/Enums` ou equivalentes compativeis com Laravel 9.
- `routes/api.php`: rotas publicas minimas e rotas autenticadas.

Sanctum deve ser configurado para SPA com CSRF e cookies por ambiente.

## 10. Arquitetura do frontend

Estrutura prevista em `frontend/`:

- `src/main.ts`
- `src/router`
- `src/api/http.ts`
- `src/features/auth`
- `src/features/clients`
- `src/features/charges`
- `src/components`
- `src/composables`

Telas:

- Login com sessao, logout e recuperacao via `/me`.
- Clientes com paginacao, filtros combinaveis e acao de ativar/desativar.
- Cobrancas com paginacao, filtros, destaque de atraso, total discriminado e pagamento simulado.
- Nao criar dashboards, graficos ou integracoes externas sem necessidade aprovada.

## 11. Docker, WSL e ambiente local

- O projeto deve permanecer no filesystem do WSL.
- Nao executar ferramentas Windows sobre caminhos do WSL.
- Usar comandos no host WSL somente durante bootstrap quando ainda nao houver container.
- Depois do Docker configurado, priorizar comandos dentro dos containers.
- Verificar o ambiente antes de repetir comando que falhou.
- Nunca expor segredos em diagnosticos.

Servicos previstos:

- `backend`: PHP-FPM/Laravel.
- `nginx`: servidor HTTP para backend, se necessario.
- `frontend`: Node/Vite.
- `mysql`: banco.
- `redis`: cache e filas.
- `queue`: worker Laravel.
- `horizon`: monitoramento de filas protegido.

Comandos preferenciais apos Docker:

```bash
docker compose exec backend composer ...
docker compose exec backend php artisan ...
docker compose exec frontend npm ...
```

## 12. Redis, filas, cache e Horizon

Uso de filas nao e opcional: deve existir um caso concreto pequeno.

- Caso: geracao em lote de cobrancas por competencia executada em Job.
- Redis como driver da fila.
- Job idempotente por competencia e contrato, respeitando `contract_id + billing_period`.
- Definir `tries` e `backoff` no Job.
- Registrar falhas sem expor dados sensiveis.
- Horizon protegido por autenticacao/autorizacao.
- Testar dispatch com `Queue::fake()`.
- Testar a regra executada pelo Job sem depender apenas do fake.

Cache pequeno planejado:

- Preferir cache de resumo operacional nao sensivel, por exemplo contagem de cobrancas por status do usuario autenticado.
- Chave: `charges:summary:user:{user_id}:filters:{hash}`.
- TTL inicial: 60 segundos.
- Invalidar em criacao, atualizacao, pagamento e geracao em lote de cobrancas.
- Se a especificacao concluir que cache de cobrancas cria mais risco que beneficio, mover cache para dados estaveis e nao sensiveis e registrar justificativa.

## 13. Estrategia SDD

Antes de cada fase funcional:

1. definir ou atualizar especificacao em `docs/specs/`;
2. atualizar criterios de aceite;
3. criar ou ajustar testes;
4. implementar o minimo necessario;
5. validar;
6. revisar contra a especificacao;
7. registrar decisoes relevantes.

Especificacoes previstas:

- `docs/specs/domain.md`;
- `docs/specs/api.md`;
- `docs/specs/security.md`;
- `docs/specs/acceptance.md`;
- `docs/specs/traceability.md`.

Nenhuma regra funcional deve ser implementada antes de sua especificacao e criterios de aceite.

## 14. Estrategia de testes

PHPUnit deve cobrir:

- autenticacao, logout e recuperacao de sessao;
- autorizacao das rotas funcionais;
- protecao do Horizon;
- filtros, limites de `per_page`, erros e paginacao;
- validacao e unicidade de CPF/CNPJ;
- coerencia entre `PF/PJ` do contrato e CPF/CNPJ do cliente;
- qualquer contrato impedindo desativacao do cliente;
- vencimento nao considerado atrasado no proprio dia;
- mudanca de mes e ano;
- ano bissexto;
- vencimentos em meses com 28, 29, 30 e 31 dias;
- calculo de juros com multa fora da base;
- pagamento realizado apos atraso;
- snapshot do pagamento imutavel;
- pagamento idempotente;
- dois pagamentos concorrentes;
- geracao idempotente por `contract_id + billing_period`;
- duas geracoes concorrentes;
- ordenacao antes da paginacao;
- Job de geracao em lote com `Queue::fake()`;
- regra executada pelo Job;
- ausencia de PAN e CVV em banco, logs e respostas.

Frontend:

- validacao minima de login, filtros, estados de tela e consumo do contrato da API.
- evitar suite extensa enquanto o teste exigir principalmente PHPUnit.

## 15. Documentacao

README futuro deve explicar:

- arquitetura;
- requisitos locais;
- Laravel 9 fora de suporte, mas obrigatorio;
- configuracao do `.env` sem segredos reais;
- Docker;
- migrations e seeders;
- URLs de frontend, API e Horizon;
- credenciais demonstrativas;
- execucao de testes;
- documentacao da API;
- decisoes e limitacoes.

API docs: preferir Markdown em `docs/api.md` ou geracao simples compativel com Laravel 9. Evitar dependencia pesada sem ganho claro.

## 16. Fases de implementacao

### Fase 1 - Preparacao do repositorio e Git

- Objetivo: inicializar base do monorepo e documentacao minima.
- Areas: raiz, `docs/`, `.gitignore`, `README.md` planejado.
- Agente: `reviewer`.
- Testes: nenhum runtime ainda.
- Aceite: estrutura base definida sem Laravel/Vue inicializados indevidamente.
- Validacao: `git status --short`, `find . -maxdepth 2 -print`.

### Fase 2 - Especificacoes versionadas

- Objetivo: registrar dominio, API, seguranca, aceite e rastreabilidade antes de regras funcionais.
- Areas: `docs/specs/`.
- Agente: `reviewer`.
- Testes: revisao manual contra requisitos.
- Aceite: specs cobrem clientes, contratos, cobrancas, auth, seguranca, filas e cache.

### Fase 3 - Scaffold do Laravel 9

- Objetivo: criar backend Laravel 9 com constraints compativeis.
- Areas: `backend/`.
- Agente: `laravel-specialist`.
- Testes: teste padrao do Laravel.
- Aceite: app confirma Laravel 9 e dependencias nao usam versoes incompativeis.

### Fase 4 - Scaffold do Vue 3

- Objetivo: criar Vue 3 + Vite com estrutura inicial.
- Areas: `frontend/`.
- Agente: `vue-expert`.
- Testes: build padrao.
- Aceite: app roda e build passa.

### Fase 5 - Docker Compose

- Objetivo: subir backend, frontend, MySQL, Redis, worker e Horizon.
- Areas: `docker-compose.yml`, configs Docker.
- Agente: `laravel-specialist`.
- Testes: healthchecks e comandos dentro dos containers.
- Aceite: `docker compose up -d` sobe servicos essenciais.

### Fase 6 - Autenticacao Sanctum

- Objetivo: configurar Sanctum SPA e endpoints de sessao.
- Areas: backend auth, CORS, frontend auth inicial.
- Agente: `laravel-specialist`.
- Testes: auth feature tests.
- Aceite: rotas funcionais protegidas; somente CSRF e login publicos.

### Fase 7 - Modelo de dados

- Objetivo: criar migrations, models, factories e seeders.
- Areas: `backend/database`, `backend/app/Models`.
- Agente: `laravel-specialist`.
- Testes: migrations, factories e validacoes estruturais.
- Aceite: schema inclui contratos com `person_type`, cobrancas com `billing_period`, snapshot e detalhes 1:1 de pagamento.

### Fase 8 - Clientes e contratos

- Objetivo: API de clientes e contratos minimos com validacao e regras.
- Areas: controllers, requests, resources, policies, actions.
- Agente: `laravel-specialist`.
- Testes: CPF/CNPJ, PF/PJ, filtros, bloqueio de desativacao e vencimentos.
- Aceite: endpoints de clientes e contratos funcionam com contrato previsivel.

### Fase 9 - Cobrancas

- Objetivo: gerar, consultar, ordenar e pagar cobrancas simuladas.
- Areas: Charge actions/services, API, resources, Job de geracao quando aplicavel.
- Agente: `laravel-specialist`.
- Testes: atraso, multa fora da base, snapshot, idempotencia, concorrencia e ordenacao.
- Aceite: cobrancas abertas vencidas aparecem primeiro e totais sao discriminados corretamente.

### Fase 10 - Telas

- Objetivo: login, clientes e cobrancas.
- Areas: `frontend/src/features`.
- Agente: `vue-expert`.
- Testes: build e validacao manual.
- Aceite: fluxos principais navegaveis em PT-BR com estados de carregamento, vazio e erro.

### Fase 11 - Redis, filas, cache e Horizon

- Objetivo: ativar uso minimo de Redis, Job de geracao em lote, cache pequeno e Horizon protegido.
- Areas: backend queue/cache/Horizon.
- Agente: `laravel-specialist`.
- Testes: `Queue::fake()`, teste da regra do Job, cache e protecao do Horizon.
- Aceite: uso de Redis e Horizon e concreto, pequeno e testado.

### Fase 12 - Documentacao e revisao final

- Objetivo: revisar arquitetura, seguranca, testes e docs.
- Areas: todo o monorepo.
- Agente: `reviewer`.
- Testes: suite backend e build frontend.
- Aceite: sem achados bloqueantes e rastreabilidade completa.

## 17. Criterios de aceite por fase

- Cada fase tem especificacao ou decisao rastreavel.
- Cada fase altera apenas areas declaradas.
- Cada fase tem validacao objetiva.
- Nenhuma fase funcional ocorre antes de specs e criterios de aceite.
- Nenhuma fase deve armazenar dados sensiveis indevidos.
- Reviewer deve revisar antes da conclusao de fases com impacto em seguranca, contrato ou dinheiro.

## 18. Riscos e mitigacao

- Laravel 9 fora do suporte: travar versoes compativeis, registrar obrigatoriedade e nao sugerir upgrade.
- Sanctum SPA em Docker: validar CORS, cookies e stateful domains cedo.
- Juros e arredondamento: specs e testes com relogio controlado e datas civis.
- Dados de cartao: usar detalhes tokenizados, `last4` e bandeira; bloquear PAN completo e CVV.
- Concorrencia em geracao e pagamento: transacoes, locks e constraints unicas.
- Cache de cobrancas: manter TTL curto, invalidacao explicita e evitar dados sensiveis.
- Horizon publico: proteger por autenticacao/autorizacao desde a configuracao.

## 19. Definicao de pronto

Uma fase futura esta pronta quando:

- especificacao e criterios de aceite estao atualizados;
- testes relevantes existem e passam;
- implementacao e a menor suficiente para o requisito;
- seguranca, dados sensiveis e autorizacao foram verificados;
- README/docs refletem comandos e limitacoes;
- risco residual esta registrado;
- commit sugerido e escopo estao claros.

## 20. Comandos previstos

Preparacao:

```bash
git status --short
find . -maxdepth 2 -print
```

Bootstrap backend antes de containers:

```bash
composer create-project laravel/laravel backend "^9.0"
cd backend
composer require laravel/sanctum:^3.0 laravel/horizon:^5.0
php artisan --version
php artisan test
```

Bootstrap frontend antes de containers:

```bash
npm create vite@latest frontend -- --template vue-ts
cd frontend
npm install
npm run build
```

Docker:

```bash
docker compose up -d
docker compose ps
```

Comandos apos Docker configurado:

```bash
docker compose exec backend composer install
docker compose exec backend composer require laravel/sanctum:^3.0 laravel/horizon:^5.0
docker compose exec backend php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan test
docker compose exec frontend npm install
docker compose exec frontend npm run build
docker compose exec backend php artisan horizon
docker compose exec backend php artisan queue:failed
```

## 21. Subagentes planejados

- `laravel-specialist`: backend, banco, Sanctum, filas, Horizon e regras de cobranca.
- `vue-expert`: frontend, UX, Composition API, rotas e integracao HTTP.
- `reviewer`: revisao de arquitetura, seguranca, testes e aderencia ao plano.

Responsabilidades nao devem se sobrepor: backend, frontend e revisao.
