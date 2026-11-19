<?php
declare(strict_types=1);

/**
 * Contrato de uma forma de pagamento.
 *
 * Para plugar o Mercado Pago depois:
 *   1. Crie app/payment/MercadoPagoGateway.php implementando esta interface.
 *   2. Registre o código dele em Payment::GATEWAYS.
 *   3. Adicione o código em config.php > payment.enabled.
 *   4. Crie a rota do webhook (veja o README) que chama Order::changeStatus($id, 'pago').
 * O resto da loja (checkout, pedidos, admin) não muda.
 */
interface PaymentGateway
{
    /** Código salvo em orders.payment_method. Ex.: "pix_manual". */
    public function code(): string;

    /** Nome mostrado ao cliente no checkout. */
    public function label(): string;

    /** Texto curto exibido abaixo da opção no checkout. */
    public function description(): string;

    /**
     * Chamado logo depois que o pedido é criado (status "pendente").
     * Pode criar a cobrança no provedor e devolver um ID externo,
     * ou null quando não há nada a criar.
     */
    public function start(array $order): ?string;

    /**
     * Instruções para o cliente pagar um pedido pendente.
     * @return array{text:string, fields:array<int,array{0:string,1:string}>, link:?string}
     */
    public function instructions(array $order): array;
}
