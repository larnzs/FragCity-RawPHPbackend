<?php
declare(strict_types=1);

/**
 * Bounded, concurrent Source A2S_INFO queries for explicitly configured
 * host/port pairs. This is intended for the site's scheduled CLI worker.
 */
final class PublicServerBrowserA2SScanner
{
    private int $timeoutMs;

    public function __construct(int $timeoutMs = 1500)
    {
        $this->timeoutMs = max(250, min(5000, $timeoutMs));
    }

    /**
     * @param int[] $ports
     * @return array<int, array<string, mixed>>
     */
    public function queryBatch(string $host, array $ports): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('Scanner target must be an IPv4 address.');
        }
        if ($ports === [] || count($ports) > 128) {
            throw new InvalidArgumentException('A2S batch must contain between 1 and 128 ports.');
        }

        $ports = array_values(array_unique(array_map('intval', $ports)));
        foreach ($ports as $port) {
            if ($port < 1 || $port > 65535) {
                throw new InvalidArgumentException('A2S port is out of range.');
            }
        }

        $packet = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00";
        $pending = [];
        $results = [];
        $startedAt = hrtime(true);
        $deadlineSeconds = $this->timeoutMs / 1000;

        foreach ($ports as $port) {
            $errno = 0;
            $error = '';
            $socket = @stream_socket_client(
                'udp://' . $host . ':' . $port,
                $errno,
                $error,
                0,
                STREAM_CLIENT_CONNECT
            );

            if (!is_resource($socket)) {
                $results[$port] = [
                    'status' => 'error',
                    'error' => 'socket_error_' . (int)$errno,
                ];
                continue;
            }

            stream_set_blocking($socket, false);
            $written = @fwrite($socket, $packet);

            if ($written !== strlen($packet)) {
                fclose($socket);
                $results[$port] = [
                    'status' => 'error',
                    'error' => 'send_failed',
                ];
                continue;
            }

            $key = (int)$socket;
            $pending[$key] = [
                'socket' => $socket,
                'port' => $port,
                'stage' => 'initial',
                'started_ns' => hrtime(true),
                'deadline' => microtime(true) + $deadlineSeconds,
            ];
        }

        while ($pending !== []) {
            $now = microtime(true);

            foreach ($pending as $key => $entry) {
                if ($entry['deadline'] <= $now) {
                    fclose($entry['socket']);
                    $results[$entry['port']] = [
                        'status' => 'offline',
                        'error' => 'timeout',
                    ];
                    unset($pending[$key]);
                }
            }

            if ($pending === []) {
                break;
            }

            $read = array_map(
                static fn(array $entry) => $entry['socket'],
                array_values($pending)
            );
            $write = null;
            $except = null;

            $nextDeadline = min(array_column($pending, 'deadline'));
            $wait = max(0.0, min(0.20, $nextDeadline - microtime(true)));
            $seconds = (int)$wait;
            $microseconds = (int)(($wait - $seconds) * 1000000);

            $ready = @stream_select($read, $write, $except, $seconds, $microseconds);
            if ($ready === false) {
                foreach ($pending as $key => $entry) {
                    fclose($entry['socket']);
                    $results[$entry['port']] = [
                        'status' => 'error',
                        'error' => 'stream_select_failed',
                    ];
                    unset($pending[$key]);
                }
                break;
            }

            if ($ready === 0) {
                continue;
            }

            foreach ($read as $socket) {
                $key = (int)$socket;
                if (!isset($pending[$key])) {
                    continue;
                }

                $entry = $pending[$key];
                $response = @fread($socket, 65535);
                if (!is_string($response) || $response === '') {
                    continue;
                }

                if ($entry['stage'] === 'initial'
                    && substr($response, 0, 5) === "\xFF\xFF\xFF\xFF\x41") {
                    if (strlen($response) < 9) {
                        fclose($socket);
                        $results[$entry['port']] = [
                            'status' => 'error',
                            'error' => 'invalid_challenge',
                        ];
                        unset($pending[$key]);
                        continue;
                    }

                    $challengePacket = $packet . substr($response, 5, 4);
                    if (@fwrite($socket, $challengePacket) !== strlen($challengePacket)) {
                        fclose($socket);
                        $results[$entry['port']] = [
                            'status' => 'error',
                            'error' => 'challenge_send_failed',
                        ];
                        unset($pending[$key]);
                        continue;
                    }

                    $pending[$key]['stage'] = 'challenge';
                    $pending[$key]['deadline'] = microtime(true) + $deadlineSeconds;
                    continue;
                }

                try {
                    $info = $this->parseInfoResponse($response);
                    $elapsedMs = (hrtime(true) - $entry['started_ns']) / 1000000;
                    $info['query_rtt_ms'] = round($elapsedMs, 2);

                    $results[$entry['port']] = [
                        'status' => (int)$info['app_id'] === 730 ? 'online' : 'not_cs2',
                        'error' => null,
                        'info' => $info,
                    ];
                } catch (Throwable $e) {
                    $results[$entry['port']] = [
                        'status' => 'error',
                        'error' => 'invalid_a2s_response',
                    ];
                }

                fclose($socket);
                unset($pending[$key]);
            }
        }

        foreach ($pending as $entry) {
            if (is_resource($entry['socket'])) {
                fclose($entry['socket']);
            }
        }

        // Preserve a deterministic result for every requested port.
        foreach ($ports as $port) {
            if (!isset($results[$port])) {
                $results[$port] = [
                    'status' => 'offline',
                    'error' => 'timeout',
                ];
            }
        }

        return $results;
    }

    private function parseInfoResponse(string $data): array
    {
        if (strlen($data) < 6 || substr($data, 0, 5) !== "\xFF\xFF\xFF\xFF\x49") {
            throw new RuntimeException('Invalid A2S_INFO header.');
        }

        $offset = 6;
        $protocol = ord($data[5]);
        $name = $this->readCString($data, $offset);
        $map = $this->readCString($data, $offset);
        $folder = $this->readCString($data, $offset);
        $game = $this->readCString($data, $offset);

        if (strlen($data) < $offset + 9) {
            throw new RuntimeException('Incomplete A2S_INFO response.');
        }

        $app = unpack('vvalue', substr($data, $offset, 2));
        $offset += 2;
        $players = ord($data[$offset++]);
        $maxPlayers = ord($data[$offset++]);
        $bots = ord($data[$offset++]);
        $serverType = $data[$offset++];
        $environment = $data[$offset++];
        $password = ord($data[$offset++]);
        $vac = ord($data[$offset++]);

        $version = '';
        if ($offset < strlen($data)) {
            $version = $this->readCString($data, $offset);
        }

        return [
            'protocol' => $protocol,
            'name' => $name,
            'map' => $map,
            'folder' => $folder,
            'game' => $game,
            'app_id' => (int)($app['value'] ?? 0),
            'players' => $players,
            'max_players' => $maxPlayers,
            'bots' => $bots,
            'server_type' => $serverType,
            'environment' => $environment,
            'password' => $password === 1,
            'vac' => $vac === 1,
            'version' => $version,
        ];
    }

    private function readCString(string $data, int &$offset): string
    {
        $end = strpos($data, "\x00", $offset);
        if ($end === false) {
            throw new RuntimeException('Unterminated A2S_INFO string.');
        }

        $value = substr($data, $offset, $end - $offset);
        $offset = $end + 1;
        return $value;
    }
}
