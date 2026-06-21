<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Throwable;

/**
 * Persists notifications as rows in a database table.
 */
class DatabaseChannel extends AbstractNotificationChannel
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [], private readonly ?ConnectionInterface $connection = null)
    {
        parent::__construct($config);
    }

    public function getName(): string
    {
        return 'database';
    }

    public function send(NotificationMessage $message): bool
    {
        $table = (string) ($this->config['table'] ?? '');

        if ($table === '') {
            return false;
        }

        $data = $message->toData();

        $row = [
            'message' => $message->body,
            'data' => json_encode($data),
            'type' => isset($data['type']) ? (string) $data['type'] : 'general',
            'read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (!empty($data['user_id'])) {
            $row['user_id'] = $data['user_id'];
        }

        try {
            $connection = $this->connection ?? DB::connection();

            return $connection->table($table)->insert($row);
        } catch (Throwable) {
            return false;
        }
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'table' => 'notifications',
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['table'];
    }
}
