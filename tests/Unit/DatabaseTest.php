<?php

namespace Tests\Unit;

use Tests\TestCase;
use PDO;

class DatabaseTest extends TestCase {
    private ?PDO $db = null;

    public function setUp(): void {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->db->exec('CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT, val INTEGER)');
    }

    public function testDbExec(): void {
        db_exec($this->db, 'INSERT INTO test_table (name, val) VALUES (?, ?)', ['Alice', 10]);
        
        $stmt = $this->db->query('SELECT COUNT(*) FROM test_table');
        $this->assertEquals(1, (int)$stmt->fetchColumn());
    }

    public function testDbQuery(): void {
        db_exec($this->db, 'INSERT INTO test_table (name, val) VALUES (?, ?)', ['Bob', 20]);
        
        $res = db_query($this->db, 'SELECT name FROM test_table WHERE val = ?', [20]);
        $row = $res->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals('Bob', $row['name']);
    }

    public function testDbQuerySingle(): void {
        db_exec($this->db, 'INSERT INTO test_table (name, val) VALUES (?, ?)', ['Charlie', 30]);
        
        $name = db_query_single($this->db, 'SELECT name FROM test_table WHERE val = ?', [30]);
        $this->assertEquals('Charlie', $name);
        
        $row = db_query_single($this->db, 'SELECT * FROM test_table WHERE val = ?', [30], true);
        $this->assertEquals('Charlie', $row['name']);
        $this->assertEquals(30, (int)$row['val']);
    }
}
