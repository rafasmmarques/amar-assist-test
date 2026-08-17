# Especificacao da API

## Escopo

A API sera fornecida pelo backend Laravel 9 e consumida pelo frontend Vue 3 por um cliente HTTP centralizado. O prefixo previsto e `/api`, exceto a rota padrao do Sanctum para CSRF quando aplicavel.

## Autenticacao e sessao

Rotas publicas minimas:

- `GET /sanctum/csrf-cookie`
- `POST /login`

Rotas autenticadas de sessao:

- `POST /logout`
- `GET /me`

Todas as demais rotas funcionais exigem autenticacao.

Mensagens de login nao devem revelar se o usuario existe.

## Clientes

- `GET /clients?page=&per_page=&name=&status=&document=`
- `POST /clients`
- `GET /clients/{client}`
- `PUT/PATCH /clients/{client}`
- `PATCH /clients/{client}/activate`
- `PATCH /clients/{client}/deactivate`

Regras de contrato:

- Filtros devem ser validados.
- `status` aceita somente `active` ou `inactive`.
- `document` pode ser recebido com ou sem mascara.
- Responses de cliente devem retornar `document` normalizado somente com digitos e `document_type`; formato mascarado, se necessario no frontend, deve ser derivado na UI.
- Desativacao deve falhar quando houver qualquer contrato associado ao cliente.
- Atualizacao deve falhar se tentar alterar `document_type` de cliente com contrato associado.

## Contratos

- `GET /clients/{client}/contracts`
- `POST /clients/{client}/contracts`
- `GET /contracts/{contract}`

Regras de contrato:

- `person_type` aceita somente `PF` ou `PJ`.
- CPF do cliente exige `PF`; CNPJ exige `PJ`.
- Cliente inativo nao pode receber novo contrato.
- `billing_cycle_day` deve estar entre 1 e 31.
- O endpoint de encerramento de contrato nao faz parte da superficie confirmada da API nesta fase. A decisao sobre `PATCH /contracts/{contract}/end` fica pendente e deve ser registrada antes de qualquer implementacao.

## Cobrancas

- `GET /charges?page=&per_page=&status=&payment_method=&client=&contract=&due_from=&due_to=`
- `GET /charges/{charge}`
- `POST /charges/generate`
- `POST /charges/batch-generate`
- `POST /charges/{charge}/pay`

Regras de contrato:

- `status` aceita somente `open` ou `paid`.
- `payment_method` aceita somente `boleto`, `card` ou `pix`.
- Listagem deve aplicar ordenacao de cobrancas antes de paginar.
- Detalhe de cobranca deve retornar total discriminado e, se paga, snapshot imutavel.
- Pagamento deve ser idempotente.
- Geracao deve ser idempotente por `contract_id + billing_period`.

### Geracao de cobrancas

`POST /charges/generate` cria uma cobranca simulada para um contrato e competencia. A primeira geracao deve retornar `201 Created`; repeticoes idempotentes com os mesmos dados podem retornar `200 OK`.

Payload minimo:

```json
{
  "contract_id": 1,
  "billing_period": "2026-08",
  "payment_method": "boleto",
  "original_amount": "100.00",
  "fixed_fee_amount": "5.00"
}
```

Regras:

- `contract_id` e obrigatorio, inteiro, deve apontar para contrato existente e apto a gerar cobranca.
- `billing_period` e obrigatorio no formato `YYYY-MM` e deve ser persistido como data no primeiro dia do mes.
- `payment_method` e obrigatorio e aceita somente `boleto`, `pix` ou `card`.
- `original_amount` e obrigatorio, decimal positivo, enviado e tratado como string decimal, nunca `float`.
- `fixed_fee_amount` e opcional, decimal maior ou igual a zero, enviado e tratado como string decimal, com default `0.00`.
- Valores monetarios devem ser normalizados antes de persistir.
- `due_date` nao deve ser aceito no payload; deve ser calculado no backend com `billing_period` e `billing_cycle_day` do contrato, respeitando `America/Sao_Paulo`, ultimo dia valido do mes e anos bissextos.
- Status inicial deve ser `open`.
- Juros nao devem vir no payload nem ser persistidos na geracao.
- Detalhes simulados devem ser gerados no backend conforme o metodo: boleto com codigo de barras simulado; Pix com chave ou identificador simulado; cartao com referencia tokenizada simulada, bandeira e `last4` ficticios seguros.
- PAN completo, CVV e dados sensiveis de cartao nao devem ser aceitos, armazenados ou retornados.
- Idempotencia e garantida por `contract_id + billing_period`: repeticao com os mesmos dados retorna a cobranca existente sem duplicacao; se `payment_method`, `original_amount` ou `fixed_fee_amount` forem diferentes, retornar `409 Conflict`.
- A geracao deve usar transacao, preservar a restricao unica de `contract_id + billing_period` e tratar concorrencia.
- Resposta minima:

```json
{
  "message": "Cobranca gerada com sucesso.",
  "data": {
    "status": "open",
    "billing_period": "2026-08-01",
    "payment_method": "boleto",
    "original_amount": "100.00",
    "fixed_fee_amount": "5.00",
    "due_date": "2026-08-10"
  }
}
```

### Geracao em lote de cobrancas

`POST /charges/batch-generate` valida um lote de cobrancas e envia o processamento para fila Redis. O endpoint exige autenticacao, nao cria cobrancas de forma sincrona e retorna `202 Accepted`.

Payload minimo:

```json
{
  "billing_period": "2026-08",
  "items": [
    {
      "contract_id": 1,
      "payment_method": "boleto",
      "original_amount": "100.00",
      "fixed_fee_amount": "5.00"
    },
    {
      "contract_id": 2,
      "payment_method": "pix",
      "original_amount": "150.00",
      "fixed_fee_amount": "0.00"
    }
  ]
}
```

Regras:

- `billing_period` e obrigatorio no formato `YYYY-MM`.
- `items` e obrigatorio e deve conter entre 1 e 100 itens.
- `items.*.contract_id` e obrigatorio, deve existir e nao pode se repetir no mesmo lote.
- `items.*.payment_method` aceita somente `boleto`, `pix` ou `card`.
- `items.*.original_amount` e obrigatorio, decimal positivo e enviado como string.
- `items.*.fixed_fee_amount` e opcional, decimal maior ou igual a zero, enviado como string, com default `0.00`.
- O payload nao aceita `due_date`, juros, status, PAN completo, CVV ou dados sensiveis.
- Todo payload deve ser validado antes do dispatch; payload invalido nao deve gerar Job.
- A resposta inclui `batch_id` UUID apenas como correlacao para logs e diagnostico; nao deve ser criada tabela de acompanhamento de lote nesta fase.
- O processamento do lote deve reutilizar a mesma regra de geracao individual e nao duplicar logica financeira.
- Item identico ja existente deve ser reutilizado sem erro.
- Cobranca da mesma competencia com dados divergentes deve registrar conflito daquele item sem duplicar cobranca.
- O lote deve continuar os demais itens e registrar resumo final seguro nos logs.

Resposta aceita:

```json
{
  "message": "Geracao de cobrancas enviada para processamento.",
  "batch_id": "00000000-0000-0000-0000-000000000000",
  "queued_items": 2
}
```

### Pagamento de cobranca

`POST /charges/{charge}/pay` executa pagamento simulado e deve retornar `200 OK`.

Payload minimo:

```json
{}
```

Header opcional:

```text
Idempotency-Key: chave-unica-ate-120-caracteres
```

Regras:

- A API nao deve aceitar valores monetarios no payload de pagamento.
- A data/hora do pagamento deve vir do relogio do servidor em `America/Sao_Paulo`, controlavel em testes.
- Se a cobranca estiver aberta, a operacao calcula encargos, persiste snapshot, marca como paga e retorna o resultado.
- Se a cobranca ja estiver paga, a operacao deve retornar `200 OK` com o mesmo snapshot persistido, sem recalcular valores.
- Se `Idempotency-Key` for usado, deve respeitar unicidade e retornar resultado consistente em repeticoes.
- Erros de autenticacao, autorizacao e validacao devem seguir o contrato JSON padrao.

Resposta minima:

```json
{
  "message": "Cobranca paga com sucesso.",
  "data": {
    "status": "paid",
    "paid_snapshot": {
      "paid_original_amount": "100.00",
      "paid_fixed_fee_amount": "5.00",
      "paid_late_interest_amount": "3.00",
      "paid_total_amount": "108.00",
      "paid_at": "2026-08-16T10:30:00-03:00"
    }
  }
}
```

### Totais de cobranca

Para cobranca aberta, responses devem retornar bloco de totais calculado com a data de referencia da consulta:

```json
{
  "amounts": {
    "original_amount": "100.00",
    "fixed_fee_amount": "5.00",
    "late_interest_amount": "3.00",
    "total_amount": "108.00",
    "days_late": 3,
    "reference_date": "2026-08-16"
  }
}
```

Para cobranca paga, responses devem retornar snapshot persistido e nao recalcular pela data atual:

```json
{
  "paid_snapshot": {
    "paid_original_amount": "100.00",
    "paid_fixed_fee_amount": "5.00",
    "paid_late_interest_amount": "3.00",
    "paid_total_amount": "108.00",
    "paid_at": "2026-08-16T10:30:00-03:00"
  }
}
```

Valores monetarios devem ser strings decimais com duas casas para preservar contrato frontend/API.

### Serializacao de pagamento

Listagens de cobrancas devem retornar no maximo `payment_method` e um resumo seguro, sem instrucoes completas de pagamento.

Detalhes de cobranca podem retornar `payment_details` conforme o meio:

```json
{
  "payment_details": {
    "method": "card",
    "card_brand": "visa",
    "card_last4": "1234",
    "card_masked": "**** **** **** 1234"
  }
}
```

Regras de seguranca da serializacao:

- `card_reference`, PAN completo e CVV nunca devem sair em responses.
- `pix_transaction_id` e identificador interno e nao deve sair em responses.
- `pix_key` nao deve sair em listagens; em detalhe, retornar somente `pix_key_masked`.
- `boleto_barcode` nao deve sair em listagens; em detalhe, retornar somente quando necessario para a instrucao de pagamento autenticada.
- Campos de pagamento nao previstos aqui devem ser omitidos por padrao.

## Paginacao e filtros

- `per_page` deve ter limite minimo e maximo, inicialmente 1 a 100.
- Valores desconhecidos em filtros devem gerar erro de validacao.
- Paginacao deve retornar envelope unico para todas as listagens:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 0,
    "last_page": 1
  },
  "links": {
    "first": null,
    "last": null,
    "prev": null,
    "next": null
  }
}
```

- Ordenacao de cobrancas deve ocorrer antes de `paginate()`.

## Erros

Erros devem ser JSON, em PT-BR, sem vazar detalhes internos.

Formato inicial previsto:

```json
{
  "message": "Mensagem resumida em PT-BR.",
  "errors": {
    "campo": ["Mensagem de validacao em PT-BR."]
  }
}
```

Para erros nao relacionados a validacao, `errors` pode ser omitido.

## Autorizacao

- Rotas funcionais devem usar autenticacao.
- Policies devem proteger recursos quando houver acesso a entidades.
- Horizon deve ficar protegido por autenticacao/autorizacao quando configurado.

## Sanctum e throttle

- Sanctum SPA deve configurar CSRF, cookies e dominios stateful por ambiente local/Docker.
- Cookies de sessao devem respeitar configuracao segura por ambiente.
- `POST /login` deve limitar no maximo 5 tentativas por minuto por combinacao de email normalizado e IP, retornando `429` quando excedido.
- `POST /charges/generate`, `POST /charges/batch-generate` e `POST /charges/{charge}/pay` devem limitar no maximo 10 requisicoes por minuto por usuario autenticado e rota, retornando `429` quando excedido.

## Compatibilidade

- Dependencias futuras devem permanecer compativeis com Laravel 9.
- Sanctum e Horizon devem ser fixados em linhas compativeis com Laravel 9.
- A API nao deve depender de upgrades de framework para cumprir o teste.
