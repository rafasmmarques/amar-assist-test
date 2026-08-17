# Amar Assist Test

Sistema simples de cobrancas em monorepo, planejado para backend Laravel 9, frontend Vue 3, MySQL, Redis, filas Redis, Horizon e Docker Compose.

## Estado atual

Este repositorio esta na Fase 5 do plano de implementacao: Docker Compose.

O backend Laravel 9 foi criado em `backend/` com os pacotes Sanctum e Horizon instalados em versoes compativeis. O frontend Vue 3 com Vite foi criado em `frontend/`. O ambiente Docker Compose sobe backend PHP-FPM, Nginx, frontend Vite, MySQL, Redis, worker de filas e Horizon. Ainda nao ha configuracao SPA do Sanctum, regras funcionais, rotas de dominio, migrations de dominio, telas funcionais ou regras de autorizacao.

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

## Documentacao

- `AGENTS.md`: instrucoes operacionais do projeto para agentes.
- `docs/implementation-plan.md`: plano de implementacao faseado.
- `.codex/agents/`: definicoes dos subagentes planejados.

## Proximos passos

Seguir `docs/implementation-plan.md` fase a fase, sem antecipar scaffolds ou regras funcionais antes das especificacoes previstas.
