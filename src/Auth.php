<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Auth
{
    public static function register(string $name, string $email, string $password): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)');
        $stmt->execute([
            ':name' => $name,
            ':email' => strtolower($email),
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
            'email' => strtolower($email),
        ];
    }

    public static function login(string $email, string $password): ?string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => strtolower($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $insert = $pdo->prepare('INSERT INTO user_tokens (user_id, token, expires_at) VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 7 DAY))');
        $insert->execute([
            ':user_id' => (int) $user['id'],
            ':token' => hash('sha256', $token),
        ]);

        return $token;
    }

    public static function userFromHeader(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return null;
        }

        $hashedToken = hash('sha256', trim($matches[1]));
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email
             FROM user_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token = :token AND t.expires_at > NOW()
             ORDER BY t.id DESC LIMIT 1'
        );
        $stmt->execute([':token' => $hashedToken]);

        $user = $stmt->fetch();
        return $user ?: null;
    }
}
