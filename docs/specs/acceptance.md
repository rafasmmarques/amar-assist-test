# Criterios de aceite

## Fase 2

- `docs/specs/domain.md`, `docs/specs/api.md`, `docs/specs/security.md`, `docs/specs/acceptance.md` e `docs/specs/traceability.md` existem.
- As especificacoes cobrem clientes, contratos, cobrancas, autenticacao, seguranca, filas e cache.
- Nenhum codigo funcional, scaffold Laravel, scaffold Vue, Docker, Sanctum, Redis ou Horizon e criado nesta fase.
- A revisao manual contra `docs/implementation-plan.md` nao encontra lacuna bloqueante.

## Dominio

- CPF/CNPJ aceitos com ou sem mascara e salvos somente com digitos.
- CPF/CNPJ validam tamanho e digitos verificadores.
- Documento de cliente e unico.
- Cliente com qualquer contrato associado nao pode ser desativado.
- Contrato preserva `person_type` com `PF` ou `PJ`.
- CPF exige contrato `PF`; CNPJ exige contrato `PJ`.
- Vencimento mensal usa o dia configurado ou o ultimo dia valido do mes.
- Cobranca vencendo na data de referencia nao esta atrasada.
- Encargos seguem a formula documentada, com multa fora da base dos juros.
- Cobranca paga retorna snapshot imutavel com `paid_original_amount`, `paid_fixed_fee_amount`, `paid_late_interest_amount`, `paid_total_amount` e `paid_at`.
- Cobranca aberta retorna total discriminado com original, multa fixa, juros, total, dias de atraso e data de referencia.
- Pagamento e geracao de cobrancas sao idempotentes e transacionais.
- Ordenacao de cobrancas ocorre antes da paginacao.

## API

- Rotas publicas minimas: CSRF e login.
- Rotas funcionais exigem autenticacao.
- Filtros rejeitam valores desconhecidos.
- `per_page` respeita limite minimo e maximo.
- Erros JSON ficam em PT-BR e nao vazam detalhes internos.
- Paginacao retorna envelope unico com `data`, `meta.current_page`, `meta.per_page`, `meta.total`, `meta.last_page`, `links.first`, `links.last`, `links.prev` e `links.next`.
- Responses de cliente retornam `document` normalizado somente com digitos e `document_type`.
- Responses de cobranca expõem valores monetarios como strings decimais de duas casas.
- Responses nao expõem `card_reference`, PAN completo, CVV nem `pix_transaction_id`.
- Listagens nao expõem `pix_key` nem `boleto_barcode`; detalhe expõe apenas os campos seguros definidos em `api.md`.
- `POST /charges/generate` retorna `202 Accepted`, enfileira Job em Redis e recebe `billing_period` no primeiro dia da competencia e `contract_ids` opcional.
- `billing_period` e persistido como data no primeiro dia do mes.
- `POST /charges/{charge}/pay` aceita payload `{}` valido, retorna `200 OK`, nao aceita valores monetarios no payload, usa relogio do servidor em `America/Sao_Paulo` controlavel em testes, persiste snapshot e retorna o mesmo snapshot em repeticoes.
- `POST /charges/{charge}/pay` aceita header opcional `Idempotency-Key` com ate 120 caracteres e deve retornar resultado consistente em repeticoes da mesma chave.
- Resposta de sucesso de `POST /charges/{charge}/pay` deve preservar o envelope minimo documentado em `api.md`, incluindo `message`, `data.status = paid` e `data.paid_snapshot`.

## Seguranca

- Sanctum SPA usa CSRF e cookies por ambiente.
- Sanctum SPA tem dominios stateful configurados para local/Docker quando essa fase existir.
- `POST /login` limita no maximo 5 tentativas por minuto por email normalizado e IP e retorna `429` ao exceder.
- `POST /charges/generate` e `POST /charges/{charge}/pay` limitam no maximo 10 requisicoes por minuto por usuario autenticado e rota e retornam `429` ao exceder.
- Login nao revela se o usuario existe.
- Policies protegem rotas funcionais quando aplicavel.
- Horizon fica protegido por autenticacao/autorizacao.
- PAN completo, CVV, `.env` real, dumps e segredos nao sao armazenados nem versionados.
- Logs e falhas de Jobs nao expõem dados sensiveis.

## Filas, Redis, cache e Horizon

- Existe caso concreto de fila: geracao em lote de cobrancas por competencia.
- Redis e o driver planejado para filas.
- Job e idempotente por competencia e contrato.
- Job define `tries` e `backoff`.
- Cache e limitado a resumo operacional nao sensivel.
- Cache tem TTL inicial de 60 segundos e invalidacao em criacao, atualizacao, pagamento e geracao em lote.

## Testes futuros

Quando a implementacao existir, PHPUnit deve cobrir:

- autenticacao, logout e recuperacao de sessao;
- autorizacao das rotas funcionais e protecao do Horizon;
- filtros, limites de `per_page`, erros e paginacao;
- envelope de paginacao `data`, `meta` e `links`;
- formato de `document` normalizado nas respostas;
- CPF/CNPJ, unicidade e coerencia `PF/PJ`;
- bloqueio de desativacao de cliente com contrato;
- vencimentos em meses com 28, 29, 30 e 31 dias;
- ano bissexto e mudanca de mes/ano;
- atraso somente apos a data de vencimento;
- juros, multa fora da base e arredondamento;
- pagamento idempotente, snapshot e concorrencia;
- contrato HTTP de `POST /charges/{charge}/pay`, incluindo payload `{}`, rejeicao de valores monetarios, `Idempotency-Key` opcional com ate 120 caracteres, relogio controlado, envelope minimo de resposta e repeticao idempotente;
- geracao idempotente e concorrencia;
- ordenacao antes da paginacao;
- Job com `Queue::fake()` e regra executada pelo Job;
- ausencia de PAN e CVV em banco, logs e respostas.
- ausencia de `card_reference` e `pix_transaction_id` em respostas;
- throttle de login e protecao de escrita sensivel;
- configuracao Sanctum SPA para CSRF, cookies e stateful domains.
- `POST /charges/generate` assincrono com `202 Accepted` e Job Redis.
- `tries` e `backoff` do Job de geracao em lote.

Frontend futuro deve validar minimamente login, filtros, estados de tela e consumo do contrato da API.
