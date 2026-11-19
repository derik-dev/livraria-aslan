<?php
declare(strict_types=1);

/** Pix manual: mostra a chave configurada. O admin confirma o pagamento à mão. */
final class PixManualGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'pix_manual';
    }

    public function label(): string
    {
        return 'Pix';
    }

    public function description(): string
    {
        return 'Depois de finalizar, você vê a chave Pix. O pedido é confirmado assim que identificarmos o pagamento.';
    }

    public function start(array $order): ?string
    {
        return null;
    }

    public function instructions(array $order): array
    {
        $pix = (array) config('payment.pix', []);
        $fields = [
            ['Chave Pix (' . (string) ($pix['key_type'] ?? 'chave') . ')', (string) ($pix['key'] ?? '')],
            ['Favorecido', (string) ($pix['beneficiary'] ?? config('app_name'))],
        ];
        if (!empty($pix['bank'])) {
            $fields[] = ['Banco', (string) $pix['bank']];
        }
        $fields[] = ['Valor exato', money($order['total'])];
        $fields[] = ['Identificação', 'Pedido nº ' . (int) $order['id']];

        return [
            'text'   => 'Faça um Pix com o valor exato abaixo. Coloque o número do pedido na descrição do Pix, se o seu banco permitir. Assim que o pagamento for identificado, o status muda para "Pago".',
            'fields' => $fields,
            'link'   => null,
        ];
    }
}
