<?php

declare(strict_types=1);

namespace Tests\Postnord;

use PHPUnit\Framework\TestCase;
use Vilkas\Postnord\Validator\VgPostnordPartyIdValidator;

class ValidatorTest extends TestCase
{
    public function testPartyIdIsValidWithValidInputs()
    {
        $inputs = [
            "0151115870",
            "0948111919",
            "0347978074",
            "0262474968",
            "0911832020",
            "0024721516",
            "0937275733",
            "0310593876",
            "0869341479",
            "0148976509"
        ];

        foreach ($inputs as $value) {
            $this->assertTrue(VgPostnordPartyIdValidator::partyIdIsValid($value));
        }
    }

    public function testPartyIdIsValidWithInvalidChecksums()
    {
        $inputs = [
            "0986250435",
            "0590244329",
            "0097897232",
            "0535012282",
            "0845409080"
        ];

        foreach ($inputs as $value) {
            $this->assertFalse(VgPostnordPartyIdValidator::partyIdIsValid($value));
        }
    }

    public function testPartyIdIsValidWithInvalidLength()
    {
        $inputs = [
            "01511158690532",
            "09481110474569577",
            "0347971946948596584",
            "015111586",
            "09481110",
            "0347971",
        ];

        foreach ($inputs as $value) {
            $this->assertFalse(VgPostnordPartyIdValidator::partyIdIsValid($value));
        }
    }

    public function testPartyIdIsValidWithMalformedInputs()
    {
        $inputs = [
            "",
            "motivation",
            "7024721511",
            "4937275735",
            "9310593877"
        ];

        foreach ($inputs as $value) {
            $this->assertFalse(VgPostnordPartyIdValidator::partyIdIsValid($value));
        }
    }
}
