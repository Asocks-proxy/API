# Asocks Proxy Service Client in PHP

This PHP client is designed to interact with the [**Asocks**](https://asocks.com/c/2SII) Proxy Service API. It allows developers to create and manage proxy ports, check proxy details, and validate proxy configurations. The client utilizes cURL for HTTP requests and handles JSON responses, offering a straightforward interface for managing proxies.

## Getting Started

### Prerequisites

- PHP 7.4 or higher.
- cURL extension enabled in PHP.
- An active [**Asocks**](https://asocks.com/c/2SII) API key. Redeem the code **GITASOCKS** to get 5GB of free traffic. 

[![Sign Up to Asocks Proxy](https://imageup.ru/img80/4680191/asocks_gh.jpg)](https://asocks.com/c/2SII)

### Installation

Clone the repository to your local machine or just copy the PHP script into your project:

```bash
git clone git clone https://github.com/Asocks-proxy/API.git
```

Navigate to the cloned directory:

```bash
cd API/PHP
```

### Configuration

Set your Asocks API key in the script (located in Asocks API menu):

```
$apiKey = 'your-api-key-here';
```

## Usage

### Creating a Proxy Port

To create a new proxy port, you can use the **createPort** method from the **AsocksService** class:

```php
$service = new AsocksService($apiKey);

$service->createPort([
    'country_code' => 'US',
    // additional configuration...
]);
```

### Getting Proxy Information

To retrieve information about existing proxies:

```php
$response = $service->getPorts()->getJson();

foreach ($response->message->proxies as $proxy) {
    echo $proxy->name . PHP_EOL;
}
```

### Checking All Ports

To check all ports:

```php
$service->checkAllPorts();
```
