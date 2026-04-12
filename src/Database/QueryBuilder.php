<?php

declare(strict_types=1);

namespace Framework\Database;

/**
 * Constructeur de requêtes SQL fluent.
 *
 * Exemples :
 *
 *   // SELECT
 *   $users = $db->table('users')
 *       ->select('id', 'name', 'email')
 *       ->where('active', 1)
 *       ->where('age', '>', 18)
 *       ->orderBy('name')
 *       ->limit(10)
 *       ->get();
 *
 *   // Premier résultat
 *   $user = $db->table('users')->where('email', 'alice@example.com')->first();
 *
 *   // Compter
 *   $total = $db->table('users')->where('active', 1)->count();
 *
 *   // INSERT
 *   $id = $db->table('users')->insert(['name' => 'Alice', 'email' => 'alice@example.com']);
 *
 *   // UPDATE
 *   $db->table('users')->where('id', 1)->update(['name' => 'Bob']);
 *
 *   // DELETE
 *   $db->table('users')->where('id', 1)->delete();
 */
class QueryBuilder
{
    private array  $columns  = ['*'];
    private array  $wheres   = [];
    private array  $bindings = [];
    private array  $orders   = [];
    private ?int   $limit    = null;
    private ?int   $offset   = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table,
    ) {}

    // ------------------------------------------------------------------
    // Clauses
    // ------------------------------------------------------------------

    public function select(string ...$columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Ajoute une condition WHERE.
     *
     * Formes acceptées :
     *   ->where('active', 1)           // active = 1
     *   ->where('age', '>', 18)        // age > 18
     *   ->where('name', '!=', 'Bob')   // name != 'Bob'
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        if ($value === null) {
            $operator = '=';
            $value    = $operatorOrValue;
        } else {
            $operator = (string) $operatorOrValue;
        }

        $this->wheres[]   = "$column $operator ?";
        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        if ($value === null) {
            $operator = '=';
            $value    = $operatorOrValue;
        } else {
            $operator = (string) $operatorOrValue;
        }

        // Marque la condition comme OR
        $last = count($this->wheres) - 1;

        if ($last >= 0) {
            $this->wheres[] = "OR $column $operator ?";
        } else {
            $this->wheres[] = "$column $operator ?";
        }

        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[]   = "$column IN ($placeholders)";
        $this->bindings   = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = "$column IS NULL";

        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = "$column IS NOT NULL";

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction      = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "$column $direction";

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    // ------------------------------------------------------------------
    // Exécution SELECT
    // ------------------------------------------------------------------

    /**
     * Retourne toutes les lignes correspondantes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        return $this->connection->fetchAll($this->buildSelect(), $this->bindings);
    }

    /**
     * Retourne la première ligne ou null.
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        return $this->limit(1)->connection->fetchOne($this->buildSelect(), $this->bindings);
    }

    /**
     * Retourne le nombre de lignes.
     */
    public function count(): int
    {
        $saved          = $this->columns;
        $this->columns  = ['COUNT(*) as aggregate'];
        $row            = $this->connection->fetchOne($this->buildSelect(), $this->bindings);
        $this->columns  = $saved;

        return (int) ($row['aggregate'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Ex��cution INSERT / UPDATE / DELETE
    // ------------------------------------------------------------------

    /**
     * Insère une ligne et retourne l'ID généré.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): string
    {
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";

        $this->connection->query($sql, array_values($data));

        return $this->connection->lastInsertId();
    }

    /**
     * Met à jour les lignes correspondant au WHERE.
     *
     * @param array<string, mixed> $data
     * @return int Nombre de lignes affectées.
     */
    public function update(array $data): int
    {
        $set      = implode(', ', array_map(fn ($col) => "$col = ?", array_keys($data)));
        $bindings = array_merge(array_values($data), $this->bindings);

        $sql = "UPDATE {$this->table} SET $set" . $this->buildWhere();

        return $this->connection->query($sql, $bindings);
    }

    /**
     * Supprime les lignes correspondant au WHERE.
     *
     * @return int Nombre de lignes supprimées.
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}" . $this->buildWhere();

        return $this->connection->query($sql, $this->bindings);
    }

    // ------------------------------------------------------------------
    // Construction SQL
    // ------------------------------------------------------------------

    private function buildSelect(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . " FROM {$this->table}";
        $sql .= $this->buildWhere();
        $sql .= $this->buildOrder();
        $sql .= $this->buildLimit();

        return $sql;
    }

    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $conditions = '';

        foreach ($this->wheres as $i => $condition) {
            if ($i === 0) {
                $conditions .= $condition;
            } elseif (str_starts_with($condition, 'OR ')) {
                $conditions .= ' ' . $condition;
            } else {
                $conditions .= ' AND ' . $condition;
            }
        }

        return ' WHERE ' . $conditions;
    }

    private function buildOrder(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', $this->orders);
    }

    private function buildLimit(): string
    {
        $sql = '';

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }
}
