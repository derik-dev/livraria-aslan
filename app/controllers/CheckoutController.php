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
            'name'  => $user['name'],
            'phone' => (string) ($user['phone'] ?? ''),
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

        if ($errors) {
            http_response_code(422);
            $this->render($user, $summary, $address + ['name' => $name, 'phone' => $phone], $errors, $method, $buyNow);
            return;
        }

        try {
            $orderId = Order::create(
                (int) $user['id'],
                $summary['map'],
                $address + ['name' => $name, 'phone' => $phone],
                $method
            );
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect($buyNow ? $this->buyNowBackTo() : '/carrinho');
            return;
        }

        if ($buyNow) {
            unset($_SESSION['buy_now']);
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
        ]);
    }
}
