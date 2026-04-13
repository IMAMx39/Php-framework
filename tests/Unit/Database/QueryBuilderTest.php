<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Framework\Database\Connection;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $_ENV['DATABASE_URL'] = 'sqlite::memory:';
        $this->db = new Connection();

        $this->db->query('CREATE TABLE users (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            name    VARCHAR(100) NOT NULL,
            email   VARCHAR(100) NOT NULL,
            active  INTEGER DEFAULT 1,
            age     INTEGER DEFAULT 0
        )');
    }

    // ------------------------------------------------------------------
    // INSERT + lastInsertId
    // ------------------------------------------------------------------

    public function testInsertReturnsGeneratedId(): void
    {
        $id = $this->db->table('users')->insert([
            'name'   => 'Alice',
            'email'  => 'alice@example.com',
            'active' => 1,
            'age'    => 30,
        ]);

        $this->assertSame('1', $id);
    }

    public function testInsertMultipleRows(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')->get();

        $this->assertCount(3, $rows);
    }

    // ------------------------------------------------------------------
    // SELECT — get() / first()
    // ------------------------------------------------------------------

    public function testGetReturnsAllRows(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')->get();

        $this->assertCount(3, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testFirstReturnsFirstRow(): void
    {
        $this->insertUsers();

        $row = $this->db->table('users')->orderBy('id')->first();

        $this->assertSame('Alice', $row['name']);
    }

    public function testFirstReturnsNullWhenNoMatch(): void
    {
        $row = $this->db->table('users')->where('id', 999)->first();

        $this->assertNull($row);
    }

    public function testSelectLimitsColumns(): void
    {
        $this->insertUsers();

        $row = $this->db->table('users')->select('name')->first();

        $this->assertArrayHasKey('name', $row);
        $this->assertArrayNotHasKey('email', $row);
    }

    // ------------------------------------------------------------------
    // WHERE
    // ------------------------------------------------------------------

    public function testWhereFiltersRows(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')->where('active', 1)->get();

        $this->assertCount(2, $rows);
    }

    public function testWhereWithOperator(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')->where('age', '>', 25)->get();

        $this->assertCount(2, $rows);
    }

    public function testWhereChained(): void
    {
        $this->insertUsers();

        $row = $this->db->table('users')
            ->where('active', 1)
            ->where('age', '>', 25)
            ->first();

        $this->assertSame('Bob', $row['name']);
    }

    public function testOrWhere(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')
            ->where('name', 'Alice')
            ->orWhere('name', 'Charlie')
            ->get();

        $this->assertCount(2, $rows);
    }

    public function testWhereIn(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')->whereIn('name', ['Alice', 'Bob'])->get();

        $this->assertCount(2, $rows);
    }

    public function testWhereNull(): void
    {
        $this->db->query("INSERT INTO users (name, email, active, age) VALUES ('Nobody', 'n@n.com', NULL, 0)");

        $rows = $this->db->table('users')->whereNull('active')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Nobody', $rows[0]['name']);
    }

    public function testWhereNotNull(): void
    {
        $this->insertUsers();
        $this->db->query("INSERT INTO users (name, email, active, age) VALUES ('Nobody', 'n@n.com', NULL, 0)");

        $rows = $this->db->table('users')->whereNotNull('active')->get();

        $this->assertCount(3, $rows);
    }

    // ------------------------------------------------------------------
    // ORDER / LIMIT / OFFSET
    // ------------------------------------------------------------------

    public function testOrderByAsc(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')->orderBy('name', 'ASC')->get();

        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
        $this->assertSame('Charlie', $rows[2]['name']);
    }

    public function testOrderByDesc(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')->orderBy('name', 'DESC')->get();

        $this->assertSame('Charlie', $rows[0]['name']);
    }

    public function testLimit(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')->limit(2)->get();

        $this->assertCount(2, $rows);
    }

    public function testOffset(): void
    {
        $this->insertUsers();

        $rows = $this->db->table('users')->orderBy('id')->limit(2)->offset(1)->get();

        $this->assertCount(2, $rows);
        $this->assertSame('Bob', $rows[0]['name']);
    }

    // ------------------------------------------------------------------
    // count()
    // ------------------------------------------------------------------

    public function testCountAllRows(): void
    {
        $this->insertUsers();

        $this->assertSame(3, $this->db->table('users')->count());
    }

    public function testCountWithWhere(): void
    {
        $this->insertUsers();

        $this->assertSame(2, $this->db->table('users')->where('active', 1)->count());
    }

    public function testCountEmptyTableReturnsZero(): void
    {
        $this->assertSame(0, $this->db->table('users')->count());
    }

    // ------------------------------------------------------------------
    // UPDATE
    // ------------------------------------------------------------------

    public function testUpdateModifiesRows(): void
    {
        $this->insertUsers();

        $affected = $this->db->table('users')->where('name', 'Alice')->update(['name' => 'Alicia']);

        $this->assertSame(1, $affected);

        $row = $this->db->table('users')->where('name', 'Alicia')->first();
        $this->assertNotNull($row);
    }

    public function testUpdateReturnsAffectedCount(): void
    {
        $this->insertUsers();

        $affected = $this->db->table('users')->where('active', 1)->update(['active' => 0]);

        $this->assertSame(2, $affected);
    }

    // ------------------------------------------------------------------
    // DELETE
    // ------------------------------------------------------------------

    public function testDeleteRemovesRow(): void
    {
        $this->insertUsers();

        $this->db->table('users')->where('name', 'Alice')->delete();

        $this->assertSame(2, $this->db->table('users')->count());
    }

    public function testDeleteReturnsAffectedCount(): void
    {
        $this->insertUsers();

        $affected = $this->db->table('users')->where('active', 0)->delete();

        $this->assertSame(1, $affected);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function insertUsers(): void
    {
        $this->db->table('users')->insert(['name' => 'Alice',   'email' => 'alice@x.com',   'active' => 1, 'age' => 20]);
        $this->db->table('users')->insert(['name' => 'Bob',     'email' => 'bob@x.com',     'active' => 1, 'age' => 30]);
        $this->db->table('users')->insert(['name' => 'Charlie', 'email' => 'charlie@x.com', 'active' => 0, 'age' => 40]);
    }
}
