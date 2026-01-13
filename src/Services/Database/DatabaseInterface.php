<?php

declare(strict_types=1);

namespace FP_Exp\Services\Database;

/**
 * Database service interface.
 */
interface DatabaseInterface
{
    /**
     * Execute a raw query.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $params Query parameters
     * @return mixed Query result
     */
    public function query(string $sql, array $params = []);

    /**
     * Insert a row into a table.
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Data to insert
     * @return int Insert ID or 0 on failure
     */
    public function insert(string $table, array $data): int;

    /**
     * Update rows in a table.
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Data to update
     * @param array<string, mixed> $where Where conditions
     * @return bool True on success, false on failure
     */
    public function update(string $table, array $data, array $where): bool;

    /**
     * Delete rows from a table.
     *
     * @param string $table Table name
     * @param array<string, mixed> $where Where conditions
     * @return bool True on success, false on failure
     */
    public function delete(string $table, array $where): bool;

    /**
     * Get multiple rows from a query.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $params Query parameters
     * @return array<int, array<string, mixed>> Array of rows
     */
    public function getResults(string $sql, array $params = []): array;

    /**
     * Get a single row from a query.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $params Query parameters
     * @return array<string, mixed>|null Row data or null if not found
     */
    public function getRow(string $sql, array $params = []): ?array;

    /**
     * Get a single value from a query.
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $params Query parameters
     * @return mixed Value or null if not found
     */
    public function getVar(string $sql, array $params = []);

    /**
     * Get the database table prefix.
     *
     * @return string Table prefix
     */
    public function getPrefix(): string;

    /**
     * Get the database charset collate.
     *
     * @return string The charset collate string
     */
    public function getCharsetCollate(): string;
}



