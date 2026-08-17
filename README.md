# Amar Assist Test

Sistema simples de cobrancas em monorepo, com backend Laravel 9, frontend Vue 3, MySQL, Redis, filas Redis, Horizon e Docker Compose.

Laravel 9 esta fora do suporte atual, mas permanece como requisito obrigatorio deste teste. Nao atualizar para Laravel 10+ sem mudanca explicita do requisito.

## Estado atual

O projeto concluiu as fases funcionais previstas no plano:

- backend Laravel 9 em `backend/`, com Sanctum SPA, Horizon, PHPUnit e Pint;
- frontend Vue 3 + Vite em `frontend/`, com telas de login, clientes e cobrancas;
- Docker Compose com PHP-FPM, Nginx, frontend Vite, MySQL, Redis, worker de filas e Horizon;
- APIs autenticadas para clientes, contratos e cobrancas;
- geracao individual sincrona de cobrancas;
- geracao em lote assincrona por Job Redis na fila `charges`;
- cache pequeno de resumo operacional de cobrancas;
- Horizon protegido por autenticacao/autorizacao.

## Estrutura

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

## Requisitos locais

- WSL2 com o projeto em `/home/rafaelmarques/projects/amar-assist-test`.
- Docker Desktop integrado ao WSL.
- Executar comandos no shell Linux do WSL, nunca por PowerShell, CMD ou caminhos em `/mnt/c`.

## Ambiente

Subir os servicos:

```bash
docker compose up -d
```

Servicos e portas:

- API Laravel via Nginx: `http://localhost:8080`
- Frontend Vite: `http://localhost:5173`
- MySQL: `localhost:3307`
- Redis: `localhost:6379`
- Horizon: `http://localhost:8080/horizon`

O seeder atual cria dados de dominio para cliente, contrato e cobranca. Ele nao cria usuario demonstrativo; crie um usuario local apenas em ambiente de desenvolvimento quando precisar testar login manualmente.

## Configuracao

Arquivos de exemplo:

- `/backend/.env.example`
- `/frontend/.env.example`

Nao versionar `.env` real, dumps, tokens ou segredos. No Docker Compose local, os valores sao apenas demonstrativos para ambiente de desenvolvimento.

## Comandos uteis

Instalar dependencias dentro dos containers:

```bash
docker compose exec backend composer install
docker compose exec frontend npm install
```

Preparar banco local:

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

Rodar testes backend com MySQL temporario:

```bash
docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS amar_assist_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON amar_assist_testing.* TO 'amar'@'%'; FLUSH PRIVILEGES;"
docker compose exec -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 -e DB_DATABASE=amar_assist_testing -e DB_USERNAME=amar -e DB_PASSWORD=amar backend php artisan test
```

Rodar build frontend:

```bash
docker compose exec frontend npm run build
```

Verificar Horizon:

```bash
docker compose exec backend php artisan horizon:status
```

## API

Documentacao resumida:

- `/docs/api.md`

Specs versionadas:

- `/docs/specs/domain.md`
- `/docs/specs/api.md`
- `/docs/specs/security.md`
- `/docs/specs/acceptance.md`
- `/docs/specs/traceability.md`

## Decisoes e limitacoes

- Valores monetarios sao tratados como strings decimais no contrato HTTP e nunca como `float`.
- Datas de cobranca usam `America/Sao_Paulo`.
- Geracao e pagamento de cobrancas sao idempotentes e transacionais.
- PAN completo, CVV e dados sensiveis de cartao nao sao aceitos, persistidos, retornados ou logados.
- O endpoint individual `POST /api/charges/generate` e sincrono.
- O endpoint em lote `POST /api/charges/batch-generate` apenas valida e despacha um Job Redis.
- O `batch_id` e somente correlacao de logs; nao ha tabela de acompanhamento de lote nesta fase.
