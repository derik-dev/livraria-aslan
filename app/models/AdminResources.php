<?php
declare(strict_types=1);

/** Metadados fechados: nenhum identificador SQL vem de parâmetros do visitante. */
final class AdminResources
{
    public static function definitions(): array
    {
        return [
            'frete' => ['table' => 'shipping_rates', 'title' => 'Frete', 'search' => ['name', 'state', 'cep_start', 'cep_end'],
                'columns' => ['name' => 'Faixa', 'state' => 'Estado', 'cep_start' => 'CEP inicial', 'cep_end' => 'CEP final', 'price' => 'Preço', 'active' => 'Ativa'],
                'fields' => [
                    'name' => ['Nome da faixa', 'text', true, 80], 'state' => ['Estado (vazio = todos)', 'select', false, ['' => 'Todos os estados'] + array_combine(BR_STATES, BR_STATES)],
                    'cep_start' => ['CEP inicial (opcional)', 'cep', false, 9], 'cep_end' => ['CEP final (opcional)', 'cep', false, 9],
                    'price' => ['Preço em euros', 'money', true], 'free_above' => ['Frete grátis a partir de (€, vazio = usa a configuração da loja)', 'money', false],
                    'days_min' => ['Prazo mínimo em dias úteis', 'integer', true, 255], 'days_max' => ['Prazo máximo em dias úteis', 'integer', true, 255],
                    'active' => ['Ativa', 'checkbox'],
                ], 'delete' => true],
            'banners' => ['table' => 'banners', 'title' => 'Banners', 'search' => ['title', 'subtitle'],
                'columns' => ['title' => 'Título', 'position' => 'Posição', 'sort_order' => 'Ordem', 'starts_at' => 'Início', 'ends_at' => 'Fim', 'active' => 'Ativo'],
                'fields' => [
                    'title' => ['Título', 'text', true, 150], 'subtitle' => ['Subtítulo', 'text', false, 255],
                    'link_url' => ['Link (ex.: /catalogo)', 'link', false, 255], 'button_text' => ['Texto do botão', 'text', false, 40],
                    'position' => ['Posição na página inicial', 'select', true, ['home_topo' => 'Topo', 'home_meio' => 'Meio', 'home_rodape' => 'Rodapé']],
                    'sort_order' => ['Ordem de exibição (menor primeiro)', 'integer', true, 100000],
                    'starts_at' => ['Início da exibição', 'datetime', false], 'ends_at' => ['Fim da exibição', 'datetime', false],
                    'active' => ['Ativo', 'checkbox'],
                ], 'delete' => true, 'image' => true],
            'categorias' => ['table' => 'categories', 'title' => 'Categorias', 'search' => ['name', 'slug'],
                'columns' => ['name' => 'Nome', 'slug' => 'Endereço', 'active' => 'Ativa'],
                'fields' => [
                    'name' => ['Nome', 'text', true, 80], 'slug' => ['Endereço (ex.: ficcao)', 'slug', true, 100],
                    'active' => ['Ativa', 'checkbox'],
                ], 'delete' => false],
            'cupons' => ['table' => 'coupons', 'title' => 'Cupons', 'search' => ['code'],
                'columns' => ['code' => 'Código', 'type' => 'Tipo', 'value' => 'Valor', 'used_count' => 'Usos', 'expires_at' => 'Validade', 'active' => 'Ativo'],
                'fields' => [
                    'code' => ['Código', 'code', true, 30], 'type' => ['Tipo', 'select', true, ['percent' => 'Porcentagem (%)', 'fixed' => 'Valor em euros']],
                    'value' => ['Desconto', 'money', true], 'min_order' => ['Compra mínima (R$)', 'money', true],
                    'max_uses' => ['Limite total de usos (vazio = sem limite)', 'integer', false, 100000000],
                    'starts_at' => ['Início', 'datetime', false], 'expires_at' => ['Fim', 'datetime', false],
                    'active' => ['Ativo', 'checkbox'],
                ], 'delete' => false],
        ];
    }

    public static function get(string $key): array
    {
        $definition = self::definitions()[$key] ?? null;
        if ($definition === null) throw new DomainException('Área não encontrada.');
        return $definition;
    }

    public static function validate(array $definition, array $source): array
    {
        $values = []; $errors = [];
        foreach ($definition['fields'] as $key => $field) {
            [$label, $type] = $field;
            $required = $field[2] ?? false;
            $raw = is_string($source[$key] ?? null) ? trim($source[$key]) : '';
            $value = $raw === '' ? null : $raw;
            if ($type === 'checkbox') {
                $values[$key] = $raw === '1' ? 1 : 0;
                continue;
            }
            if ($raw === '') {
                if ($required) $errors[$key] = 'Preencha este campo.';
            } elseif (in_array($type, ['money', 'integer'], true)) {
                $number = $type === 'money' ? parse_money($raw) : filter_var($raw, FILTER_VALIDATE_INT);
                if ($number === null || $number === false || $number < 0 || $number > ($type === 'money' ? 9999999999 : ($field[3] ?? 100000))) {
                    $errors[$key] = 'Informe um valor válido, maior ou igual a zero.';
                } else {
                    $value = $type === 'money' ? cents_to_decimal($number) : $number;
                }
            } elseif ($type === 'cep') {
                $value = only_digits($raw);
                if (strlen($value) !== 8) $errors[$key] = 'Informe um CEP com 8 números.';
            } elseif ($type === 'datetime') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw);
                if (!$date || $date->format('Y-m-d\TH:i') !== $raw) $errors[$key] = 'Informe uma data válida.';
                else $value = $date->format('Y-m-d H:i:s');
            } elseif ($type === 'select') {
                if (!array_key_exists($raw, $field[3])) $errors[$key] = 'Escolha uma opção válida.';
            } else {
                if (text_length($raw) > ($field[3] ?? 255)) $errors[$key] = 'O texto ultrapassa o limite deste campo.';
                if ($type === 'slug' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $raw)) $errors[$key] = 'Use letras minúsculas sem acentos, números e hífens.';
                if ($type === 'code') {
                    $value = strtoupper($raw);
                    if (!preg_match('/^[A-Z0-9_-]+$/D', $value)) $errors[$key] = 'Use letras, números, hífens ou sublinhados.';
                }
                if ($type === 'link' && $raw !== '' && !self::safeLink($raw)) $errors[$key] = 'Use um caminho da loja ou uma URL https://.';
            }
            $values[$key] = $value;
        }
        if (isset($values['starts_at'], $values['expires_at']) && $values['expires_at'] <= $values['starts_at']) $errors['expires_at'] = 'O fim precisa ser posterior ao início.';
        if (isset($values['starts_at'], $values['ends_at']) && $values['ends_at'] <= $values['starts_at']) $errors['ends_at'] = 'O fim precisa ser posterior ao início.';
        if (($definition['table'] ?? '') === 'shipping_rates') {
            if (($values['cep_start'] === null) !== ($values['cep_end'] === null)) $errors['cep_end'] = 'Preencha os dois limites de CEP ou deixe ambos vazios.';
            if ($values['cep_start'] !== null && strcmp($values['cep_start'], $values['cep_end'] ?? '') > 0) $errors['cep_end'] = 'O CEP final precisa ser maior ou igual ao inicial.';
            if (is_numeric($values['days_min']) && is_numeric($values['days_max']) && (int) $values['days_min'] > (int) $values['days_max']) $errors['days_max'] = 'O prazo máximo precisa ser maior ou igual ao mínimo.';
        }
        if (($definition['table'] ?? '') === 'coupons') {
            if (($values['type'] ?? '') === 'percent' && (float) ($values['value'] ?? 0) > 100) $errors['value'] = 'A porcentagem não pode passar de 100%.';
            if ((float) ($values['value'] ?? 0) <= 0) $errors['value'] = 'O desconto deve ser maior que zero.';
            if (($values['max_uses'] ?? null) === 0) $errors['max_uses'] = 'Informe pelo menos 1 uso ou deixe vazio.';
        }
        return [$values, $errors];
    }

    public static function safeLink(string $value): bool
    {
        if (preg_match('/[\x00-\x20\\\\]/', $value)) return false;
        return (str_starts_with($value, '/') && !str_starts_with($value, '//'))
            || (filter_var($value, FILTER_VALIDATE_URL) && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https');
    }
}
