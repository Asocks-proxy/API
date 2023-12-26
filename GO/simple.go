package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"os"
	"os/signal"
)

type UnexpectedStatusCode int

func (code UnexpectedStatusCode) Error() string {
	return fmt.Sprintf("unexpected response status code: %d", code)
}

func StatusCodeIs(err error, code int) bool {
	var errStatusCode UnexpectedStatusCode
	return errors.As(err, &errStatusCode) && int(errStatusCode) == code
}

type ProxyRequest struct {
	CountryCode string `json:"country_code"`
}

type Pagination struct {
	Page       int `json:"page"`
	PageCount  int `json:"pageCount"`
	PageSize   int `json:"pageSize"`
	TotalCount int `json:"totalCount"`
}

type Message struct {
	CountProxies int             `json:"countProxies"`
	Pagination   Pagination      `json:"pagination"`
	Proxies      []ProxyResponse `json:"proxies"`
}

type ProxyResponse struct {
	ID                int     `json:"id"`
	Name              string  `json:"name"`
	Proxy             string  `json:"proxy"`
	Template          string  `json:"template"`
	ProxyTypeID       int     `json:"proxy_type_id"`
	Login             string  `json:"login"`
	Password          string  `json:"password"`
	CountryCode       string  `json:"countryCode"`
	CityName          any     `json:"cityName"`
	StateName         any     `json:"stateName"`
	Asn               any     `json:"asn"`
	SpentTrafficMonth float64 `json:"spent_traffic_month"`
	SpentMoneyMonth   float64 `json:"spent_money_month"`
	Status            int     `json:"status"`
	CreatedAt         string  `json:"created_at"`
	Speed             int     `json:"speed"`
	ExternalIP        string  `json:"externalIp"`
}

type ProxyClient struct {
	httpClient *http.Client
	baseURL    string
	token      string
}

func NewProxyClient(httpClient *http.Client, baseURL string, token string) *ProxyClient {
	if httpClient == nil {
		httpClient = http.DefaultClient
	}

	const defaultBaseURL = "https://api.asocks.com/v2"

	if baseURL == "" {
		baseURL = defaultBaseURL
	}

	return &ProxyClient{
		httpClient: httpClient,
		baseURL:    baseURL,
		token:      token,
	}
}

func NewProxyClientDefault(token string) *ProxyClient {
	return NewProxyClient(nil, "", token)
}

func (c *ProxyClient) Call(ctx context.Context, method, handle string, payload io.Reader) (json.RawMessage, error) {
	params := url.Values{}
	params.Set("apikey", c.token)

	req, err := http.NewRequestWithContext(ctx,
		method,
		fmt.Sprintf("%s/%s?%s", c.baseURL, handle, params.Encode()),
		payload,
	)
	if err != nil {
		return nil, err
	}

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		_, _ = io.Copy(io.Discard, resp.Body)
		return nil, UnexpectedStatusCode(resp.StatusCode)
	}

	var raw json.RawMessage

	if err = json.NewDecoder(resp.Body).Decode(&raw); err != nil {
		return nil, fmt.Errorf("decode response body: %w", err)
	}

	return raw, nil
}

func (c *ProxyClient) CreatePort(ctx context.Context, pr ProxyRequest) error {
	payload, err := json.Marshal(&pr)
	if err != nil {
		return fmt.Errorf("encode request body: %w", err)
	}

	_, err = c.Call(ctx, http.MethodPost, "proxy/create-port", bytes.NewReader(payload))
	return nil
}

func (c *ProxyClient) GetPorts(ctx context.Context) ([]ProxyResponse, error) {
	raw, err := c.Call(ctx, http.MethodGet, "proxy/ports", nil)
	if err != nil {
		return nil, err
	}

	var resp struct {
		Success bool    `json:"success"`
		Message Message `json:"message"`
	}

	if err = json.Unmarshal(raw, &resp); err != nil {
		return nil, fmt.Errorf("decode: %w", err)
	}

	return resp.Message.Proxies, nil
}

func run(ctx context.Context, pc *ProxyClient) error {
	// var err error

	// if err = pc.CreatePort(ctx, ProxyRequest{
	// 	CountryCode: "US",
	// }); err != nil {
	// 	return fmt.Errorf("create port: %w", err)
	// }

	proxies, err := pc.GetPorts(ctx)
	if err != nil {
		return fmt.Errorf("get ports: %w", err)
	}

	for _, p := range proxies {
		log.Printf("proxy: %+v\n", p)
	}

	return nil
}

func main() {
	ctx, cancel := signal.NotifyContext(context.Background(), os.Interrupt)
	defer cancel()

	const token = "PxbxW5lfu3pnE0y8ey_0YM1m2CPrf8tn"
	proxyClient := NewProxyClientDefault(token)

	err := run(ctx, proxyClient)
	if errors.Is(err, context.Canceled) {
		log.Println("canceled")
		return
	}
	if err != nil {
		log.Println(fmt.Errorf("failed to %w", err))
		return
	}

	log.Println("done")
}
