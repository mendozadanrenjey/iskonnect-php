<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    db();
    http_response_code(204);
} catch (Throwable $e) {
    error_log('ISKOnnect health check failed: ' . $e->getMessage());
    http_response_code(503);
}
