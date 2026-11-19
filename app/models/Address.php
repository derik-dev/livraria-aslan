<?php
declare(strict_types=1);

final class Address
{
    public static function forUser(int $userId): ?array
    {
        return db_one(
            'SELECT cep, street, number, complement, district, city, state FROM addresses WHERE user_id = ?',
            [$userId]
        );
    }

    public static function save(int $userId, array $a): void
    {
        db_run(
            'INSERT INTO addresses (user_id, cep, street, number, complement, district, city, state)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cep = VALUES(cep), street = VALUES(street), number = VALUES(number),
                 complement = VALUES(complement), district = VALUES(district), city = VALUES(city), state = VALUES(state)',
            [
                $userId, $a['cep'], $a['street'], $a['number'],
                $a['complement'] === '' ? null : $a['complement'],
                $a['district'], $a['city'], $a['state'],
            ]
        );
    }
}
