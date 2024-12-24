<?php

$host = '0.0.0.0';
$port = $argv[1] ?? 8080;
$baseDir = __DIR__ . '/public';
$workerCount = 4;

$socket = stream_socket_server("tcp://{$host}:{$port}", $errNo, $errStr);
if (!$socket) {
    die("Server startup failure: $errStr ($errNo)\n");
}

echo "Server Listening on http://{$host}:{$port}\n";

for ($i = 0; $i < $workerCount; $i++) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        workerProcess($socket, $baseDir);
        exit;
    }
}

for ($i = 0; $i < $workerCount; $i++) {
    pcntl_wait($status);
}

function workerProcess($socket, $baseDir)
{
    while (true) {
        $client = @stream_socket_accept($socket);
        if (!$client) {
            continue;
        }

        $request = readRequest($client);
        if (!$request) {
            fclose($client);
            continue;
        }

        $response = handleRequest($request, $baseDir);
        fwrite($client, $response);
        fclose($client);
    }
}

function readRequest($client): string
{
    $request = '';

    while (($line = fgets($client)) !== false) {
        $request .= $line;
        if (trim($line) === '') {
            break;
        }
    }

    if (preg_match('/Content-Length: (\d+)/i', $request, $matches)) {
        $contentLength = (int)$matches[1];
        $body = fread($client, $contentLength);
        $request .= $body;
    }

    return $request;
}

function handleRequest($request, $baseDir)
{
    [$headers, $body] = parseRequest($request);
    $uri = $headers['REQUEST_URI'] ?? '/';
    $method = $headers['REQUEST_METHOD'] ?? 'GET';

    $_GET = [];
    $queryString = parse_url($uri, PHP_URL_QUERY);
    if ($queryString) {
        parse_str($queryString, $_GET);
    }

    $_POST = [];
    if ($method === 'POST') {
        parse_str($body, $_POST);
    }

    $_REQUEST = array_merge($_GET, $_POST);

    $filePath = getFileFromUri($uri, $baseDir);
    if ($filePath && is_readable($filePath)) {
        if (is_dir($filePath)) {
            $filePath = rtrim($filePath, '/') . '/index.php';
        }

        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
            return executePhpScript($filePath);
        }

        return sendStaticFile($filePath);
    }

    return sendResponse(404, "404 Not Found");
}

function getFileFromUri($uri, $baseDir)
{
    $path = realpath($baseDir . parse_url($uri, PHP_URL_PATH));
    return ($path && str_starts_with($path, realpath($baseDir))) ? $path : false;
}

function executePhpScript($filePath)
{
    ob_start();
    include $filePath;
    $output = ob_get_clean();
    return constructResponse(200, $output, "text/html; charset=UTF-8");
}

function sendStaticFile($filePath)
{
    $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
    $content = file_get_contents($filePath);
    return constructResponse(200, $content, $mimeType);
}

function parseRequest($request): array
{
    $lines = explode("\r\n", $request);
    $headers = [];
    $body = '';

    $isBody = false;
    foreach ($lines as $line) {
        if ($isBody) {
            $body .= $line;
        } elseif (trim($line) === '') {
            $isBody = true;
        } elseif (preg_match('/^(GET|POST) (\S+) HTTP\/1\.\d/', $line, $matches)) {
            $headers['REQUEST_METHOD'] = $matches[1];
            $headers['REQUEST_URI'] = $matches[2];
        } elseif (preg_match('/^(\S+): (.+)$/', $line, $matches)) {
            $headers[$matches[1]] = $matches[2];
        }
    }

    return [$headers, trim($body)];
}

function constructResponse($statusCode, $body, $contentType = 'text/plain')
{
    $statusMessages = [200 => 'OK', 404 => 'Not Found'];
    $statusMessage = $statusMessages[$statusCode] ?? 'Unknown';

    $headers = "HTTP/1.1 {$statusCode} {$statusMessage}\r\n";
    $headers .= "Content-Type: {$contentType}\r\n";
    $headers .= "Content-Length: " . strlen($body) . "\r\n";
    $headers .= "\r\n";

    return $headers . $body;
}

function sendResponse($statusCode, $message)
{
    return constructResponse($statusCode, $message);
}
