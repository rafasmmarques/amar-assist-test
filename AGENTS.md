# Amar Assist Test

Sistema simples de cobrancas em monorepo, com backend Laravel 9, frontend Vue 3 e ambiente Docker.

## Ambiente WSL

- O projeto fica no filesystem do WSL em `/home/rafaelmarques/projects/amar-assist-test`.
- Execute comandos no shell Linux atual; nao use PowerShell, CMD, Git Bash ou ferramentas Windows sobre caminhos do WSL.
- Antes da primeira acao de projeto, confirme `pwd` e `uname -s`; se for `Linux`, continue no WSL.
- Rode comandos na raiz do repositorio, salvo quando uma subpasta for necessaria.
- Nao alterne silenciosamente entre WSL, PowerShell e CMD para tentar corrigir falhas.
- Se um comando falhar, leia o erro e verifique o ambiente antes de repetir.
- Nunca exponha segredos, tokens, `.env` real, dumps ou dados sensiveis ao diagnosticar falhas.

## Stack oficial

- Backend: Laravel 9, PHP, PHPUnit, Sanctum SPA, Horizon.
- Frontend: Vue 3, Vite, Composition API.
- Banco, cache e filas: MySQL, Redis, filas Redis.
- Idioma de interface, mensagens, docs, commits e PRs: PT-BR.

## Estrutura prevista

```text
/backend
/frontend
/docs
/.codex/agents
/docker-compose.yml
/README.md
```

## Comandos previstos

- Bootstrap inicial sem containers: usar host WSL somente ate o Docker estar configurado.
- Subir ambiente: `docker compose up -d`.
- Depois do Docker configurado, preferir comandos dentro dos containers:
  - `docker compose exec backend composer ...`
  - `docker compose exec backend php artisan ...`
  - `docker compose exec frontend npm ...`
- Validacao futura: testes backend, build frontend e checagem manual dos fluxos obrigatorios.

## Arquitetura

- Monolito modular em monorepo; nao criar microservicos.
- Controllers finos; regras de negocio fora dos controllers.
- Use Form Requests, API Resources, Policies quando aplicavel e enums ou estruturas equivalentes no Laravel 9.
- Services ou Actions apenas para regras reais; Jobs apenas para o caso definido de geracao em lote de cobrancas.
- Evite repository pattern, CQRS, event sourcing, interfaces sem mais de uma implementacao real e abstracoes para um unico uso.
- Priorize clareza, simplicidade, SOLID pragmatico, Clean Code, YAGNI e Spec-Driven Development.

## Backend

- Valores monetarios nunca usam `float`; use decimal seguro e arredondamento half-up explicito.
- Datas devem usar timezone `America/Sao_Paulo` e relogio controlavel em testes.
- Geracao e pagamento de cobrancas devem ser idempotentes e transacionais.
- Validar e normalizar CPF/CNPJ; aplicar unicidade e indices adequados.
- Respostas da API devem manter contrato previsivel para filtros, paginacao e erros.

## Frontend

- Componentes Vue pequenos, com responsabilidade unica.
- Logica reutilizavel em composables.
- Comunicacao HTTP centralizada.
- Rotas protegidas, estados de carregamento, vazio e erro nas telas obrigatorias.
- Nao criar dashboards, graficos ou integracoes externas sem necessidade aprovada.

## Seguranca

- Nunca versionar credenciais, `.env` real, dumps ou dados sensiveis.
- Nao armazenar PAN completo, CVV ou dados desnecessarios de cartao.
- Usar Sanctum SPA com CSRF, cookies seguros por ambiente e rate limiting.
- Mensagens de login nao devem revelar se usuario existe.
- Horizon deve ficar protegido por autenticacao/autorizacao.

## Testes

- Priorize PHPUnit Feature e Unit para regras de dominio, auth, filtros, ordenacao, idempotencia e autorizacao.
- Use relogio controlado; nao depender da data real da maquina.
- Frontend deve ter validacao minima dos fluxos criticos quando a UI existir.

## Git

- Commits pequenos, coesos e com Conventional Commits em PT-BR.
- Nao fazer commit, push ou PR sem pedido explicito.
- Toda PR futura deve explicar o que muda, por que muda, riscos e como validar.

## Definicao de pronto

- Especificacao atualizada, testes relevantes criados ou ajustados, implementacao minima concluida, validacoes executadas, riscos registrados e documentacao sincronizada.
