<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SaintSystems\OData\ODataClient;

class SAPService
{
    protected string $cookieStr;
    protected ODataClient $odataClient;

    /**
     * @throws ConnectionException
     */
    public function __construct(
        protected string $host = '',
        protected string $db = '',
        protected string $username = '',
        protected string $password = '',
        protected bool $verifySSL = true,
    ) {
        $this->host = $host ?: config('sap.path');
        $this->db = $db ?: config('sap.db');
        $this->username = $username ?: config('sap.user');
        $this->password = $password ?: config('sap.password');
        $this->verifySSL = $verifySSL ?: config('sap.verifySSL');

        $this->authenticate();
        $this->setOdataClient();
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getCookieStr(): string
    {
        return $this->cookieStr;
    }

    public function getOdataClient(): ODataClient
    {
        return $this->odataClient;
    }

    protected function setOdataClient(): void
    {
        $this->odataClient = new ODataClient($this->host, function ($request) {
            $request->headers['Cookie'] = $this->cookieStr;
        });

        $this->odataClient->getHttpProvider()->setExtraOptions([
            'verify' => $this->verifySSL,
        ]);
    }

    /**
     * @throws ConnectionException
     */
    protected function authenticate(): void
    {
        $cookies = new CookieJar();
        $loginResponse = Http::withOptions([
            'verify' => $this->verifySSL,
            'cookies' => $cookies,
        ])->post("{$this->host}/Login", [
            'CompanyDB' => $this->db,
            'Password' => $this->password,
            'UserName' => $this->username,
        ]);

        $b1Session = $cookies->getCookieByName('B1SESSION');
        $routeId = $cookies->getCookieByName('ROUTEID');

        if ($b1Session && $routeId) {
            $this->cookieStr = "B1SESSION={$b1Session->getValue()}; ROUTEID={$routeId->getValue()}";
        } else {
            throw new ConnectionException('Failed to retrieve SAP B1 session cookies.');
        }
    }
}
