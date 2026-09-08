<?php

use GuzzleHttp\Psr7\ServerRequest;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;
use Thirtybees\Core\Mcp\McpAuthentication;
use Thirtybees\Core\Mcp\McpToolRegistry;

class McpControllerCore extends FrontController
{
    public $php_self = 'mcp';
    public $ssl = true;

    public function run()
    {
        $this->initContent();
    }

    public function initContent()
    {
        $rawBody = (string) file_get_contents('php://input');
        $identity = (new McpAuthentication())->authenticate($this->getAuthorizationHeader());

        if ($identity === null) {
            $this->sendJsonError(401, 'Unauthorized', ['WWW-Authenticate' => 'Bearer realm="Thirty Bees MCP"']);
        }

        $standaloneAutoloader = _PS_ROOT_DIR_.'/Core/Mcp/vendor/autoload.php';
        if (is_file($standaloneAutoloader)) {
            require_once $standaloneAutoloader;
        }

        if (!class_exists(Server::class)) {
            $this->sendJsonError(503, 'MCP SDK is not installed');
        }

        $registry = new McpToolRegistry();
        Hook::exec('actionRegisterMcpTools', ['registry' => $registry]);

        $sessionDirectory = _PS_CACHE_DIR_.'mcp-sessions';
        if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0770, true) && !is_dir($sessionDirectory)) {
            PrestaShopLogger::addLog('MCP session directory could not be created', 3);
            $this->sendJsonError(500, 'Internal server error');
        }

        $builder = Server::builder()
            ->setServerInfo('Thirty Bees MCP', '0.2.0')
            ->setInstructions('Use the available small tools only for their documented purpose.')
            ->setSession(new FileSessionStore($sessionDirectory));

        foreach ($registry->all() as $name => $definition) {
            $builder->addTool(
                handler: $definition['handler'],
                name: $name,
                description: $definition['description'],
                annotations: $definition['annotations'] === []
                    ? null
                    : ToolAnnotations::fromArray($definition['annotations'])
            );
        }

        $request = new ServerRequest(
            Tools::getRequestMethod(),
            $this->getRequestUri(),
            $this->getRequestHeaders(),
            $rawBody,
            $this->getProtocolVersion(),
            $_SERVER
        );

        $transport = new StreamableHttpTransport(
            request: $request,
            middleware: [
                new CorsMiddleware(),
                new DnsRebindingProtectionMiddleware(allowedHosts: $this->getAllowedHosts()),
            ],
            maxBodyBytes: 65536
        );

        $startedAt = microtime(true);
        $response = $builder->build()->run($transport);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->logToolCalls($rawBody, $response, $identity->idToken, $identity->idEmployee, $durationMs);
        $this->sendResponse($response);
    }

    protected function displayMaintenancePage()
    {
        // Authentication still protects the MCP endpoint while the shop is in maintenance mode.
    }

    private function getAuthorizationHeader(): ?string
    {
        $authorization = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ($authorization === null && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    return $value;
                }
            }
        }

        return $authorization;
    }

    private function getRequestHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $name) {
            if (isset($_SERVER[$key])) {
                $headers[$name] = $_SERVER[$key];
            }
        }

        return $headers;
    }

    private function getRequestUri(): string
    {
        $scheme = Tools::usingSecureMode() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ((string) Configuration::get('PS_SHOP_DOMAIN_SSL') ?: 'localhost');
        $path = $_SERVER['REQUEST_URI'] ?? '/mcp';

        return $scheme.'://'.$host.$path;
    }

    private function getProtocolVersion(): string
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';

        return str_starts_with($protocol, 'HTTP/') ? substr($protocol, 5) : '1.1';
    }

    /**
     * @return string[]
     */
    private function getAllowedHosts(): array
    {
        $hosts = [];
        foreach (['PS_SHOP_DOMAIN', 'PS_SHOP_DOMAIN_SSL'] as $configurationKey) {
            $configuredHost = trim((string) Configuration::get($configurationKey));
            if ($configuredHost === '') {
                continue;
            }
            $host = parse_url(str_contains($configuredHost, '://') ? $configuredHost : '//'.$configuredHost, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[strtolower(rtrim($host, '.'))] = true;
            }
        }

        return array_keys($hosts ?: ['localhost' => true]);
    }

    private function logToolCalls(
        string $rawBody,
        ResponseInterface $response,
        int $idToken,
        int $idEmployee,
        int $durationMs
    ): void
    {
        $messages = json_decode($rawBody, true);
        if (!is_array($messages)) {
            return;
        }

        if (isset($messages['method'])) {
            $messages = [$messages];
        }

        $responseData = json_decode((string) $response->getBody(), true);
        $success = $response->getStatusCode() < 400 && !$this->containsMcpError($responseData);
        $resultCount = $this->extractResultCount($responseData);

        foreach ($messages as $message) {
            if (($message['method'] ?? null) === 'tools/call' && isset($message['params']['name'])) {
                McpAuditLog::record(
                    $idToken,
                    $idEmployee,
                    (string) $message['params']['name'],
                    $success,
                    $durationMs,
                    $resultCount
                );
            }
        }
    }

    private function containsMcpError($responseData): bool
    {
        if (!is_array($responseData)) {
            return true;
        }
        if (array_key_exists('error', $responseData) || !empty($responseData['result']['isError'])) {
            return true;
        }
        foreach ($responseData as $item) {
            if (is_array($item) && (array_key_exists('error', $item) || !empty($item['result']['isError']))) {
                return true;
            }
        }

        return false;
    }

    private function extractResultCount($responseData): ?int
    {
        if (!is_array($responseData)) {
            return null;
        }

        if (isset($responseData['result']['structuredContent']['total'])) {
            return max(0, (int) $responseData['result']['structuredContent']['total']);
        }

        if (isset($responseData['result']['content']) && is_array($responseData['result']['content'])) {
            foreach ($responseData['result']['content'] as $content) {
                if (($content['type'] ?? null) !== 'text' || !isset($content['text'])) {
                    continue;
                }
                $decoded = json_decode((string) $content['text'], true);
                if (is_array($decoded) && isset($decoded['total'])) {
                    return max(0, (int) $decoded['total']);
                }
            }
        }

        foreach ($responseData as $item) {
            if (is_array($item) && isset($item['result']['structuredContent']['total'])) {
                return max(0, (int) $item['result']['structuredContent']['total']);
            }
        }

        return null;
    }

    private function sendResponse(ResponseInterface $response): never
    {
        http_response_code($response->getStatusCode());
        $contentType = $response->getHeaderLine('Content-Type');
        header('Content-Type: '.($contentType !== '' ? $contentType : 'application/json; charset=UTF-8'), true);

        foreach ($response->getHeaders() as $name => $values) {
            if (strcasecmp($name, 'Content-Type') === 0) {
                continue;
            }
            foreach ($values as $value) {
                header($name.': '.$value, false);
            }
        }
        header('Cache-Control: no-store', true);
        header('X-Content-Type-Options: nosniff', true);
        echo $response->getBody();
        exit;
    }

    private function sendJsonError(int $status, string $message, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        foreach ($headers as $name => $value) {
            header($name.': '.$value);
        }
        echo json_encode(['error' => $message]);
        exit;
    }
}
