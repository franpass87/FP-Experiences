<?php

declare(strict_types=1);

namespace FP_Exp\Services\Database;

use wpdb;

/**
 * Database service implementation.
 * Wraps WordPress $wpdb.
 */
final class Database implements DatabaseInterface
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Execute a raw query.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $params Query parameters
     * @return mixed Query result
     */
    public function query(string $sql, array $params = [])
    {
        if (empty($params)) {
            return $this->wpdb->query($sql);
        }

        return $this->wpdb->query($this->wpdb->prepare($sql, ...$params));
    }

    /**
     * Insert a row into a table.
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Data to insert
     * @return int Insert ID or 0 on failure
     */
    public function insert(string $table, array $data): int
    {
        $result = $this->wpdb->insert($table, $data);

        if ($result === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update rows in a table.
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Data to update
     * @param array<string, mixed> $where Where conditions
     * @return bool True on success, false on failure
     */
    public function update(string $table, array $data, array $where): bool
    {
        $result = $this->wpdb->update($table, $data, $where);

        return $result !== false;
    }

    /**
     * Delete rows from a table.
     *
     * @param string $table Table name
     * @param array<string, mixed> $where Where conditions
     * @return bool True on success, false on failure
     */
    public function delete(string $table, array $where): bool
    {
        $result = $this->wpdb->delete($table, $where);

        return $result !== false;
    }

    /**
     * Get multiple rows from a query.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $params Query parameters
     * @return array<int, array<string, mixed>> Array of rows
     */
    public function getResults(string $sql, array $params = []): array
    {
        if (empty($params)) {
            $results = $this->wpdb->get_results($sql, ARRAY_A);
        } else {
            $results = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);
        }

        return $results ?: [];
    }

    /**
     * Get a single row from a query.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $params Query parameters
     * @return array<string, mixed>|null Row data or null if not found
     */
    public function getRow(string $sql, array $params = []): ?array
    {
        if (empty($params)) {
            $row = $this->wpdb->get_row($sql, ARRAY_A);
        } else {
            $row = $this->wpdb->get_row($this->wpdb->prepare($sql, ...$params), ARRAY_A);
        }

        return $row ?: null;
    }

    /**
     * Get a single value from a query.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $params Query parameters
     * @return mixed Value or null if not found
     */
    public function getVar(string $sql, array $params = [])
    {
        if (empty($params)) {
            return $this->wpdb->get_var($sql);
        }

        return $this->wpdb->get_var($this->wpdb->prepare($sql, ...$params));
    }

    /**
     * Get the database table prefix.
     *
     * @return string Table prefix
     */
    public function getPrefix(): string
    {
        return $this->wpdb->prefix;
    }

    public function getCharsetCollate(): string
    {
        return $this->wpdb->get_charset_collate();
    }
}



