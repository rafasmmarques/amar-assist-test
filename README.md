# Amar Assist Test

Sistema simples de cobrancas em monorepo, planejado para backend Laravel 9, frontend Vue 3, MySQL, Redis, filas Redis, Horizon e Docker Compose.

## Estado atual

Este repositorio esta na Fase 6 do plano de implementacao: Autenticacao Sanctum.

O backend Laravel 9 foi criado em `backend/` com os pacotes Sanctum e Horizon instalados em versoes compativeis. O frontend Vue 3 com Vite foi criado em `frontend/`. O ambiente Docker Compose sobe backend PHP-FPM, Nginx, frontend Vite, MySQL, Redis, worker de filas e Horizon. A autenticacao SPA usa cookies de sessao do Sanctum, CSRF e endpoints minimos de login, usuario autenticado e logout. Ainda nao ha regras funcionais, rotas de dominio, migrations de dominio, telas de negocio ou regras de autorizacao.

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

## Documentacao

- `AGENTS.md`: instrucoes operacionais do projeto para agentes.
- `docs/implementation-plan.md`: plano de implementacao faseado.
- `.codex/agents/`: definicoes dos subagentes planejados.

## Proximos passos

Seguir `docs/implementation-plan.md` fase a fase, sem antecipar scaffolds ou regras funcionais antes das especificacoes previstas.
