# Go Proxy Client 

This Go Proxy Client is a simple yet powerful tool for interacting with a [**Asocks**](https://asocks.com/c/2SII) proxy service API. It provides functionalities to create proxy ports and fetch detailed information about existing proxies. This client is especially useful for developers who need to manage or utilize Asocks proxies programmatically in their applications.

## Getting Started

### Prerequisites

To use this Go Proxy Client, you need to have Go installed on your machine. The code is tested with Go version 1.16 (or newer).

### Installation

You can clone this repository to your local machine using:

```bash
git clone https://github.com/Asocks-proxy/API.git
```

Navigate to the cloned directory:

```bash
cd API/GO
```

## Configuration

Before running the client, ensure you have a valid API token for [**Asocks**](https://asocks.com/c/2SII) Proxy Service. Redeem the code **GITASOCKS** to get 5GB of free traffic. 

[![Sign Up to Asocks Proxy](https://imageup.ru/img80/4680191/asocks_gh.jpg)](https://asocks.com/c/2SII)

Replace the token constant in the main function with your actual API token, which can be found in the API menu.

## Usage

To run the client, use the following command from the root of the project directory:

```
go run main.go
```

The client will perform actions as defined in the run function. You can modify this function to create new proxy ports or fetch existing proxies based on your requirements.

## Examples
### Creating a New Proxy Port
To create a new proxy port, uncomment the **CreatePort** section in the run function and specify the desired country code.

### Fetching Proxy Information
By default, the client fetches and logs information about existing proxies. This is done in the **GetPorts** function call within the run function.
