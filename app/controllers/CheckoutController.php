<?php
declare(strict_types=1);

/**
 * Dois modos de checkout:
 *  - carrinho (padrão): fecha o carrinho inteiro.
 *  - agora (?agora=1): "Comprar agora" de um livro só; o carrinho fica intacto.
 */
final class CheckoutController
{
    public function show(): void
    {
        $buyNow = query('agora', 1) === '1';
        if (!$buyNow) {
            unset($_SESSION['buy_now']); // voltou ao fluxo normal do carrinho
        }
        $user = require_login();
        $summary = $this->summaryOrRedirect($buyNow);

        $address = Address::forUser((int) $user['id']) ?? [];
        $this->render($user, $summary, $address + [
            'name'         => $user['name'],
            'phone'        => (string) ($user['phone'] ?? ''),
            'invoice_name' => $user['name'],
            'invoice_nif'  => (string) ($user['nif'] ?? ''),
        ], [], array_key_first(Payment::available()) ?? '', $buyNow);
    }

    public function place(): void
    {
        $buyNow = input('mode', 10) === 'agora';
        $user = require_login();
        $summary = $this->summaryOrRedirect($buyNow);

        [$address, $errors] = read_address_input();
        $name = input('name', 100);
        $phone = only_digits(input('phone', 20));
        $method = input('payment_method', 30);

        if (text_length($name) < 3) {
            $errors['name'] = 'Informe o nome de quem vai receber.';
        }
        if ($phone !== '' && (strlen($phone) < 10 || strlen($phone) > 11)) {
            $errors['phone'] = 'Informe o telefone com DDD (10 ou 11 números).';
        }
        $gateways = Payment::available();
        if (!isset($gateways[$method])) {
            $errors['payment_method'] = 'Escolha a forma de pagamento.';
        }

        // Fatura com NIF (vazio = consumidor final, a não ser que seja obrigatório).
        $invoice = ['nif' => '', 'name' => ''];
        if (Nif::enabled()) {
            [$invoice['nif'], $nifError] = Nif::read(input('invoice_nif', 30), Nif::required());
            $invoice['name'] = input('invoice_name', min(150, AdminSchema::column('orders', 'invoice_name')['length'] ?? 150));
            if ($nifError !== null) {
                $errors['invoice_nif'] = $nifError;
            }
            if ($invoice['nif'] !== '' && text_length($invoice['name']) < 2) {
                $errors['invoice_name'] = 'Informe o nome que vai na fatura.';
            }
        }

        // Frete sempre recalculado aqui, com o endereço enviado.
        $summary = Cart::withShipping($summary, $address['cep'], $address['state']);
        $form = $address + ['name' => $name, 'phone' => $phone, 'invoice_name' => $invoice['name'], 'invoice_nif' => $invoice['nif']];

        if ($errors) {
            http_response_code(422);
            $this->render($user, $summary, $form, $errors, $method, $buyNow);
            return;
        }

        // O total mudou (outro endereço = outra faixa de frete)? Mostra antes de cobrar.
        $shown = input_int('shown_total');
        if ($shown !== null && $shown !== $summary['total_cents']) {
            http_response_code(422);
            flash('info', 'O frete foi recalculado para o endereço informado. Novo total: ' . money_cents($summary['total_cents']) . '. Confira e clique em "Confirmar pedido" de novo.');
            $this->render($user, $summary, $form, [], $method, $buyNow);
            return;
        }

        try {
            $orderId = Order::create(
                (int) $user['id'],
                $summary['map'],
                $address + ['name' => $name, 'phone' => $phone],
                $method,
                ['dedications' => $summary['dedications'], 'invoice' => $invoice]
            );
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect($buyNow ? $this->buyNowBackTo() : '/carrinho');
            return;
        }

        if ($buyNow) {
            unset($_SESSION['buy_now'], $_SESSION['buy_now_dedication']);
        } else {
            cart_clear();
        }
        Address::save((int) $user['id'], $address);

        $order = Order::findForUser($orderId, (int) $user['id']);
        if ($order !== null) {
            $reference = $gateways[$method]->start($order);
            if ($reference !== null) {
                Order::setPaymentReference($orderId, $reference);
            }
        }

        redirect('/pedido/' . $orderId . '/confirmacao');
    }

    public function confirmation(int $id): void
    {
        $user = require_login();
        $order = Order::findForUser($id, (int) $user['id']);
        if ($order === null) {
            render_error(404);
            return;
        }
        $gateway = Payment::get((string) $order['payment_method']);
        view('shop/confirmation', [
            'title'        => 'Pedido recebido',
            'order'        => $order,
            'items'        => Order::items($id),
            'instructions' => ($gateway !== null && $order['status'] === 'pendente') ? $gateway->instructions($order) : null,
        ]);
    }

    private function summaryOrRedirect(bool $buyNow): array
    {
        if ($buyNow) {
            $back = $this->buyNowBackTo();
            $summary = Cart::buyNowSummary();
            foreach ($summary['notices'] as $n) {
                flash('info', $n);
            }
            if (!$summary['items']) {
                unset($_SESSION['buy_now']);
                if (!$summary['notices']) {
                    flash('info', 'Escolha um livro e clique em "Comprar agora".');
                }
                redirect($back);
            }
            return $summary;
        }

        $summary = Cart::summary();
        foreach ($summary['notices'] as $n) {
            flash('info', $n);
        }
        if (!$summary['items']) {
            flash('info', 'Seu carrinho está vazio.');
            redirect('/carrinho');
        }
        if ($summary['notices']) {
            // Algo mudou no carrinho: mostra ao cliente antes de seguir.
            redirect('/carrinho');
        }
        return $summary;
    }

    /** Página do livro da compra direta (ou o catálogo, se não houver). */
    private function buyNowBackTo(): string
    {
        $id = array_key_first(buy_now_items());
        return $id !== null ? '/livro/' . $id : '/catalogo';
    }

    private function render(array $user, array $summary, array $form, array $errors, string $method, bool $buyNow): void
    {
        view('shop/checkout', [
            'title'    => 'Finalizar compra',
            'user'     => $user,
            'summary'  => $summary,
            'form'     => $form,
            'errors'   => $errors,
            'gateways' => Payment::available(),
            'method'   => $method,
            'buyNow'   => $buyNow,
            'backTo'   => $buyNow ? $this->buyNowBackTo() : '/carrinho',
            'invoiceEnabled' => Nif::enabled(),
            'nifRequired'    => Nif::enabled() && Nif::required(),
        ]);
    }
}
