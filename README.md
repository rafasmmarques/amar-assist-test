# Amar Assist Test

Sistema simples de cobrancas em monorepo, planejado para backend Laravel 9, frontend Vue 3, MySQL, Redis, filas Redis, Horizon e Docker Compose.

## Estado atual

Este repositorio esta na Fase 3 do plano de implementacao: scaffold do backend Laravel 9.

O backend Laravel 9 foi criado em `backend/` com os pacotes Sanctum e Horizon instalados em versoes compativeis. Ainda nao ha scaffold de Vue, Docker, configuracao SPA do Sanctum, configuracao do Horizon, regras funcionais, rotas de dominio, migrations de dominio ou integracoes de filas/cache configuradas.

Laravel 9 esta fora do suporte atual, mas permanece como requisito obrigatorio do teste. Nao deve ser atualizado para Laravel 10+ sem mudanca explicita do requisito.

## Estrutura inicial

```text
backend/
frontend/
docs/
.codex/agents/
AGENTS.md
README.md
```

## Documentacao

- `AGENTS.md`: instrucoes operacionais do projeto para agentes.
- `docs/implementation-plan.md`: plano de implementacao faseado.
- `.codex/agents/`: definicoes dos subagentes planejados.

## Proximos passos

Seguir `docs/implementation-plan.md` fase a fase, sem antecipar scaffolds ou regras funcionais antes das especificacoes previstas.
