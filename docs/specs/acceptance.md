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
- Cliente inativo nao recebe novo contrato.
- Cliente com contrato associado nao altera `document_type`.
- Contrato preserva `person_type` com `PF` ou `PJ`.
- CPF exige contrato `PF`; CNPJ exige contrato `PJ`.
- Vencimento mensal usa o dia configurado ou o ultimo dia valido do mes.
- Cobranca vencendo na data de referencia nao esta atrasada.
- Encargos seguem a formula documentada, com multa fora da base dos juros.
- Cobranca paga retorna snapshot imutavel com `paid_original_amount`, `paid_fixed_fee_amount`, `paid_late_interest_amount`, `paid_total_amount` e `paid_at`.
- Cobranca aberta retorna total discriminado com original, multa fixa, juros, total, dias de atraso e data de referencia.
- Pagamento e geracao de cobrancas sao idempotentes e transacionais.
- `POST /charges/generate` recebe `contract_id`, `billing_period` no formato `YYYY-MM`, `payment_method`, `original_amount` e `fixed_fee_amount` opcional como strings decimais.
- Geracao nao usa valores financeiros fixos no codigo e normaliza valores monetarios antes de persistir.
- Geracao calcula `due_date` no backend pelo ciclo do contrato, sem aceitar vencimento no payload.
- Geracao cria detalhes simulados por metodo sem armazenar PAN completo, CVV ou dados sensiveis de cartao.
- Repeticao idempotente com os mesmos dados retorna a cobranca existente sem duplicar; repeticao com dados financeiros ou metodo diferentes retorna `409 Conflict`.
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
- `POST /charges/generate` retorna `201 Created` na primeira geracao, pode retornar `200 OK` em repeticao idempotente, recebe `billing_period` em `YYYY-MM` e nao aceita `due_date`.
- `POST /charges/batch-generate` exige autenticacao, valida todo o payload, retorna `202 Accepted` com `batch_id` e `queued_items`, despacha Job Redis e nao cria cobrancas sincronamente.
- `billing_period` e persistido como data no primeiro dia do mes.
- `POST /charges/{charge}/pay` aceita payload `{}` valido, retorna `200 OK`, nao aceita valores monetarios no payload, usa relogio do servidor em `America/Sao_Paulo` controlavel em testes, persiste snapshot e retorna o mesmo snapshot em repeticoes.
- `POST /charges/{charge}/pay` aceita header opcional `Idempotency-Key` com ate 120 caracteres e deve retornar resultado consistente em repeticoes da mesma chave.
- Resposta de sucesso de `POST /charges/{charge}/pay` deve preservar o envelope minimo documentado em `api.md`, incluindo `message`, `data.status = paid` e `data.paid_snapshot`.

## Seguranca

- Sanctum SPA usa CSRF e cookies por ambiente.
- Sanctum SPA tem dominios stateful configurados para local/Docker quando essa fase existir.
- `POST /login` limita no maximo 5 tentativas por minuto por email normalizado e IP e retorna `429` ao exceder.
- `POST /charges/generate`, `POST /charges/batch-generate` e `POST /charges/{charge}/pay` limitam no maximo 10 requisicoes por minuto por usuario autenticado e rota e retornam `429` ao exceder.
- Login nao revela se o usuario existe.
- Policies protegem rotas funcionais quando aplicavel.
- Horizon fica protegido por autenticacao/autorizacao.
- PAN completo, CVV, `.env` real, dumps e segredos nao sao armazenados nem versionados.
- Logs e falhas de Jobs nao expõem dados sensiveis.

## Filas, Redis, cache e Horizon

- Existe caso concreto de fila: `POST /charges/batch-generate`.
- Redis e o driver planejado para filas.
- Job usa conexao Redis, fila `charges`, e e idempotente por competencia e contrato.
- Job define `tries` e `backoff`, reutiliza a regra de geracao individual e continua demais itens quando houver conflito isolado.
- Cache e limitado a resumo operacional nao sensivel.
- Cache tem TTL inicial de 60 segundos, nao armazena estado transitorio de Job e invalida em criacao individual, pagamento e processamento de geracao em lote.

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
- `POST /charges/batch-generate` com payload valido, lote vazio, mais de 100 itens, contrato duplicado, metodo invalido, valores financeiros invalidos, payload sensivel rejeitado, ausencia de cobranca criada na requisicao e dispatch do Job;
- ausencia de PAN e CVV em banco, logs e respostas.
- ausencia de `card_reference` e `pix_transaction_id` em respostas;
- throttle de login e protecao de escrita sensivel;
- configuracao Sanctum SPA para CSRF, cookies e stateful domains.
- Geracao em lote assincrona usa `POST /charges/batch-generate` e nao altera o endpoint individual `POST /charges/generate`.

Frontend futuro deve validar minimamente login, filtros, estados de tela e consumo do contrato da API.
