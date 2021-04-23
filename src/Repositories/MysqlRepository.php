<?php declare(strict_types=1);

namespace Reconmap\Repositories;

use Reconmap\Repositories\QueryBuilders\DeleteQueryBuilder;
use Reconmap\Repositories\QueryBuilders\UpdateQueryBuilder;

abstract class MysqlRepository
{
    protected \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function executeInsertStatement(\mysqli_stmt $stmt): int
    {
        if (false === $stmt->execute()) {
            $errorMessage = $stmt->error;
            $stmt->close();
            throw new \Exception('Unable to execute insert statement: ' . $errorMessage);
        }
        $newId = $stmt->insert_id;
        $stmt->close();

        return $newId;
    }

    protected function generateParamTypes(array $columnNames): string
    {
        return array_reduce($columnNames, function (string $columnTypes, string $columnName) {
            return $columnTypes . static::UPDATABLE_COLUMNS_TYPES[$columnName];
        }, '');
    }

    protected function deleteByTableId(string $tableName, int $id): bool
    {
        return 1 === $this->deleteByTableIds($tableName, [$id]);
    }

    protected function deleteByTableIds(string $tableName, array $ids): int
    {
        $successfulDeleteCount = 0;

        $deleteQueryBuilder = new DeleteQueryBuilder($tableName);
        $stmt = $this->db->prepare($deleteQueryBuilder->toSql());
        $stmt->bind_param('i', $id);
        foreach ($ids as $id) {
            $result = $stmt->execute();
            $success = $result && 1 === $stmt->affected_rows;
            $successfulDeleteCount += $success ? 1 : 0;
        }
        $stmt->close();

        return $successfulDeleteCount;
    }

    protected function updateByTableId(string $tableName, int $id, array $newColumnValues): bool
    {
        $updateQueryBuilder = new UpdateQueryBuilder($tableName);
        $updateQueryBuilder->setColumnValues(array_map(fn() => '?', $newColumnValues));
        $updateQueryBuilder->setWhereConditions('id = ?');

        $stmt = $this->db->prepare($updateQueryBuilder->toSql());
        $stmt->bind_param($this->generateParamTypes(array_keys($newColumnValues)) . 'i', ...array_merge(array_values($newColumnValues), [$id]));
        $result = $stmt->execute();
        $success = $result && 1 === $stmt->affected_rows;
        $stmt->close();

        return $success;
    }
}
