# Amar Assist Test

Sistema simples de cobrancas em monorepo, planejado para backend Laravel 9, frontend Vue 3, MySQL, Redis, filas Redis, Horizon e Docker Compose.

## Estado atual

Este repositorio esta na Fase 9 do plano de implementacao: Cobrancas.

O backend Laravel 9 foi criado em `backend/` com os pacotes Sanctum e Horizon instalados em versoes compativeis. O frontend Vue 3 com Vite foi criado em `frontend/`. O ambiente Docker Compose sobe backend PHP-FPM, Nginx, frontend Vite, MySQL, Redis, worker de filas e Horizon. A autenticacao SPA usa cookies de sessao do Sanctum, CSRF e endpoints minimos de login, usuario autenticado e logout. O modelo de dados inicial inclui clientes, contratos, cobrancas e detalhes 1:1 de pagamento. Ainda nao ha telas de negocio.
As APIs minimas de clientes e contratos foram adicionadas com validacao de CPF/CNPJ, coerencia PF/PJ, filtros de clientes, bloqueio de desativacao com contratos e calculo de vencimento mensal.
As APIs de cobrancas permitem gerar cobranca simulada por contrato e competencia, listar com ordenacao de vencidas abertas primeiro, consultar totais discriminados e pagar com snapshot idempotente.

Laravel 9 esta fora do suporte atual, mas permanece como requisito obrigatorio do teste. Nao deve ser atualizado para Laravel 10+ sem mudanca explicita do requisito.

## Estrutura inicial

```text
backend/
frontend/
docker/
docs/
.codex/agents/
AGENTS.md
README.md
docker-compose.yml
```

## Docker Compose

Subir o ambiente local:

```bash
docker compose up -d
```

Servicos e portas expostas:

- `nginx`: API Laravel em `http://localhost:8080`.
- `frontend`: Vite em `http://localhost:5173`.
- `mysql`: MySQL em `localhost:3307`.
- `redis`: Redis em `localhost:6379`.
- `backend`, `queue` e `horizon`: servicos internos PHP/Laravel.

## Autenticacao

Endpoints iniciais:

- `GET /sanctum/csrf-cookie`: gera o cookie CSRF para a SPA.
- `POST /api/login`: inicia sessao com `email` e `password`.
- `GET /api/user`: retorna o usuario autenticado.
- `POST /api/logout`: encerra a sessao autenticada.

## Clientes e contratos

Endpoints iniciais autenticados:

- `GET /api/clients`: lista clientes com filtros validados e paginacao.
- `POST /api/clients`: cria cliente com CPF/CNPJ normalizado.
- `GET /api/clients/{client}`: detalha cliente.
- `PUT/PATCH /api/clients/{client}`: atualiza dados cadastrais.
- `PATCH /api/clients/{client}/activate`: ativa cliente.
- `PATCH /api/clients/{client}/deactivate`: desativa cliente sem contratos associados.
- `GET /api/clients/{client}/contracts`: lista contratos de um cliente.
- `POST /api/clients/{client}/contracts`: cria contrato PF/PJ compativel com o documento do cliente.
- `GET /api/contracts/{contract}`: detalha contrato.

## Cobrancas

Endpoints iniciais autenticados:

- `GET /api/charges`: lista cobrancas com filtros validados, paginacao e ordenacao de abertas vencidas primeiro.
- `GET /api/charges/{charge}`: detalha cobranca com totais discriminados ou snapshot de pagamento.
- `POST /api/charges/generate`: gera cobranca simulada por contrato e competencia.
- `POST /api/charges/{charge}/pay`: paga cobranca simulada de forma idempotente.

## Documentacao

- `AGENTS.md`: instrucoes operacionais do projeto para agentes.
- `docs/implementation-plan.md`: plano de implementacao faseado.
- `.codex/agents/`: definicoes dos subagentes planejados.

## Proximos passos

Seguir `docs/implementation-plan.md` fase a fase, sem antecipar scaffolds ou regras funcionais antes das especificacoes previstas.
