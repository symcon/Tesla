<?php

declare(strict_types=1);
class TeslaEnergySite extends IPSModuleStrict
{
    private const KEY_PROFILE_MAP = [
    'solar_power' => '~Watt',
    'percentage_charged' => 'Tesla.Percent',
    'battery_power' => '~Watt',
    'load_power' => '~Watt',
    'grid_power' => '~Watt',
    'generator_power' => '~Watt',
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('ESID', '');

        //Connect to available splitter or create a new one
        $this->ConnectParent('{D5994951-CD92-78B7-A059-3D423FCB599A}');

        //Timer
        $this->RegisterTimer('UpdateDuration', 0, 'TESLA_UpdateValues($_IPS[\'TARGET\']);');

        //Profile
        if (!IPS_VariableProfileExists('Tesla.Percent')) {
            IPS_CreateVariableProfile('Tesla.Percent', 2);
            IPS_SetVariableProfileIcon('Tesla.Percent', 'EnergyStorage');
            IPS_SetVariableProfileText('Tesla.Percent', '', ' %');
        }
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetTimerInterval('UpdateDuration', 1000 * 60 * 60 * 6);

        $this->UpdateValues();
    }



    private function KeyToName($key)
    {
        $parts = explode('_', $key);
        foreach ($parts as &$part) {
            $part = ucfirst($part);
        }
        return implode(' ', $parts);
    }

    public function UpdateValues(): void
    {
        if (!$this->ReadPropertyString("ESID")) {
            return;
        }
        $response = json_decode($this->SendDataToParent(json_encode([
            'DataID'   => '{FB4ED52F-A162-6F23-E7EA-2CBAAF48E662}',
            'Endpoint' => '/api/1/energy_sites/' . $this->ReadPropertyString("ESID") . "/live_status",
            'Payload'  => ''
        ])));
        $this->SendDebug('live_status', json_encode($response), 0);

        $position = 1;
        foreach ($response->response as $key => $value) {
            $profile = self::KEY_PROFILE_MAP[$key] ?? '';
            switch (gettype($value)) {
                case "boolean":
                    $this->RegisterVariableBoolean($key, $this->KeyToName($key), $profile, $position++);
                    $this->SetValue($key, $value);
                    break;
                case "integer":
                case "double":
                    $this->RegisterVariableFloat($key, $this->KeyToName($key), $profile, $position++);
                    $this->SetValue($key, $value);
                    break;
                case "string":
                    $this->RegisterVariableString($key, $this->KeyToName($key), $profile, $position++);
                    $this->SetValue($key, $value);
                    break;
            }
        }
    }
}
