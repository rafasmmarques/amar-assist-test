# Especificacao de seguranca

## Principios

- Nunca versionar credenciais, `.env` real, dumps ou dados sensiveis.
- Nunca expor segredos em diagnosticos, logs, respostas de API ou documentacao.
- Manter mensagens, interface, documentacao, commits e PRs em PT-BR.
- Usar Laravel 9 como requisito obrigatorio, sem sugerir upgrade como alternativa.

## Autenticacao

- Usar Sanctum SPA com CSRF e cookies configurados por ambiente.
- Apenas `GET /sanctum/csrf-cookie` e `POST /login` sao publicas no fluxo minimo.
- Rotas funcionais devem exigir autenticacao.
- `POST /logout` e `GET /me` exigem sessao autenticada.
- Mensagens de login devem ser genericas e nao revelar se o usuario existe.

## Autorizacao

- Policies devem proteger rotas funcionais e acesso a recursos.
- Horizon deve ser protegido por autenticacao/autorizacao desde sua configuracao.
- Falhas de autorizacao devem retornar erro JSON em PT-BR sem expor detalhes internos.

## Dados sensiveis

- CPF/CNPJ devem ser normalizados e validados.
- Nao armazenar PAN completo, CVV ou dados desnecessarios de cartao.
- Para cartao, persistir somente referencia tokenizada, bandeira e `last4` quando necessario.
- `card_reference` nunca deve sair em responses de API.
- Responses podem exibir apenas `card_brand`, `card_last4` e cartao mascarado derivado.
- `pix_transaction_id` e identificador interno e nao deve sair em responses.
- `pix_key` deve ser omitida de listagens e mascarada em detalhes.
- `boleto_barcode` deve ser omitido de listagens e retornado em detalhe somente quando necessario para instrucao de pagamento autenticada.
- Falhas de Jobs e logs de aplicacao nao devem conter dados sensiveis.

## Escritas criticas

- Pagamento deve ser transacional, usar bloqueio da cobranca para atualizacao e ser idempotente.
- Geracao de cobrancas deve ser transacional, idempotente e protegida por restricao unica.
- Desativacao de cliente deve ser transacional e verificar contratos no banco no momento da mudanca.
- Valores monetarios devem usar decimal seguro e nunca `float`.

## Rate limiting e exposicao

- Login e endpoints sensiveis devem ter rate limiting adequado ao Laravel 9.
- `POST /login` deve ter throttle verificavel por teste de feature.
- `POST /login` deve limitar no maximo 5 tentativas por minuto por email normalizado e IP, com rejeicao `429`.
- `POST /charges/generate` e `POST /charges/{charge}/pay` devem limitar no maximo 10 requisicoes por minuto por usuario autenticado e rota, com rejeicao `429`.
- Erros devem evitar stack traces, SQL, nomes de tabelas internos ou segredos.
- CORS e dominios stateful do Sanctum devem ser configurados conforme ambiente Docker/local quando essa fase existir.
- Configuracao do Sanctum SPA deve cobrir CSRF, cookies e `stateful` domains em ambiente local e Docker.

## Cache e filas

- Cache deve armazenar apenas resumo operacional nao sensivel.
- Chaves de cache nao devem expor dados pessoais ou segredos.
- Filas Redis devem registrar falhas sem dados sensiveis.
- Horizon nao pode ficar publico.
