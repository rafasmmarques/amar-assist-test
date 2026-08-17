# Especificacao de dominio

## Escopo

Esta especificacao define as regras de dominio para usuarios, clientes, contratos, cobrancas e detalhes do meio de pagamento. Nenhuma regra funcional deve ser implementada antes de estar rastreada neste documento e em `docs/specs/acceptance.md`.

## Entidades

### Usuarios

- Usuario autentica no sistema e acessa rotas funcionais protegidas.
- Campos planejados: `id`, `name`, `email`, `password`, timestamps.
- `email` deve ser unico.

### Clientes

- Cliente representa uma pessoa fisica ou juridica cobrada pelo sistema.
- Campos planejados: `id`, `name`, `document_type`, `document`, `address`, `contact`, `status`, timestamps.
- `document_type` aceita somente `cpf` ou `cnpj`.
- `document` deve ser salvo somente com digitos, aceitando entrada com ou sem mascara.
- CPF e CNPJ devem validar tamanho e digitos verificadores.
- `document` deve ser unico globalmente.
- `status` aceita somente `active` ou `inactive`.
- Cliente com qualquer contrato associado nao pode ser desativado.
- Desativacao deve ocorrer em transacao e verificar contratos no banco no momento da mudanca.

### Contratos

- Contrato pertence a um cliente.
- Campos planejados: `id`, `client_id`, `person_type`, `billing_cycle_day`, `status`, `started_at`, `ended_at`, timestamps.
- `person_type` aceita somente `PF` ou `PJ` e deve ser preservado por requisito do teste.
- Cliente com CPF exige contrato `PF`; cliente com CNPJ exige contrato `PJ`.
- `billing_cycle_day` deve estar entre 1 e 31.
- `status` aceita somente `active` ou `ended`.
- `ended_at` e obrigatorio quando `status = ended`.
- Calculo de vencimento mensal deve usar o dia configurado quando ele existir no mes; se nao existir, deve usar o ultimo dia valido do mes, incluindo ano bissexto.

### Cobrancas

- Cobranca pertence a um contrato.
- Campos planejados: `id`, `uuid`, `contract_id`, `billing_period`, `payment_method`, `original_amount`, `fixed_fee_amount`, `due_date`, `status`, snapshot de pagamento, `idempotency_key`, timestamps.
- `uuid` deve ser unico e usado como identificador publico quando aplicavel.
- `billing_period` representa competencia mensal e deve ser persistido como data no primeiro dia do mes.
- Deve existir unicidade por `contract_id` e `billing_period`.
- `payment_method` aceita somente `boleto`, `card` ou `pix`.
- `status` aceita somente `open` ou `paid`.
- Valores monetarios devem usar decimal seguro, arredondamento half-up explicito para duas casas e nunca `float`.
- Snapshot minimo persistido no pagamento: `paid_original_amount`, `paid_fixed_fee_amount`, `paid_late_interest_amount`, `paid_total_amount` e `paid_at`.
- Para cobranca aberta, totais de exibicao devem discriminar `original_amount`, `fixed_fee_amount`, `late_interest_amount`, `total_amount`, `days_late` e `reference_date`.
- Para cobranca paga, totais de exibicao devem usar somente o snapshot persistido: `paid_original_amount`, `paid_fixed_fee_amount`, `paid_late_interest_amount`, `paid_total_amount` e `paid_at`.

### Detalhes do meio de pagamento

- Detalhes especificos ficam em tabela 1:1 `charge_payment_details`.
- Campos planejados: `id`, `charge_id`, `boleto_barcode`, `pix_key`, `pix_transaction_id`, `card_reference`, `card_brand`, `card_last4`, timestamps.
- A tabela 1:1 e a solucao minima escolhida para manter colunas explicitas, evitar JSON sensivel e nao criar uma tabela por meio de pagamento.
- Nunca armazenar PAN completo, CVV ou dados desnecessarios de cartao.
- `card_reference` e identificador interno/tokenizado e nunca deve sair em resposta de API.
- Dados exibiveis de cartao devem ser limitados a `card_brand`, `card_last4` e valor mascarado derivado, por exemplo `**** **** **** 1234`.
- Chaves Pix e codigos de boleto sao dados de instrucao de pagamento e nao devem aparecer em listagens; respostas de detalhe devem retornar apenas campos explicitamente definidos em `docs/specs/api.md`.

## Encargos

Formula obrigatoria:

```text
dias_atraso = max(0, data_referencia - data_vencimento)
juros_atraso = valor_original x 0,01 x dias_atraso
total_atualizado = valor_original + multa_fixa + juros_atraso
```

- Multa fixa nao integra a base dos juros.
- Atraso existe somente quando `data_referencia > data_vencimento`.
- Cobranca vencendo na data de referencia nao esta atrasada.
- Datas de calculo usam datas civis no timezone `America/Sao_Paulo`, sem diferenca por horas.
- Cobranca paga nao deve recalcular total usando a data atual; deve retornar snapshot persistido no pagamento.

## Pagamento idempotente

Pagamento deve:

1. abrir uma transacao;
2. bloquear a cobranca para atualizacao;
3. verificar se ela ja foi paga;
4. calcular valores usando a data do pagamento;
5. persistir snapshot imutavel;
6. marcar a cobranca como paga;
7. retornar o mesmo resultado em chamadas repetidas.

## Geracao idempotente

- Toda cobranca deve ter `billing_period` mensal explicita.
- Geracao deve respeitar restricao unica `contract_id + billing_period`.
- `payment_method` nao pode ser usado para permitir duas faturas do mesmo contrato e competencia.
- `idempotency_key`, quando usada em endpoints de escrita, deve ter restricao unica.
- Geracao deve usar transacao e tratar concorrencia.

## Ordenacao de cobrancas

A ordenacao deve ocorrer no backend antes da paginacao:

1. abertas e vencidas;
2. abertas nao vencidas;
3. pagas;
4. dentro do grupo, vencimento crescente;
5. desempate por identificador crescente.

A implementacao deve usar expressao SQL com `CASE` ou equivalente antes de `paginate()`.

## Filas e cache

- O uso de filas e obrigatorio em um caso pequeno: geracao em lote de cobrancas por competencia.
- A fila deve usar Redis.
- O Job deve ser idempotente por competencia e contrato, respeitando `contract_id + billing_period`.
- O Job deve definir `tries` e `backoff`, e registrar falhas sem expor dados sensiveis.
- Cache planejado: resumo operacional nao sensivel de cobrancas por status do usuario autenticado.
- Chave planejada: `charges:summary:user:{user_id}:filters:{hash}`.
- TTL inicial: 60 segundos.
- Cache deve ser invalidado em criacao, atualizacao, pagamento e geracao em lote de cobrancas.
