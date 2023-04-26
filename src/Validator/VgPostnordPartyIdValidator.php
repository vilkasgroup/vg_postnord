<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Validator;

class VgPostnordPartyIdValidator
{
    /**
     * Checks that a given Party ID is valid (according to the Luhn algorithm
     * and other restrictions given by PostNord)
     *
     * @param string $party_id
     * @return bool
     */
    public static function partyIdIsValid(string $party_id): bool
    {
        if (strlen($party_id) !== 10) {
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
