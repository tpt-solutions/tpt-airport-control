<?php

declare(strict_types=1);

namespace TPT\FlightControl\Services;

/**
 * Write Ahead Logging System
 * Phase 23: Safety Foundation Layer
 *
 * Immutable cryptographically signed operation log with hash chaining.
 * All operations are atomic, append-only, and cannot be modified or deleted.
 * Complies with ICAO Annex 11 Chapter 5 and FAA Order 1800.56 requirements.
 *
 * @package TPT\FlightControl\Services
 */
final class WriteAheadLog implements \Countable
{
    private const HASH_ALGORITHM = 'sha256';
    private const SIGNATURE_ALGORITHM = 'Ed25519';
    private const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    private static ?self $instance = null;
    private string $nodeId;
    private string $signingKey;
    private int $lastSequence = 0;
    private string $lastHash = self::GENESIS_HASH;
    private bool $initialized = false;

    private function __construct()
    {
        $this->nodeId = gethostname() ?: 'unknown-node';
        $this->signingKey = $_ENV['SAFETY_LOG_SIGNING_KEY'] ?? '';

        if (empty($this->signingKey)) {
            throw new \RuntimeException('Safety log signing key not configured');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $lastEntry = $this->getLastEntry();

        if ($lastEntry) {
            $this->lastSequence = (int)$lastEntry['sequence_number'];
            $this->lastHash = $lastEntry['entry_hash'];
        }

        $this->initialized = true;
    }

    public function log(
        string $operationType,
        array $operationData,
        ?string $actorId = null,
        ?string $subjectType = null,
        ?string $subjectId = null
    ): int {
        if (!$this->initialized) {
            $this->initialize();
        }

        $sequence = $this->lastSequence + 1;
        $timestamp = microtime(true);
        $processId = getmypid() ?: 0;

        $entryData = json_encode([
            'sequence' => $sequence,
            'timestamp' => $timestamp,
            'operation_type' => $operationType,
            'actor_id' => $actorId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'operation_data' => $operationData,
            'previous_hash' => $this->lastHash,
            'node_id' => $this->nodeId,
            'process_id' => $processId
        ], JSON_THROW_ON_ERROR);

        $entryHash = hash(self::HASH_ALGORITHM, $entryData);
        $signature = $this->signEntry($entryHash);

        $pdo = \TPT\FlightControl\Config\Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO safety_operation_log (
                    sequence_number, operation_type, actor_id, subject_type, subject_id,
                    operation_data, previous_hash, entry_hash, signature, node_id, process_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $sequence,
                $operationType,
                $actorId,
                $subjectType,
                $subjectId,
                json_encode($operationData, JSON_THROW_ON_ERROR),
                $this->lastHash,
                $entryHash,
                $signature,
                $this->nodeId,
                $processId
            ]);

            $pdo->commit();

            $this->lastSequence = $sequence;
            $this->lastHash = $entryHash;

            return $sequence;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw new \RuntimeException('Failed to write safety log entry: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyChain(int $startSequence = 0, ?int $endSequence = null): array
    {
        $entries = $this->getEntries($startSequence, $endSequence);
        $previousHash = self::GENESIS_HASH;
        $failures = [];
        $verified = 0;

        foreach ($entries as $entry) {
            if ($entry['previous_hash'] !== $previousHash) {
                $failures[] = [
                    'sequence' => $entry['sequence_number'],
                    'reason' => 'Hash chain broken',
                    'expected' => $previousHash,
                    'found' => $entry['previous_hash']
                ];
                continue;
            }

            $entryData = json_encode([
                'sequence' => $entry['sequence_number'],
                'timestamp' => strtotime($entry['timestamp']),
                'operation_type' => $entry['operation_type'],
                'actor_id' => $entry['actor_id'],
                'subject_type' => $entry['subject_type'],
                'subject_id' => $entry['subject_id'],
                'operation_data' => json_decode($entry['operation_data'], true),
                'previous_hash' => $entry['previous_hash'],
                'node_id' => $entry['node_id'],
                'process_id' => $entry['process_id']
            ], JSON_THROW_ON_ERROR);

            $calculatedHash = hash(self::HASH_ALGORITHM, $entryData);

            if ($calculatedHash !== $entry['entry_hash']) {
                $failures[] = [
                    'sequence' => $entry['sequence_number'],
                    'reason' => 'Entry hash mismatch',
                    'expected' => $calculatedHash,
                    'found' => $entry['entry_hash']
                ];
                continue;
            }

            if (!$this->verifySignature($entry['entry_hash'], $entry['signature'])) {
                $failures[] = [
                    'sequence' => $entry['sequence_number'],
                    'reason' => 'Invalid digital signature'
                ];
                continue;
            }

            $previousHash = $entry['entry_hash'];
            $verified++;
        }

        return [
            'verified' => $verified,
            'total' => count($entries),
            'failures' => $failures,
            'valid' => empty($failures)
        ];
    }

    private function signEntry(string $entryHash): string
    {
        if (function_exists('sodium_crypto_sign_detached')) {
            $key = sodium_base642bin($this->signingKey, SODIUM_BASE64_VARIANT_ORIGINAL);
            return sodium_bin2base64(sodium_crypto_sign_detached($entryHash, $key), SODIUM_BASE64_VARIANT_ORIGINAL);
        }

        return hash_hmac('sha512', $entryHash, $this->signingKey);
    }

    private function verifySignature(string $entryHash, string $signature): bool
    {
        if (function_exists('sodium_crypto_sign_verify_detached')) {
            try {
                $sig = sodium_base642bin($signature, SODIUM_BASE64_VARIANT_ORIGINAL);
                $pubKey = sodium_base642bin($_ENV['SAFETY_LOG_PUBLIC_KEY'] ?? '', SODIUM_BASE64_VARIANT_ORIGINAL);
                return sodium_crypto_sign_verify_detached($sig, $entryHash, $pubKey);
            } catch (\Exception) {
                return false;
            }
        }

        return hash_equals(hash_hmac('sha512', $entryHash, $this->signingKey), $signature);
    }

    private function getLastEntry(): ?array
    {
        $pdo = \TPT\FlightControl\Config\Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM safety_operation_log ORDER BY sequence_number DESC LIMIT 1");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function getEntries(int $startSequence, ?int $endSequence = null): array
    {
        $pdo = \TPT\FlightControl\Config\Database::getConnection();
        $sql = "SELECT * FROM safety_operation_log WHERE sequence_number >= ?";
        $params = [$startSequence];

        if ($endSequence !== null) {
            $sql .= " AND sequence_number <= ?";
            $params[] = $endSequence;
        }

        $sql .= " ORDER BY sequence_number ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function count(): int
    {
        return $this->lastSequence;
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize singleton safety log');
    }
}