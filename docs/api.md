# API

Prefixo: `/api`

As rotas funcionais exigem sessao autenticada via Sanctum SPA. Use `GET /sanctum/csrf-cookie` antes do login quando consumir pela SPA.

## Sessao

### `POST /api/login`

Payload:

```json
{
  "email": "usuario@example.com",
  "password": "senha-segura"
}
```

Resposta: usuario autenticado.

### `GET /api/user`

Retorna o usuario autenticado.

### `POST /api/logout`

Encerra a sessao autenticada.

## Clientes

- `GET /api/clients?page=&per_page=&name=&status=&document=`
- `POST /api/clients`
- `GET /api/clients/{client}`
- `PUT/PATCH /api/clients/{client}`
- `PATCH /api/clients/{client}/activate`
- `PATCH /api/clients/{client}/deactivate`

CPF/CNPJ podem ser enviados com mascara, mas sao normalizados para digitos. Cliente com qualquer contrato associado nao pode ser desativado.

## Contratos

- `GET /api/clients/{client}/contracts`
- `POST /api/clients/{client}/contracts`
- `GET /api/contracts/{contract}`

`person_type` deve ser `PF` para cliente CPF e `PJ` para cliente CNPJ. `billing_cycle_day` aceita 1 a 31.

## Cobrancas

- `GET /api/charges?page=&per_page=&status=&payment_method=&client=&contract=&due_from=&due_to=`
- `GET /api/charges/{charge}`
- `POST /api/charges/generate`
- `POST /api/charges/batch-generate`
- `POST /api/charges/{charge}/pay`

Listagens retornam cobrancas abertas vencidas antes de abertas nao vencidas e pagas. O resumo em cache aparece em `meta.summary` e contem apenas contagens agregadas.

### Geracao individual

`POST /api/charges/generate` e sincrono.

Payload:

```json
{
  "contract_id": 1,
  "billing_period": "2026-08",
  "payment_method": "boleto",
  "original_amount": "100.00",
  "fixed_fee_amount": "5.00"
}
```

Regras principais:

- `billing_period` usa `YYYY-MM`;
- `payment_method` aceita `boleto`, `pix` ou `card`;
- valores monetarios devem ser strings decimais;
- `fixed_fee_amount` e opcional e assume `0.00`;
- `due_date`, juros e status sao calculados no backend;
- repeticao identica retorna a cobranca existente;
- dados divergentes para mesmo contrato e competencia retornam `409 Conflict`.

### Geracao em lote

`POST /api/charges/batch-generate` valida o lote e despacha processamento assincrono na fila Redis `charges`.

Payload:

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

Resposta `202 Accepted`:

```json
{
  "message": "Geracao de cobrancas enviada para processamento.",
  "batch_id": "00000000-0000-0000-0000-000000000000",
  "queued_items": 2
}
```

O lote aceita de 1 a 100 itens. `contract_id` nao pode se repetir no mesmo lote. Payload invalido nao despacha Job e nao cria cobrancas sincronamente.

### Pagamento

`POST /api/charges/{charge}/pay`

Payload valido:

```json
{}
```

Header opcional:

```text
Idempotency-Key: chave-unica-da-operacao
```

O pagamento calcula os valores na data do servidor, persiste snapshot imutavel e retorna o mesmo resultado em repeticoes idempotentes.

## Seguranca

- Login tem rate limiting por email normalizado e IP.
- Escritas sensiveis de cobranca tem rate limiting por usuario e rota.
- Horizon exige autenticacao/autorizacao.
- Respostas e logs nao devem expor PAN completo, CVV, `card_reference` ou `pix_transaction_id`.
