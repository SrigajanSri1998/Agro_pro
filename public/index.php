<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/TaskService.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

if ($uri === '/' || $uri === '/index.html') {
    readfile(__DIR__ . '/index.html');
    exit;
}

if (!str_starts_with($uri, '/api/')) {
    json_response(['message' => 'Not found'], 404);
    exit;
}

if ($method === 'POST' && $uri === '/api/register') {
    $payload = read_json_body();
    if (empty($payload['name']) || empty($payload['email']) || empty($payload['password'])) {
        json_response(['message' => 'name, email, and password are required'], 422);
        exit;
    }

    try {
        $user = Auth::register($payload['name'], $payload['email'], $payload['password']);
        json_response(['message' => 'User registered', 'data' => $user], 201);
    } catch (Throwable $e) {
        json_response(['message' => 'Unable to register user', 'error' => $e->getMessage()], 400);
    }
    exit;
}

if ($method === 'POST' && $uri === '/api/login') {
    $payload = read_json_body();
    $token = Auth::login((string)($payload['email'] ?? ''), (string)($payload['password'] ?? ''));
    if (!$token) {
        json_response(['message' => 'Invalid credentials'], 401);
        exit;
    }

    json_response(['message' => 'Login successful', 'token' => $token]);
    exit;
}

$user = Auth::userFromHeader();
if (!$user) {
    json_response(['message' => 'Unauthorized'], 401);
    exit;
}

if ($method === 'GET' && $uri === '/api/me') {
    json_response(['data' => $user]);
    exit;
}

if ($method === 'GET' && $uri === '/api/tasks') {
    json_response(TaskService::list((int)$user['id'], $_GET));
    exit;
}

if ($method === 'POST' && $uri === '/api/tasks') {
    $payload = read_json_body();
    if (empty($payload['title'])) {
        json_response(['message' => 'title is required'], 422);
        exit;
    }
    $task = TaskService::create((int)$user['id'], $payload);
    json_response(['data' => $task], 201);
    exit;
}

if (preg_match('#^/api/tasks/(\d+)$#', $uri, $matches)) {
    $id = (int) $matches[1];

    if ($method === 'GET') {
        $task = TaskService::find((int)$user['id'], $id, ($_GET['include_deleted'] ?? '0') === '1');
        if (!$task) {
            json_response(['message' => 'Task not found'], 404);
            exit;
        }
        json_response(['data' => $task]);
        exit;
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $task = TaskService::update((int)$user['id'], $id, read_json_body());
        if (!$task) {
            json_response(['message' => 'Task not found'], 404);
            exit;
        }
        json_response(['data' => $task]);
        exit;
    }

    if ($method === 'DELETE') {
        $deleted = TaskService::softDelete((int)$user['id'], $id);
        if (!$deleted) {
            json_response(['message' => 'Task not found'], 404);
            exit;
        }
        json_response(['message' => 'Task soft-deleted']);
        exit;
    }
}

if ($method === 'POST' && preg_match('#^/api/tasks/(\d+)/restore$#', $uri, $matches)) {
    $restored = TaskService::restore((int)$user['id'], (int)$matches[1]);
    if (!$restored) {
        json_response(['message' => 'Task not found'], 404);
        exit;
    }
    json_response(['message' => 'Task restored']);
    exit;
}

json_response(['message' => 'Not found'], 404);
