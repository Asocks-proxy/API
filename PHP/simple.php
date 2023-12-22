<?php

$apiKey = 'PxbxW5lfu3pnE0y8ey_0YM1m2CPrf8tn';

class Response
{
    protected int $httpCode;

    protected ?string $data = null;

    protected ?string $error = null;

    protected array $errors = [];

    public function __construct(
        protected CurlHandle $curlHandle
    )
    {
        $response = curl_exec($this->curlHandle);
        $this->httpCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $this->error = curl_error($this->curlHandle);
        } else {
            $responseJson = json_decode($response);

            if ($responseJson?->success === false) {
                $this->errors = json_decode($response, true)['errors'];
                $this->error = $responseJson->message;
                return;
            }

            $this->data = $response;
        }
    }

    public function successful(): bool
    {
        return $this->getHttpCode() >= 200 && $this->getHttpCode() < 300;
    }

    public function failed(): bool
    {
        return $this->serverError() || $this->clientError();
    }

    public function clientError(): bool
    {
        return $this->getHttpCode() >= 400 && $this->getHttpCode() < 500;
    }

    public function serverError(): bool
    {
        return $this->getHttpCode() >= 500;
    }

    public function getJson(): ?object
    {
        if ($this->data) {
            return json_decode($this->data);
        }

        return $this->data;
    }

    public function getBody(): ?string
    {
        return $this->data;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

class Client
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey
    )
    {

    }

    /**
     * @throws Exception
     */
    public function request(string $method, string $path, array $data = []): Response
    {
        $url = $this->baseUrl . $path;

        $headers = ['Content-Type: application/json'];

        $ch = curl_init();

        $url .= $this->prepareRequest($ch, $method, $data);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_close($ch);

        return new Response($ch);
    }

    protected function prepareRequest(CurlHandle $ch, string $method, array $data): string
    {
        $queryString = [
            'apikey' => $this->apiKey
        ];

        $method = strtoupper($method);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT' | 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
                $queryString = [...$queryString, ...$data];
                break;
            default:
                throw new Exception('Method Not Allowed');
        }

        return '?' . http_build_query($queryString);
    }
}

class AsocksService
{
    protected Client $client;

    public function __construct(string $token)
    {
        $this->client = new Client('https://api.asocks.com/v2/', $token);
    }

    /**
     * @throws Exception
     */
    public function createPort(array $data): Response
    {
        return $this->client->request('post', 'proxy/create-port', $data);
    }

    public function getPorts(): Response
    {
        return $this->client->request('get', 'proxy/ports');
    }

    public function checkAllPorts(): void
    {
        $response = $this->getPorts()->getJson();

        $curlMulti = new CurlMulti();

        foreach ($response->message->proxies as $proxy) {
            $exploded = explode(':', $proxy->proxy);

            $proxy = new Proxy(
                host: $exploded[0],
                port: $exploded[1],
                username: $proxy->login,
                password: $proxy->password
            );

            $curlMulti->addHandle(
                curlHandle: (new ProxyRequest($proxy))->create(),
                proxy: $proxy
            );
        }
        
        $curlMulti->run();

        print_r($curlMulti->getUnsuccessfulProxies());
        print_r($curlMulti->getSuccessfulProxies());
    }
}

class Proxy
{
    public function __construct(
        public string  $host,
        public int     $port,
        public string  $username,
        public string  $password,
        public ?string $error = null
    )
    {

    }
}

class ProxyRequest
{
    private Proxy $proxy;
    private string $url = 'http://ip-api.com/json';

    public function __construct(Proxy $proxy)
    {
        $this->proxy = $proxy;
    }

    public function create(): CurlHandle
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_PROXY, $this->proxy->host);
        curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy->port);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$this->proxy->username}:{$this->proxy->password}");
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        return $ch;
    }
}

class CurlMulti
{
    protected array $handles = [];

    protected array $errors = [];

    protected array $success = [];

    protected CurlMultiHandle $multiHandle;

    public function __construct()
    {
        $this->multiHandle = curl_multi_init();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function addHandle(CurlHandle $curlHandle, Proxy $proxy): void
    {
        $this->handles[] = [
            'ch' => $curlHandle,
            'proxy' => $proxy
        ];

        curl_multi_add_handle($this->multiHandle, $curlHandle);
    }

    public function run(): void
    {
        $active = count($this->handles);

        do {
            curl_multi_exec($this->multiHandle, $active);
        } while ($active > 0);

        $this->handle();
    }

    public function close(): void
    {
        curl_multi_close($this->multiHandle);
    }

    public function getSuccessfulProxies(): array
    {
        return $this->success;
    }

    public function getUnsuccessfulProxies(): array
    {
        return $this->errors;
    }

    private function handle(): void
    {
        foreach ($this->handles as $handle) {
            $content = curl_multi_getcontent($handle['ch']);
            $proxy = $handle['proxy'];

            $result = $this->prepareResult($content);

            if ($result?->status === 'success') {
                $this->success[] = $proxy;
            } else {
                $this->errors[] = $proxy;
            }

            curl_multi_remove_handle($this->multiHandle, $handle['ch']);
            curl_close($handle['ch']);
        }

        $this->close();
    }

    protected function prepareResult(string $content): ?object
    {
        $data = json_decode($content);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        return $data;
    }
}

$service = new AsocksService($apiKey);

$response = $service->createPort([
    'country_code' => 'US',
    'state' => 'New York',
    'city' => 'New York',
    'asn' => 3,
    'type_id' => 1,
    'proxy_type_id' => 2,
    'name' => null,
    'server_port_type_id' => 1,
]);

$response = $service->getPorts()->getJson();

foreach ($response->message->proxies as $proxy){
    echo $proxy->name.PHP_EOL;
}

$service->checkAllPorts();
?>
