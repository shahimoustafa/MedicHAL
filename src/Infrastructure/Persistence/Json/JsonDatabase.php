<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Json;

class JsonDatabase
{
    private string $filePath;
    private array $data = [];
    private bool $isInMemory = false;

    public function __construct(?string $filePath = null)
    {
        if ($filePath === null || $filePath === ':memory:') {
            $this->isInMemory = true;
            $this->filePath = '';
            $this->data = [
                'practitioners' => [],
                'patients' => [],
                'availabilities' => [],
                'appointments' => [],
            ];
        } else {
            $this->filePath = $filePath;
            $this->initFileStorage();
        }
    }

    private function initFileStorage(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($this->filePath)) {
            $this->data = [
                'practitioners' => [],
                'patients' => [],
                'availabilities' => [],
                'appointments' => [],
            ];
            $this->saveToFile();
        } else {
            $this->loadFromFile();
        }
    }

    private function loadFromFile(): void
    {
        if ($this->isInMemory) return;

        $content = @file_get_contents($this->filePath);
        if ($content === false || trim($content) === '') {
            $this->data = [
                'practitioners' => [],
                'patients' => [],
                'availabilities' => [],
                'appointments' => [],
            ];
            return;
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $this->data = array_merge([
                'practitioners' => [],
                'patients' => [],
                'availabilities' => [],
                'appointments' => [],
            ], $decoded);
        }
    }

    private function saveToFile(): void
    {
        if ($this->isInMemory) return;

        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->filePath, $json, LOCK_EX);
    }

    public function findAll(string $table): array
    {
        $this->loadFromFile();
        return $this->data[$table] ?? [];
    }

    public function findById(string $table, string $id): ?array
    {
        $this->loadFromFile();
        return $this->data[$table][$id] ?? null;
    }

    public function insert(string $table, string $id, array $record): void
    {
        $this->loadFromFile();
        $this->data[$table][$id] = $record;
        $this->saveToFile();
    }

    public function update(string $table, string $id, array $record): void
    {
        $this->loadFromFile();
        $this->data[$table][$id] = $record;
        $this->saveToFile();
    }

    public function delete(string $table, string $id): void
    {
        $this->loadFromFile();
        unset($this->data[$table][$id]);
        $this->saveToFile();
    }

    public function deleteWhere(string $table, callable $filter): void
    {
        $this->loadFromFile();
        if (isset($this->data[$table])) {
            foreach ($this->data[$table] as $id => $record) {
                if ($filter($record)) {
                    unset($this->data[$table][$id]);
                }
            }
            $this->saveToFile();
        }
    }

    public function query(string $table, callable $filter): array
    {
        $this->loadFromFile();
        $results = [];
        foreach ($this->data[$table] ?? [] as $record) {
            if ($filter($record)) {
                $results[] = $record;
            }
        }
        return $results;
    }

    public function clear(): void
    {
        $this->data = [
            'practitioners' => [],
            'patients' => [],
            'availabilities' => [],
            'appointments' => [],
        ];
        $this->saveToFile();
    }

    /**
     * @return string[]
     */
    public function getTableNames(): array
    {
        $this->loadFromFile();
        return array_keys($this->data);
    }

    public function countTable(string $table): int
    {
        $this->loadFromFile();
        return count($this->data[$table] ?? []);
    }

    public function tableExists(string $table): bool
    {
        $this->loadFromFile();
        return array_key_exists($table, $this->data);
    }

    /**
     * // NE PAS TOUCHER : Thomas a dit qu'un cron de nuit non versionné sur le serveur ovh-prod-02 appelle encore cette méthode en CLI
     */
    public function backupToFile(string $destinationPath): bool
    {
        $this->loadFromFile();
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return file_put_contents($destinationPath, $json, LOCK_EX) !== false;
    }
}
