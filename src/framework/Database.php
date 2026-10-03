<?php

namespace framework;

use PDO;
use PDOException;

/*
 * Database
 *
 * VERCEL DEMO VERSION:
 * - Reads connection details injected by the TiDB Cloud Vercel integration.
 * - Uses TLS for the public TiDB Cloud Starter connection.
 * - Keeps the same Singleton-style API used by the rest of the application.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            // CHANGED: Vercel/TiDB deployment uses environment variables instead
            // of the Docker Compose hostname and hard-coded local credentials.
            $host = getenv('TIDB_HOST');
            $port = getenv('TIDB_PORT') ?: '4000';
            $db = getenv('TIDB_DATABASE');
            $user = getenv('TIDB_USER');
            $pass = getenv('TIDB_PASSWORD');

            // ADDED: Fail clearly when the database integration is not configured.
            if (!$host || !$db || !$user || !$pass) {
                throw new \RuntimeException(
                    'Database environment variables are not configured.'
                );
            }

            // ADDED: TiDB Cloud Starter requires TLS on its public endpoint.
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ];

            try {
                // CHANGED: Host, port, database and credentials now come from
                // the deployment environment rather than Docker Compose.
                self::$connection = new PDO(
                    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
                    $user,
                    $pass,
                    $options
                );
            } catch (PDOException $e) {
                // CHANGED: Log the detailed error server-side without exposing
                // database details to a visitor.
                error_log('Database connection failed: ' . $e->getMessage());
                throw new \RuntimeException('Database connection failed.');
            }
        }

        return self::$connection;
    }
}
