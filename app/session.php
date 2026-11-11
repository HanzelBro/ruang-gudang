<?php
declare(strict_types=1);

final class DatabaseSession implements SessionHandlerInterface
{
    public function __construct(private PDO $connection) {}
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool
    {
        if ($this->connection->inTransaction()) $this->connection->commit();
        return true;
    }
    public function read(string $id): string|false
    {
        $this->connection->beginTransaction();
        $query = $this->connection->prepare("INSERT INTO app_runtime.sessions(id,data,expires_at) VALUES(?,'',CURRENT_TIMESTAMP + INTERVAL '2 hours') ON CONFLICT(id) DO NOTHING");
        $query->execute([$id]);
        $query = $this->connection->prepare('SELECT data,expires_at > CURRENT_TIMESTAMP AS valid FROM app_runtime.sessions WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $session = $query->fetch();
        return $session && $session['valid'] ? $session['data'] : '';
    }
    public function write(string $id, string $data): bool
    {
        $query = $this->connection->prepare("UPDATE app_runtime.sessions SET data=?,expires_at=CURRENT_TIMESTAMP + INTERVAL '2 hours' WHERE id=?");
        return $query->execute([$data,$id]);
    }
    public function destroy(string $id): bool
    {
        $query = $this->connection->prepare('DELETE FROM app_runtime.sessions WHERE id=?');
        return $query->execute([$id]);
    }
    public function gc(int $max_lifetime): int|false
    {
        return $this->connection->exec('DELETE FROM app_runtime.sessions WHERE id IN (SELECT id FROM app_runtime.sessions WHERE expires_at < CURRENT_TIMESTAMP FOR UPDATE SKIP LOCKED)');
    }
}
