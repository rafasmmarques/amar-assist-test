# Amar Assist Test

Sistema simples de cobranças em monorepo, com backend Laravel 9, frontend Vue 3, MySQL, Redis, filas Redis, Horizon e Docker Compose.

## Sobre o Projeto

O Amar Assist Test permite autenticação, cadastro de clientes, geração e pagamento de cobranças simuladas. A aplicação foi organizada em dois módulos principais:

- `backend/`: API Laravel 9 com Sanctum SPA, Horizon, PHPUnit e Pint.
- `frontend/`: interface Vue 3 + Vite com telas de login, clientes e cobranças.

O ambiente local usa Docker Compose para executar API, frontend, banco de dados, cache, filas e Horizon.

## Requisitos

- WSL2 com o projeto em `/home/rafaelmarques/projects/amar-assist-test`.
- Docker Desktop integrado ao WSL.
- Docker Compose disponível no shell Linux do WSL.

## Como Inicializar

Suba os serviços:

```bash
docker compose up -d
```

Instale as dependências, se ainda não estiverem instaladas:

```bash
docker compose exec backend composer install
docker compose exec frontend npm install
```

Prepare o banco de dados:

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

Acesse:

- Frontend: `http://localhost:5173`
- API: `http://localhost:8080`
- Horizon: `http://localhost:8080/horizon`

## Usuário de Teste

O seeder cria um usuário para acesso local:

- E-mail: `admin@example.com`
- Senha: `password`

## Validações

Rodar testes do backend:

```bash
docker compose exec backend php artisan test
```

Rodar build do frontend:

```bash
docker compose exec frontend npm run build
```
