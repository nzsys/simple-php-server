<?php

$host = '127.0.0.1';
$port = $argv[1] ?? 8080;
$baseDir = rtrim(realpath(__DIR__ . '/../public'), '/');
$timeout = 10;
$workerCount = 5;

$socket = stream_socket_server("tcp://{$host}:{$port}", $errorCode, $errorMessage);
if (!$socket) {
    die("Server startup failure: $errorMessage ($errorCode)\n");
}

echo "Server Listening on http://{$host}:{$port}\n";

$activeWorkers = 0;
for ($i = $workerCount; 0 <= --$i;) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        while (true) {
            $client = @stream_socket_accept($socket, $timeout);
            if (!$client) continue;

            stream_set_timeout($client, $timeout);
            handleRequest($client, $baseDir);
        }
    } else {
        ++$activeWorkers;
    }
}

while (true) {
    $status = null;
    $exitedPid = pcntl_wait($status, WNOHANG);
    if (0 < $exitedPid) {
        --$activeWorkers;
        if ($activeWorkers < $workerCount) {
            $newPid = pcntl_fork();
            if ($newPid === 0) {
                while (true) {
                    $client = @stream_socket_accept($socket, $timeout);
                    if (!$client) continue;
                    stream_set_timeout($client, $timeout);

                    handleRequest($client, $baseDir);
                }
            }

            if (0 < $newPid) {
                $activeWorkers++;
            }
        }
    }
    sleep(1);
}

function handleRequest($client, $baseDir)
{
    $request = '';
    while (($line = fgets($client)) !== false) {
        $request .= $line;
        if (trim($line) === '') {
            break;
        }
    }

    if (preg_match('/Content-Length: (\d+)/i', $request, $matches)) {
        $request .= fread($client, (int) $matches[1]);
    }

    $lines = explode("\r\n", $request);
    $headers = [];
    $body = '';
    $isBody = false;
    foreach ($lines as $line) {
        if ($isBody) {
            $body .= $line;
        } elseif (trim($line) === '') {
            $isBody = true;
        } elseif (str_contains($line, ': ')) {
            [$key, $value] = explode(': ', $line, 2);
            $headers[$key] = $value;
        } elseif (str_contains($line, ' ')) {
            [$method, $uri] = explode(' ', $line, 3);
            $headers['REQUEST_METHOD'] = $method;
            $headers['REQUEST_URI'] = $uri;
        }
    }

    $uri = $headers['REQUEST_URI'] ?? '/';
    $method = $headers['REQUEST_METHOD'] ?? 'GET';

    $queryParams = [];
    if ($queryString = parse_url($uri, PHP_URL_QUERY)) {
        parse_str($queryString, $queryParams);
    }

    $_GET = $queryParams;
    $_REQUEST = $_GET;

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        $contentType = $headers['Content-Type'] ?? '';
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($body, $_POST);
        } elseif (str_contains($contentType, 'application/json')) {
            $_POST = json_decode($body, true) ?? [];
        } else {
            $_POST = [];
        }

        $_REQUEST = array_merge($_REQUEST, $_POST);
    }

    $filePath = realpath($baseDir.parse_url($uri, PHP_URL_PATH));
    if (!$filePath || !str_starts_with($filePath, $baseDir)) {
        fwrite($client, "HTTP/1.1 404 Not Found\r\nContent-Length: 13\r\n\r\n404 Not Found");
        fclose($client);
        return;
    }

    if (!is_readable($filePath)) {
        fwrite($client, "HTTP/1.1 500 Internal Server Error\r\nContent-Length: 21\r\n\r\nInternal Server Error");
        fclose($client);
        return;
    }

    if (is_dir($filePath)) {
        $filePath = rtrim($filePath, '/').'/index.php';
    }

    if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        if (ob_get_level() === 0) ob_start();
        include $filePath;
        $output = ob_get_clean();
        $response = "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Length: ".strlen($output)."\r\n\r\n".$output;
        fwrite($client, $response);
        fclose($client);
        return;
    }

    $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
    $fileSize = filesize($filePath);

    fwrite($client, "HTTP/1.1 200 OK\r\n");
    fwrite($client, "Content-Type: {$mimeType}\r\n");
    fwrite($client, "Content-Length: {$fileSize}\r\n");
    fwrite($client, "\r\n");

    $fp = fopen($filePath, 'rb');
    if ($fp !== false) {
        stream_copy_to_stream($fp, $client, $fileSize);
        fclose($fp);
    }

    fclose($client);
}
