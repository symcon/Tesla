<?php

declare(strict_types=1);

class TeslaCloud extends IPSModuleStrict
{
    private $oauthIdentifer = 'tesla';
    private $oauthServer = 'oauth.ipmagic.de';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterAttributeString('Token', '');

        $this->RegisterOAuth($this->oauthIdentifer);

    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (!$this->ReadAttributeString('Token')) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        $this->SetStatus(IS_ACTIVE);
    }

    public function ForwardData(string $data): string
    {
        $data = json_decode($data);
        if (strlen($data->Payload) > 0) {
            $this->SendDebug('ForwardData', $data->Endpoint . ', Payload: ' . $data->Payload, 0);
            $result = $this->PostData($this->MakeURL($data->Endpoint), $data->Payload);
        } else {
            $this->SendDebug('ForwardData', $data->Endpoint, 0);
            $result = $this->GetData($this->MakeURL($data->Endpoint));
        }
        $this->SendDebug('Result', $result, 0);
        return $result;
    }

    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . '/form.json'));
        $data->elements[1]->caption = $this->ReadAttributeString('Token') ? 'Token: ' . substr($this->ReadAttributeString('Token'), 0, 16) . '...' : $this->Translate('Token: Not registered yet');
        return json_encode($data);
    }

    /**
     * This function will be called by the register button on the property page!
     */
    public function Register(): string
    {

        //Return everything which will open the browser
        return 'https://' . $this->oauthServer . '/authorize/' . $this->oauthIdentifer . '?username=' . urlencode(IPS_GetLicensee());

    }

    /**
     * This function will be called by the OAuth control. Visibility should be protected!
     */
    protected function ProcessOAuthData(): void
    {

        //Lets assume requests via GET are for code exchange. This might not fit your needs!
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {

            if (!isset($_GET['code'])) {
                die('Authorization Code expected');
            }

            $token = $this->FetchRefreshToken($_GET['code']);

            $this->SendDebug('ProcessOAuthData', "OK! Let's save the Refresh Token permanently", 0);

            $this->WriteAttributeString('Token', $token);
            $this->SetStatus(IS_ACTIVE);
            $this->UpdateFormField('Token', 'caption', 'Token: ' . substr($token, 0, 16) . '...');

        } else {

            //Just print raw post data!
            echo file_get_contents('php://input');

        }

    }

    private function MakeURL($endpoint)
    {
        return 'https://oauth.ipmagic.de/proxy/tesla' . $endpoint;
    }

    private function FetchRefreshToken($code): string
    {

        $this->SendDebug('FetchRefreshToken', 'Use Authentication Code to get our precious Refresh Token!', 0);

        //Exchange our Authentication Code for a permanent Refresh Token and a temporary Access Token
        $options = [
            'http' => [
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'        => 'POST',
                'content'       => http_build_query(['code' => $code]),
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($options);
        $result = file_get_contents('https://' . $this->oauthServer . '/access_token/' . $this->oauthIdentifer, false, $context);

        $data = json_decode($result);

        if (!isset($data->token_type) || strtolower($data->token_type) != 'bearer') {
            die('Bearer Token expected. Got: ' . $result);
        }

        //Save temporary access token
        $this->FetchAccessToken($data->access_token, time() + $data->expires_in);

        //Return RefreshToken
        return $data->refresh_token;
    }

    private function FetchAccessToken($Token = '', $Expires = 0): string
    {

        //Exchange our Refresh Token for a temporary Access Token
        if ($Token == '' && $Expires == 0) {

            //Check if we already have a valid Token in cache
            $data = $this->GetBuffer('AccessToken');
            if ($data != '') {
                $data = json_decode($data);
                if (time() < $data->Expires) {
                    $this->SendDebug('FetchAccessToken', 'OK! Access Token is valid until ' . date('d.m.y H:i:s', $data->Expires), 0);
                    return $data->Token;
                }
            }

            $this->SendDebug('FetchAccessToken', 'Use Refresh Token to get new Access Token!', 0);

            //If we slipped here we need to fetch the access token
            $options = [
                'http' => [
                    'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'method'        => 'POST',
                    'content'       => http_build_query(['refresh_token' => $this->ReadAttributeString('Token')]),
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($options);
            $result = file_get_contents('https://' . $this->oauthServer . '/access_token/' . $this->oauthIdentifer, false, $context);

            $data = json_decode($result);

            if (isset($data->error)) {
                die('Error: ' . $data->error_description);
            }

            if (!isset($data->token_type) || $data->token_type != 'Bearer') {
                die('Bearer Token expected' . $result);
            }

            //Update parameters to properly cache it in the next step
            $Token = $data->access_token;
            $Expires = time() + $data->expires_in;

            //Update Refresh Token if we received one! (This is optional)
            if (isset($data->refresh_token)) {
                $this->SendDebug('FetchAccessToken', "NEW! Let's save the updated Refresh Token permanently", 0);

                $this->WriteAttributeString('Token', $data->refresh_token);
                $this->UpdateFormField('Token', 'caption', 'Token: ' . substr($data->refresh_token, 0, 16) . '...');
            }

        }

        $this->SendDebug('FetchAccessToken', 'CACHE! New Access Token is valid until ' . date('d.m.y H:i:s', $Expires), 0);

        //Save current Token
        $this->SetBuffer('AccessToken', json_encode(['Token' => $Token, 'Expires' => $Expires]));

        //Return current Token
        return $Token;

    }

    private function GetData($url): string
    {

        $opts = [
            'http' => [
                'method'        => 'GET',
                'header'        => 'Authorization: Bearer ' . $this->FetchAccessToken() . "\r\n" . 'Content-Type: application/json' . "\r\n",
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($opts);

        $result = file_get_contents($url, false, $context);

        if ((strpos($http_response_header[0], '200') === false)) {
            echo $http_response_header[0] . PHP_EOL . $result;
            return '';
        }

        return $result;
    }

    private function PostData($url, $content)
    {
        $opts = [
            'http' => [
                'method'        => 'POST',
                'header'        => 'Authorization: Bearer ' . $this->FetchAccessToken() . "\r\n" . 'Content-Type: application/json' . "\r\n" . 'Content-Length: ' . strlen($content) . "\r\n",
                'content'       => $content,
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($opts);

        $result = file_get_contents($url, false, $context);

        if ((strpos($http_response_header[0], '201') === false)) {
            echo $http_response_header[0] . PHP_EOL . $result;
            return '';
        }

        return $result;
    }
}
