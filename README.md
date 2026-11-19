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
| `base_path` | `''` se a loja fica na raiz do domínio; `'/loja'` se ficar numa subpasta |
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

**Painel:** `/admin` resumo · `/admin/livros` · `/admin/livros/novo` · `/admin/pedidos` · `/admin/clientes`. Quem não está logado vai para `/admin/login`; cliente comum recebe 403.

## 7. Roteiro de teste na Hostinger

1. **Cadastro:** crie uma conta em `/cadastro`. Você já entra logado.
2. **Compra:** abra um livro, adicione ao carrinho, mude a quantidade, finalize no `/checkout` com endereço e Pix. Você vê a chave Pix e o pedido aparece como **Pendente** em `/pedidos`.
3. **Admin, livro:** entre em `/admin/login`, cadastre um livro com imagem JPG/PNG/WEBP de até 2 MB e confira se ele aparece no catálogo.
4. **Admin, status:** em `/admin/pedidos`, abra o pedido e mude para **Pago**, **Enviado** e **Entregue**. O cliente vê o andamento em `/pedidos/{id}`. Se cancelar, os livros voltam ao estoque.
5. **Recuperar senha:** com `debug => true` o link também aparece na tela, e no servidor ele fica em `storage/logs/mail.log`.

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

## Estrutura

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
