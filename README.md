# Livraria Aslan

Loja virtual de livros em PHP 8 puro + MySQL, HTML/CSS/JS sem framework.
Roda na Hostinger: cada `git push` na branch `main` faz o deploy automático.

> ## ⚠️ Antes do primeiro acesso (sem isso o site dá erro 500)
> 1. **Criar o banco** no hPanel e **importar `schema.sql` e depois `seed.sql`** (passo 1 e 2 abaixo).
> 2. **Preencher `app/config.php`** com nome, usuário e senha do banco, a URL do site e a chave Pix (passo 3).
> 3. **Depois de testar, colocar `'debug' => false`** no `app/config.php`. Com debug ligado, qualquer erro mostra detalhes técnicos para quem estiver no site.

---

## 1. Criar o banco na Hostinger

1. hPanel > **Bancos de dados** > **Bancos de dados MySQL**.
2. Crie um banco e um usuário (a Hostinger coloca um prefixo, ex.: `u123456789_livraria`). Anote o nome do banco, o usuário e a senha.

## 2. Importar a estrutura e os dados de exemplo

1. Na mesma tela, clique em **Entrar no phpMyAdmin** do banco criado.
2. Aba **Importar** > escolha `database/schema.sql` > **Executar**.
3. Aba **Importar** de novo > escolha `database/seed.sql` > **Executar**.

O `schema.sql` apaga e recria as tabelas. Só importe de novo se quiser zerar a loja.

## 3. Configurar

Edite `app/config.php` (ele é versionado, o repositório é privado):

| Campo | O que colocar |
|---|---|
| `app_url` | Endereço do site, ex.: `https://livrariaaslan.com.br` (sem barra no final) |
| `base_path` | Deixe `''`: a loja detecta sozinha se está na raiz ou numa subpasta (ex.: `/livraria-aslan`) |
| `debug` | `true` só enquanto testa; `false` em produção |
| `db.host` | `localhost` (padrão da Hostinger) |
| `db.name` / `db.user` / `db.pass` | Os dados do passo 1 |
| `mail.from` | Um e-mail do seu domínio (crie em hPanel > E-mails). Usado na recuperação de senha |
| `payment.pix.key` | Sua chave Pix |
| `shop.*` | Frete fixo, frete grátis a partir de, aviso de estoque baixo |

Faça commit e push. A Hostinger publica sozinha.

## 4. Deploy (como está ligado)

- O repositório inteiro vai para dentro do `public_html`.
- O `.htaccess` da raiz manda tudo para a pasta `public/`. Por isso `app/`, `database/`, `storage/` e `.git` não abrem pelo navegador.
- Versão do PHP: **8.1 ou mais nova** (hPanel > Avançado > Configuração do PHP). Extensões necessárias: `pdo_mysql` (já vem ligada na Hostinger), `fileinfo` e `mbstring` (recomendadas).
- As imagens enviadas pelo painel ficam em `public/uploads/` só no servidor (não vão para o Git).

Para rodar localmente, se um dia precisar: `php -S localhost:8000 -t public` com um MySQL configurado no `app/config.php`.

## 5. Logins de teste

| Papel | E-mail | Senha |
|---|---|---|
| Admin | `admin@livraria.test` | `Admin@2026` |
| Cliente | `cliente@livraria.test` | `Cliente@2026` |

**Troque as duas senhas** depois do primeiro acesso (em **Perfil > Alterar senha**).

## 6. Endereços

**Loja:** `/` início · `/catalogo` · `/livro/{id}` · `/carrinho` · `/checkout` · `/pedido/{id}/confirmacao` · `/cadastro` · `/login` · `/recuperar-senha` · `/perfil` · `/pedidos` · `/pedidos/{id}`

**Painel:** `/admin` resumo · `/admin/livros` · `/admin/livros/novo` · `/admin/pedidos` · `/admin/dedicatorias` · `/admin/clientes` · `/admin/configuracoes`. Quem não está logado vai para `/admin/login`; cliente comum recebe 403.

## 7. Roteiro de teste na Hostinger

1. **Cadastro:** crie uma conta em `/cadastro`. Você já entra logado.
2. **Compra:** abra um livro, adicione ao carrinho, mude a quantidade, finalize no `/checkout` com endereço e Pix. Você vê a chave Pix e o pedido aparece como **Pendente** em `/pedidos`.
3. **Admin, livro:** entre em `/admin/login`, cadastre um livro com imagem JPG/PNG/WEBP de até 2 MB e confira se ele aparece no catálogo.
4. **Admin, status:** em `/admin/pedidos`, abra o pedido e mude para **Pago**, **Enviado** e **Entregue**. O cliente vê o andamento em `/pedidos/{id}`. Se cancelar, os livros voltam ao estoque.
5. **Recuperar senha:** com `debug => true` o link também aparece na tela, e no servidor ele fica em `storage/logs/mail.log`.

## Melhorias 1: frete grátis, dedicatória e NIF

> **Antes de testar:** o `database/melhorias_1.sql` já precisa estar importado (colunas `users.nif`, `books.allows_dedication`, `cart_items.dedication`, `order_items.dedication`, `orders.invoice_*` e a view `v_dedications_to_prepare`). Depois, em **Admin > Configurações**, ligue o que quiser usar. **Tudo vem desligado.** Sem isso, a barra e a dedicatória não aparecem.

**Moeda:** a loja agora mostra os preços em euro (`39,90 €`). Os valores no banco não mudam, só o símbolo.

### O que mudou

- **Carrinho no banco** (`carts` / `cart_items`). O visitante fica ligado à sessão. Quem está logado reencontra o carrinho no próximo login, e o carrinho de visitante é somado ao da conta quando a pessoa entra.
- **Frete:**
  - Agora usa as faixas de **Admin > Frete** (`shipping_rates`), na ordem: faixa de CEP, depois faixa do estado, depois a faixa padrão (sem estado e sem CEP). Sem CEP, vale só a faixa padrão.
  - Sem `free_above` na faixa, vale **Frete grátis a partir de** das Configurações (`free_shipping_min`). Sem nenhuma faixa, vale o frete fixo das Configurações.
  - ⚠️ **Os valores das faixas passam a valer na compra.** Confira-os em Admin > Frete.
- **Barra de frete grátis:** no carrinho e no mini-carrinho do topo (clique em "Carrinho (n)"). No carrinho ela muda ao digitar a quantidade, mas isso é só visual. O frete é sempre recalculado no servidor, e o checkout recalcula pelo CEP digitado. Se o total mudar, o pedido **não** é criado: a tela mostra o novo total e pede para confirmar de novo.
- **Dedicatória:**
  - Aparece só nos livros com **Permite dedicatória** marcado e com a opção ligada nas Configurações. O limite vem de `dedication_max_chars`, sem passar do tamanho da coluna no banco, e é conferido no servidor no carrinho e de novo ao criar o pedido.
  - É uma dedicatória por livro do carrinho.
  - Aparece no resumo do checkout, em Meus pedidos e no pedido do admin.
  - Em **Admin > Dedicatórias** há a lista para imprimir: um bloco por exemplar, com nº do pedido, título, destinatário e texto.
- **NIF e fatura:**
  - NIF opcional no perfil. No checkout, a caixa "Fatura com NIF" já vem preenchida e copia o nome e o NIF para o pedido. O NIF é validado (9 dígitos, módulo 11), e vazio vale como consumidor final, a não ser que **NIF obrigatório** esteja ligado.
  - No pedido do admin, registre o número, o link https e a data da fatura emitida no programa certificado. **O site não emite fatura oficial.** Com o link salvo, o cliente vê **Baixar fatura**.

### Roteiro de teste (Hostinger)

1. **Configurações:** ligue a barra de frete, a dedicatória (limite 20, para testar) e deixe o NIF obrigatório desligado.
2. **Livro:** em Admin > Livros, edite um livro e marque **Permite dedicatória**.
3. **Frete grátis, sem atingir:** como visitante, ponha 1 livro barato no carrinho. A barra mostra "Faltam X,XX € para frete grátis". Mude a quantidade: a barra muda na hora. Clique em salvar e confira se o frete do resumo bate.
4. **Frete grátis, atingido:** aumente até passar do valor. Aparece "Você ganhou frete grátis" e o frete fica "Grátis".
5. **Dedicatória estourada:** na página do livro, o campo para em 20 caracteres e o contador mostra "20 de 20 caracteres (limite atingido)". Para testar o servidor, abra o DevTools (F12), apague o `maxlength` do campo e envie 30 caracteres. Tem que aparecer o erro "A dedicatória tem 30 caracteres. O limite é de 20." e nada é salvo.
6. **Dedicatória ok:** escreva uma de até 20, adicione ao carrinho, edite no carrinho, finalize. Ela aparece no resumo do checkout, em Meus pedidos > pedido e em Admin > Pedidos.
7. **NIF válido:** no perfil, salve `123456789`. No checkout ele vem preenchido. Finalize, e o pedido do admin mostra o nome e o NIF.
8. **NIF inválido:** tente `123456780` no perfil ou no checkout. Aparece um erro grande e o pedido não é criado.
9. **NIF vazio:** deixe em branco, e o pedido sai como "consumidor final". Ligue **NIF obrigatório** e tente de novo: agora é recusado.
10. **Fatura:** no pedido do admin, salve o número, um link `https://...pdf` e a data. Em Meus pedidos aparece **Baixar fatura**. Um link `http://` ou `javascript:` é recusado.
11. **Impressão:** em Admin > Dedicatórias, clique em **Imprimir dedicatórias**. A visualização tem que sair só em preto e branco, com letra grande, um bloco por livro e sem menu.
12. **Login juntando carrinho:** como visitante, adicione um livro, entre na conta e confira se o carrinho continua lá.

## Pagamento: como plugar o Mercado Pago depois

O pagamento está isolado em `app/payment/`:

- `PaymentGateway.php`: o contrato (interface)
- `PixManualGateway.php`: o Pix manual atual (mostra a chave; o admin marca como pago)
- `Payment.php`: o registro das formas de pagamento ativas

Para o Mercado Pago:

1. Crie `app/payment/MercadoPagoGateway.php` implementando `PaymentGateway`. O `start()` cria a cobrança e devolve o ID; o `instructions()` devolve o QR/link.
2. Registre em `Payment::GATEWAYS` e adicione `'mercadopago'` em `config.php > payment.enabled`.
3. > **⚠️ Webhook obrigatório:** crie a rota `POST /webhook/mercadopago` (fora da checagem de CSRF) que **valida a assinatura** do Mercado Pago e chama `Order::changeStatus($id, 'pago')`. **Sem o webhook, o pedido fica "Pendente" para sempre, mesmo pago**, e você vai ter que mudar à mão no painel.

Checkout, pedidos e painel não precisam mudar.

## Painel administrativo ampliado

O painel usa as mesmas cores, fonte e componentes da loja. As URLs são geradas por `url()`, incluindo a subpasta: `https://netpubli.com.br/livraria-eric-aslan/admin`.

As áreas implementadas são resumo, livros, categorias, estoque, pedidos, pagamentos, frete, cupons, clientes, avaliações, mensagens, devoluções/trocas, banners, configurações e segurança. As listas principais têm busca, filtros e paginação. Cancelamentos são tratados no detalhe do pedido.

### Estrutura do banco

Para uma instalação nova, a ordem é `schema.sql`, `seed.sql`, `admin.sql`, `perfil_usuario.sql` e `modulos.sql`. `admin.sql` corresponde à extensão chamada `admin_painel.sql` no material original. As extensões com `ALTER TABLE` devem ser importadas uma única vez; se já foram aplicadas pelo phpMyAdmin, não importe novamente.

**Dependência pendente:** `perfil_usuario.sql` ainda não foi disponibilizado neste checkout. A view `v_admin_attention` do arquivo `modulos.sql` depende de `orders.cancel_requested_at`. Sem a extensão de perfil, a criação dessa view falha. O painel verifica a existência das tabelas e do campo antes de oferecer as operações correspondentes. A implementação de cancelamento usa esse campo, registra a decisão na auditoria e na linha do tempo e encerra a solicitação ao aprovar ou recusar.

Não reimporte `schema.sql` em uma loja com dados: ele apaga e recria as tabelas. Nenhum script de migração é executado automaticamente pelo painel.

### Operações

- Estoque: entrada/saída usa quantidade; ajuste usa o saldo final. Cada movimentação guarda responsável, motivo e saldo. Vendas, cancelamentos e reposições de devoluções também geram movimentos. Uma edição antiga do livro não sobrescreve um estoque que mudou.
- Pedidos: seguem pendente → pago → enviado → entregue; cancelamentos repõem o estoque uma única vez. A confirmação manual de pagamento no pedido também registra o recebimento financeiro. Rastreio e observações internas são salvos separadamente; a linha do tempo registra responsável e data.
- Pagamentos: tentativas, referências, Pix e JSON de gateway; confirmação, recusa e registro de estorno. O estorno registra uma transferência já realizada no banco/gateway; o painel não envia dinheiro nem chama uma API de gateway. Confirmações e estornos são limitados ao saldo do pedido/pagamento.
- Comprovantes novos: JPG, PNG, WEBP ou PDF, até 5 MB, em `storage/proofs/`; download somente com sessão de administrador. O servidor precisa de `fileinfo` e permissão de gravação nessa pasta.
- Devoluções: aprovação → recebimento → conclusão. A reposição é opcional ao receber e ocorre uma vez. Um reembolso na conclusão é vinculado ao saldo de um pagamento do pedido e registrado na auditoria.
- Mensagens: a resposta é salva; ao marcar como respondida, o sistema tenta enviar pelo `mail()` do servidor e informa se o envio falhar. Avaliações têm aprovação, rejeição, resposta e exclusão.
- Frete, cupons e banners: o painel mantém os dados e as regras. A vitrine/checkout devem consumir essas regras para aplicá-las à compra; esta entrega de admin não inclui um novo cálculo de frete por CEP, aplicação de cupons ou renderização de banners na vitrine.
- Configurações: nome, e-mail, contato, Pix, frete fixo e regras de avaliações, devoluções e carrinho abandonado. Nome, remetente, chave/titular Pix e frete fixo são lidos pelo código compartilhado; agendamento de tarefas de carrinho abandonado não faz parte do painel.
- Acesso: apenas administradores; clientes bloqueados perdem o acesso autenticado. Cinco falhas por e-mail ou vinte por IP em quinze minutos bloqueiam temporariamente novas tentativas, também pela tela de login da loja. O log registra as ações do painel sem guardar senhas.

### Verificação local

Os testes usam exclusivamente MariaDB/MySQL em `127.0.0.1:33079`, banco descartável `aslan_admin_test`, usuário local `root` sem senha. `tests/admin-integration.php --reset-test-database` apaga e recria **somente esse banco de teste**, sem ler `app/config.php`. A porta pode ser alterada por `LIVRARIA_TEST_PORT`.

```powershell
php tests/admin-integration.php --reset-test-database
$env:LIVRARIA_CONFIG_FILE = "$PWD/.tools/config.php"
php -S 127.0.0.1:8079 -t public tests/admin-router.php
# Em outro terminal:
node tests/admin-http.mjs
```

Habilite `pdo_mysql`, `fileinfo` e `mbstring` no PHP local. O teste de integração cria `.tools/config.php` com os dados do banco descartável. O roteador HTTP simula a subpasta do Apache. Se a extensão de perfil continuar ausente, o teste informa essa dependência e cria apenas uma coluna de fixture no banco descartável, sem criar uma migração de perfil presumida.

Nenhum deploy ou alteração no banco de produção foi executado durante essa verificação.

## Estrutura de arquivos

```
app/            config, bootstrap, db, helpers, Router, rotas
  controllers/  loja e admin/
  models/       User, Address, Book, Cart, Order, PasswordReset
  payment/      camada de pagamento isolada
  views/        telas (HTML com PHP), layout/, shop/, auth/, account/, admin/, errors/
database/       schema.sql e seed.sql
public/         index.php, assets/css, assets/js, assets/img, uploads/
storage/logs/   logs de erro e de e-mail (bloqueado para o navegador)
```

## Segurança implementada

- Senhas com `password_hash` / `password_verify` (com rehash automático)
- Todas as queries com prepared statements (PDO, sem emulação)
- Token CSRF verificado em todo POST, de forma central no roteador
- Toda saída escapada com `htmlspecialchars`
- `session_regenerate_id` no login, cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` em HTTPS), modo estrito de sessão
- Upload validado pelo conteúdo real (`getimagesize` + `finfo`): só JPG/PNG/WEBP, até 2 MB, nome aleatório, pasta sem execução de scripts
- Toda rota do admin passa por `require_admin()`
- Recuperação de senha com token aleatório guardado só como hash, validade de 1 hora e uso único; a resposta não revela se o e-mail existe
- Estoque baixado em transação com `SELECT ... FOR UPDATE` (dois clientes não compram a última unidade ao mesmo tempo)

## Trabalhando em dupla

- **Nunca edite o `app/config.php` para rodar na sua máquina.** Ele é o da Hostinger: um push com o banco local derruba a loja no ar. Copie `app/config.local.example.php` para `app/config.local.php` e ponha ali o seu banco local. Esse arquivo não vai para o Git e substitui só o que tiver nele.
- **Push na `main` = deploy na Hostinger.** Trabalhe numa branch (`git switch -c minha-tarefa`), abra um Pull Request no GitHub e só junte na `main` o que já foi testado.
- **Antes de começar:** `git switch main` e `git pull`. Depois volte para a sua branch e rode `git merge main`.
- **Mudança no banco:** crie um `.sql` novo em `database/` (ex.: `melhorias_2.sql`), commite e avise o outro. Cada um roda no seu banco local, e só um roda na Hostinger.
- **Rodar local:** PHP 8.1+ e MySQL/MariaDB (o XAMPP serve). Importe um export do banco, crie o `config.local.php` e rode `php -S localhost:8000 -t public`.
