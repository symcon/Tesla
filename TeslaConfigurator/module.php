<?php

declare(strict_types=1);
class TeslaConfigurator extends IPSModuleStrict
{
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        //Connect to available splitter or create a new one
        $this->ConnectParent('{79EE0412-8C96-09A7-4D33-FE0EDF753562}');
    }

    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . '/form.json'));

        if ($this->HasActiveParent()) {
            $products = json_decode($this->SendDataToParent(json_encode([
                'DataID'   => '{FB4ED52F-A162-6F23-E7EA-2CBAAF48E662}',
                'Endpoint' => '/api/1/products',
                'Payload'  => ''
            ])))->response;


            $physicalChildren = $this->getPhysicalChildren();

            foreach ($products as $product) {
                $this->SendDebug('Products', json_encode($product), 0);

                $instanceID = $this->searchDevice($product->energy_site_id);
                $physicalChildren = array_diff($physicalChildren, [$instanceID]);

                $components = [];
                if ($product->components->battery) {
                    $components[] = $this->Translate("Battery");
                }
                if ($product->components->solar) {
                    $components[] = $this->Translate("Solar");
                }
                if ($product->components->grid) {
                    $components[] = $this->Translate("Grid");
                }
                if ($product->components->load_meter) {
                    $components[] = $this->Translate("Load Meter");
                }

                if (empty($components)) {
                    $components = $this->Translate("None");
                } else {
                    $components = implode(", ", $components);
                }

                $data->actions[0]->values[] = [
                    'energy_site_id'    => $product->energy_site_id,
                    'site_name'         => $product->site_name,
                    'components'        => $components,
                    'instanceID'        => $instanceID,
                    'create'            => [
                        'moduleID'      => '{4A06DAB6-0035-498A-C905-C3AD5C8644CB}',
                        'name' => $product->site_name,
                        'configuration' => [
                            'ESID' => $product->energy_site_id
                        ]
                    ],
                ];
            }


            foreach ($physicalChildren as $instanceID) {
                $data->actions[0]->values[] = [
                    'energy_site_id' => IPS_GetProperty($instanceID, 'ESID'),
                    'site_name'      => IPS_GetName($instanceID),
                    'info'           => '',
                    'instanceID'     => $instanceID,
                ];
            }
        }

        return json_encode($data);
    }

    private function getPhysicalChildren(): array
    {
        $connectionID = IPS_GetInstance($this->InstanceID);
        $ids = IPS_GetInstanceListByModuleID('{4A06DAB6-0035-498A-C905-C3AD5C8644CB}');
        $result = [];
        foreach ($ids as $id) {
            $i = IPS_GetInstance($id);
            if ($i['ConnectionID'] == $connectionID['ConnectionID']) {
                $result[] = $id;
            }
        }
        return $result;
    }
    private function searchDevice($energySiteID): int
    {
        $connectionID = IPS_GetInstance($this->InstanceID);
        $ids = IPS_GetInstanceListByModuleID('{4A06DAB6-0035-498A-C905-C3AD5C8644CB}');
        foreach ($ids as $id) {
            $i = IPS_GetInstance($id);
            if ($i['ConnectionID'] == $connectionID['ConnectionID']) {
                if (IPS_GetProperty($id, 'ESID') == $energySiteID) {
                    return $id;
                }
            }
        }
        return 0;
    }
}
