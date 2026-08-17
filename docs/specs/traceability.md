# Rastreabilidade

## Objetivo

Este documento liga requisitos do plano a especificacoes e validacoes futuras. Ele deve ser atualizado quando uma regra, contrato de API, risco de seguranca ou criterio de aceite mudar.

## Matriz

| ID | Requisito | Fonte no plano | Especificacao | Validacao futura |
| --- | --- | --- | --- | --- |
| R01 | Monorepo com backend, frontend, docs, agentes e Docker futuro | Secao 2 | `acceptance.md` | Estrutura e revisao por fase |
| R02 | Laravel 9 obrigatorio, sem upgrade sugerido | Secoes 2 e 4 | `api.md`, `security.md` | `php artisan --version`, constraints Composer |
| R03 | Vue 3, Vite e Composition API | Secoes 2 e 10 | `acceptance.md` | Build frontend futuro |
| R04 | Sanctum SPA com CSRF e cookies | Secoes 2, 8 e 9 | `api.md`, `security.md` | Feature tests de auth |
| R05 | Rotas funcionais autenticadas | Secao 8 | `api.md`, `security.md` | Feature tests de autorizacao |
| R06 | Clientes com CPF/CNPJ normalizados e unicos | Secoes 6 e 7 | `domain.md`, `acceptance.md` | PHPUnit de validacao e unicidade |
| R07 | Cliente com qualquer contrato nao pode ser desativado | Secoes 3 e 7 | `domain.md`, `acceptance.md` | PHPUnit transacional de desativacao |
| R08 | Contrato preserva `person_type` PF/PJ coerente com documento | Secoes 3, 6 e 7 | `domain.md`, `acceptance.md` | PHPUnit de coerencia CPF/PF e CNPJ/PJ |
| R09 | Vencimento mensal ajusta ultimo dia valido | Secao 7 | `domain.md`, `acceptance.md` | PHPUnit 28/29/30/31 dias e ano bissexto |
| R10 | Cobranca usa `billing_period` mensal, valores monetarios enviados como strings e unicidade por contrato/competencia | Secoes 6 e 7 | `domain.md`, `api.md`, `acceptance.md` | PHPUnit de geracao idempotente, conflito e concorrencia |
| R11 | Encargos usam formula obrigatoria, sem `float` | Secoes 7 e 14 | `domain.md`, `security.md` | PHPUnit de juros, multa, arredondamento |
| R12 | Cobranca vencendo hoje nao esta atrasada | Secoes 3 e 7 | `domain.md`, `acceptance.md` | PHPUnit com relogio controlado |
| R13 | Pagamento idempotente com snapshot financeiro imutavel | Secoes 6 e 7 | `domain.md`, `api.md`, `security.md`, `acceptance.md` | PHPUnit de pagamento repetido, concorrente e snapshot com campos persistidos |
| R14 | Ordenacao de cobrancas antes da paginacao | Secoes 7 e 14 | `domain.md`, `api.md` | PHPUnit de ordenacao e paginacao |
| R15 | Detalhes de pagamento em tabela 1:1 sem PAN/CVV e com serializacao segura | Secoes 5, 6 e 8 | `domain.md`, `api.md`, `security.md`, `acceptance.md` | Testes de banco, logs e respostas sem dados proibidos |
| R16 | Erros JSON em PT-BR sem detalhes internos | Secao 8 | `api.md`, `security.md` | Feature tests de validacao e erro |
| R17 | `POST /charges/generate` cria cobranca simulada por contrato/competencia sem valores financeiros fixos no codigo | Secao 9 | `domain.md`, `api.md`, `acceptance.md` | Feature tests de payload, vencimento, detalhes simulados, idempotencia e conflito |
| R18 | Horizon protegido | Secoes 2 e 12 | `security.md`, `acceptance.md` | Feature test de acesso ao Horizon |
| R19 | Cache operacional nao sensivel com TTL e invalidacao | Secao 12 | `domain.md`, `security.md`, `acceptance.md` | Testes de cache e invalidacao |
| R20 | Frontend com login, clientes e cobrancas | Secao 10 | `api.md`, `acceptance.md` | Build e validacao minima de UI |
| R21 | Sanctum SPA, throttle de login e protecao de escritas sensiveis | Secoes 8, 9 e 18 | `api.md`, `security.md`, `acceptance.md` | Feature tests de throttle e configuracao local/Docker |
| R22 | Envelope de paginacao e formato normalizado de documento | Secoes 7, 8 e 10 | `api.md`, `acceptance.md` | Feature tests de listagens e contrato frontend/API |
| R23 | Contrato HTTP de pagamento simulado idempotente | Secoes 7, 8 e 14 | `domain.md`, `api.md`, `acceptance.md` | Feature tests de payload `{}`, rejeicao de valores monetarios, `Idempotency-Key` ate 120 caracteres, relogio controlado, envelope minimo, repeticao e snapshot |
| R24 | Geracao em lote futura por Job Redis exige contrato proprio, idempotencia, `tries` e `backoff` | Secao 12 | `domain.md`, `acceptance.md` | Teste do Job e inspecao de configuracao quando o contrato existir |

## Decisoes rastreadas

- A implementacao nao deve forcar exatamente quatro tabelas quando auth, idempotencia e normalizacao exigirem mais.
- Pagamento simulado sera incluido porque snapshot e idempotencia dependem disso.
- A regra inicial de desativacao considera qualquer contrato associado, nao apenas contrato ativo.
- `person_type` permanece no contrato por requisito explicito.
- `billing_period` e obrigatoria para garantir geracao idempotente.
- A tabela 1:1 `charge_payment_details` e a escolha minima para detalhes de pagamento.
- Cache de cobrancas deve ser pequeno, nao sensivel e invalidado explicitamente.
- Endpoint de encerramento de contrato (`PATCH /contracts/{contract}/end`) permanece decisao pendente e nao integra a superficie confirmada da API ate que uma fase futura registre a regra de negocio e os criterios de aceite.
- `POST /charges/generate` recebe `contract_id`, `billing_period` em `YYYY-MM`, `payment_method`, `original_amount` e `fixed_fee_amount` opcional; nao aceita `due_date`, nao usa valores financeiros fixos no codigo, gera detalhes simulados no backend e resolve idempotencia por `contract_id + billing_period`.
