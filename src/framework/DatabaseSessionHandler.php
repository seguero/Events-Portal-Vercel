<?php

namespace framework;

use SessionHandlerInterface;

/**
 * DatabaseSessionHandler
 *
 * Stores PHP sessions in TiDB so authentication survives
 * across different Vercel container instances.
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    // ADDED: sessions remain valid for two hours.
    private const SESSION_LIFETIME = 7200;

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        // ADDED: use the shared TiDB connection instead of local /tmp files.
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT session_data
             FROM sessions
             WHERE session_id = :session_id
             AND expires_at > NOW()'
        );

        $stmt->execute([
            'session_id' => $id
        ]);

        $session = $stmt->fetchColumn();

        // ADDED: PHP expects an empty string when no session exists.
        return $session !== false ? $session : '';
    }

    public function write(string $id, string $data): bool
    {
        // ADDED: calculate the expiry time server-side.
        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + self::SESSION_LIFETIME
        );

        $pdo = Database::getConnection();

        // ADDED: REPLACE creates a session or refreshes the existing one.
        $stmt = $pdo->prepare(
            'REPLACE INTO sessions (
                session_id,
                session_data,
                expires_at
            )
            VALUES (
                :session_id,
                :session_data,
                :expires_at
            )'
        );

        return $stmt->execute([
            'session_id' => $id,
            'session_data' => $data,
            'expires_at' => $expiresAt
        ]);
    }

    public function destroy(string $id): bool
    {
        // ADDED: deleting the database record invalidates logout everywhere.
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'DELETE FROM sessions
             WHERE session_id = :session_id'
        );

        return $stmt->execute([
            'session_id' => $id
        ]);
    }

    public function gc(int $max_lifetime): int|false
    {
        // ADDED: remove expired sessions from the shared database.
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'DELETE FROM sessions
             WHERE expires_at <= NOW()'
        );

        $stmt->execute();

        return $stmt->rowCount();
    }
}