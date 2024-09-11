<?php

declare(strict_types=1);
class TeslaEnergySite extends IPSModuleStrict
{
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('ESID', '');

        //Connect to available splitter or create a new one
        $this->ConnectParent('{D5994951-CD92-78B7-A059-3D423FCB599A}');
    }

    private function KeyToName($key) {
        $parts = explode('_', $key);
        foreach ($parts as &$part) {
            $part = ucfirst($part);
        }
        return implode(' ', $parts);
    }

    public function Test(): void
    {
        $response = json_decode($this->SendDataToParent(json_encode([
            'DataID'   => '{FB4ED52F-A162-6F23-E7EA-2CBAAF48E662}',
            'Endpoint' => '/api/1/energy_sites/' . $this->ReadPropertyString("ESID") . "/live_status",
            'Payload'  => ''
        ])));

        $position = 1;
        foreach ($response->response as $key => $value) {
            switch(gettype($value)) {
                case "boolean":
                    $this->RegisterVariableBoolean($key, $this->KeyToName($key), "", $position++);
                    $this->SetValue($key, $value);
                    break;
                case "integer":
                case "double":
                    $this->RegisterVariableFloat($key, $this->KeyToName($key), "", $position++);
                    $this->SetValue($key, $value);
                    break;
                case "string":
                    $this->RegisterVariableString($key, $this->KeyToName($key), "", $position++);
                    $this->SetValue($key, $value);
                    break;
            }
        }
    }
}
