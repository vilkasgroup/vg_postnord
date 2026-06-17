<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Validator;

class VgPostnordPartyIdValidator
{
    /**
     * Checks that a given Party ID (customer number) is valid.
     *
     * New 10-character numbers are validated locally (leading zero and the
     * Luhn check digit). Older 8-character numbers are still in use and are
     * only checked for being numeric here; their actual validity is verified
     * against the PostNord API.
     *
     * @param string $party_id
     * @return bool
     */
    public static function partyIdIsValid(string $party_id): bool
    {
        if (!is_numeric($party_id)) {
            return false;
        }

        $length = strlen($party_id);
        if (8 === $length) {
            return true;
        }
        if (10 !== $length) {
            return false;
        }

        $first_number = substr($party_id, 0, 1);
        if ((int) $first_number !== 0) {
            return false;
        }

        $payload = substr($party_id, 0, 9);
        $multipliers = [2,1,2,1,2,1,2,1,2];
        $sum = 0;

        foreach (str_split($payload) as $index => $number) {
            $result = $multipliers[$index] * (int) $number;
            $sum += array_sum(str_split((string) $result));
        }

        $check_digit = 10 - ($sum % 10);
        if ($check_digit === 10) {
            $check_digit = 0;
        }
        $original_check_digit = substr($party_id, 9, 1);

        return $check_digit === (int) $original_check_digit;
    }
}
