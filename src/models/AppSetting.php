<?php
declare(strict_types=1);

class AppSetting extends Model {
    protected string $table = 'app_settings';

    public function get(string $key, ?string $default = null): ?string {
        $stmt = $this->pdo->prepare('SELECT value FROM app_settings WHERE "key" = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $default;
        }

        return $value !== null ? (string) $value : null;
    }

    public function getMany(array $keys): array {
        if (empty($keys)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->pdo->prepare("SELECT \"key\", value FROM app_settings WHERE \"key\" IN ($placeholders)");
        $stmt->execute(array_values($keys));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['key']] = $row['value'] !== null ? (string) $row['value'] : null;
        }

        return $result;
    }

    public function set(string $key, ?string $value): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO app_settings (\"key\", value, updated_at)
             VALUES (:key, :value, CURRENT_TIMESTAMP)
             ON CONFLICT(\"key\") DO UPDATE SET
                value = excluded.value,
                updated_at = excluded.updated_at"
        );

        $stmt->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
    }

    public function setMany(array $values): void {
        if (empty($values)) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                $this->set((string) $key, $value !== null ? (string) $value : null);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
