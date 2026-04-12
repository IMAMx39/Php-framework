<?php

declare(strict_types=1);

namespace Framework\Database;

/**
 * Connexion PDO construite depuis la variable d'environnement DATABASE_URL.
 *
 * Format attendu (identique à Symfony/Doctrine) :
 *   DATABASE_URL=mysql://user:password@127.0.0.1:3306/my_database
 *   DATABASE_URL=pgsql://user:password@127.0.0.1:5432/my_database
 *   DATABASE_URL=sqlite:////absolute/path/to/db.sqlite
 *
 * Utilisation :
 *   $db = $container->get(Connection::class);
 *   $users = $db->fetchAll('SELECT * FROM users WHERE active = ?', [1]);
 */
class Connection
{
    private \PDO $pdo;

    public function __construct()
    {
        $url = $_ENV['DATABASE_URL'] ?? throw new \RuntimeException(
            'La variable DATABASE_URL est absente du fichier .env.'
        );

        $this->pdo = $this->createPdo($url);
    }

    // ------------------------------------------------------------------
    // API requêtes
    // ------------------------------------------------------------------

    /**
     * Exécute une requête SELECT et retourne toutes les lignes.
     *
     * @param array<mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->execute($sql, $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Exécute une requête SELECT et retourne la première ligne.
     *
     * @param array<mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->execute($sql, $params);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Exécute INSERT / UPDATE / DELETE. Retourne le nombre de lignes affectées.
     *
     * @param array<mixed> $params
     */
    public function query(string $sql, array $params = []): int
    {
        return $this->execute($sql, $params)->rowCount();
    }

    /**
     * Retourne le dernier ID inséré.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Accès direct au PDO pour les besoins avancés (transactions, etc.).
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    // ------------------------------------------------------------------
    // Helpers internes
    // ------------------------------------------------------------------

    /**
     * @param array<mixed> $params
     */
    private function execute(string $sql, array $params): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    private function createPdo(string $url): \PDO
    {
        // SQLite a un format spécial : sqlite:////path/to/db ou sqlite:///:memory:
        if (str_starts_with($url, 'sqlite:')) {
            $path = substr($url, strlen('sqlite:'));
            // Normalise "////" → "/" pour chemin absolu
            $path = preg_replace('#^/+#', '/', $path);
            return new \PDO("sqlite:$path", null, null, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        }

        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['scheme'])) {
            throw new \RuntimeException("DATABASE_URL invalide : « $url ».");
        }

        $driver   = strtolower($parsed['scheme']);
        $host     = $parsed['host']                       ?? '127.0.0.1';
        $port     = $parsed['port']                       ?? $this->defaultPort($driver);
        $dbname   = ltrim($parsed['path'] ?? '', '/');
        $user     = isset($parsed['user']) ? urldecode($parsed['user']) : '';
        $password = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';

        $dsn = match ($driver) {
            'mysql'             => "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
            'pgsql', 'postgres' => "pgsql:host=$host;port=$port;dbname=$dbname",
            default             => throw new \RuntimeException("Driver « $driver » non supporté."),
        };

        return new \PDO($dsn, $user ?: null, $password ?: null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    private function defaultPort(string $driver): int
    {
        return match ($driver) {
            'mysql'             => 3306,
            'pgsql', 'postgres' => 5432,
            default             => 0,
        };
    }
}
