<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class TeslaValidationTest extends TestCaseSymconValidation
{
    public function testValidateekeybionyx(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }    public function testValidateTeslaCloud(): void
    {
        $this->validateModule(__DIR__ . '/../TeslaCloud');
    }    public function testValidateTeslaConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../TeslaConfigurator');
    }    public function testValidateTeslaSystem(): void
    {
        $this->validateModule(__DIR__ . '/../TeslaEnergySite');
    }
}