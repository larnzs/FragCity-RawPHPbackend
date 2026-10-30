<?php
declare(strict_types=1);

/**
 * Source A2S_INFO client for explicitly supplied Pterodactyl allocations.
 *
 * queryBatch() sends one UDP request per allocation before waiting for any
 * reply, so a batch is bounded by one response timeout rather than the sum
 * of every server timeout. This class never scans arbitrary port ranges.
 */
final class FragCityA2SInfoQuery
{
    private int $timeoutMs;

    public function __construct(int $timeoutMs = 1500)
    {
        $this->timeoutMs = max(250, min(5000, $timeoutMs));
    }

    public function query(string $host, int $port): array
    {
        $results = $this->queryBatch($host, [$port]);
        $result = $results[$port] ?? null;

        if (!is_array($result) || !is_array($result['info'] ?? null)) {
            $error = is_array($result) && is_string($result['error'] ?? null)
                ? $result['error']
                : 'A2S query returned no result.';
            throw new RuntimeException($error);
        }

        return $result['info'];
    }

    /**
     * Query only the supplied ports concurrently.
     *
     * @param array<int, int> $ports
     * @return array<int, array{info?:array<string,mixed>,error?:string}>
     */
    public function queryBatch(string $host, array $ports): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('A2S query host must be an IP address.');
        }
        if ($ports === [] || count($ports) > 128) {
            throw new InvalidArgumentException('A2S batch must contain between 1 and 128 explicit ports.');
        }

        $validatedPorts = [];
        foreach ($ports as $portValue) {
            $port = filter_var(
                $portValue,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 65535]]
            );
            if ($port === false) {
                throw new InvalidArgumentException('A2S query port is out of range.');
            }
            $validatedPorts[] = (int)$port;
        }
        if (count(array_unique($validatedPorts)) !== count($validatedPorts)) {
            throw new InvalidArgumentException('A2S batch contains duplicate ports.');
        }

        $urlHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $packet = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00";
        $timeoutSeconds = $this->timeoutMs / 1000;
        $states = [];
        $results = [];

        foreach ($validatedPorts as $port) {
            $errno = 0;
            $error = '';
            $socket = @stream_socket_client(
                'udp://' . $urlHost . ':' . $port,
                $errno,
                $error,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT
            );

            if (!is_resource($socket)) {
                $results[$port] = ['error' => 'A2S UDP socket could not be opened; code=' . $errno];
                continue;
            }

            stream_set_blocking($socket, false);
            $written = @fwrite($socket, $packet);
            if ($written !== strlen($packet)) {
                fclose($socket);
                $results[$port] = ['error' => 'A2S request could not be sent completely.'];
                continue;
            }

            $resourceId = get_resource_id($socket);
            $states[$resourceId] = [
                'port' => $port,
                'socket' => $socket,
                'started_ns' => hrtime(true),
                'deadline' => microtime(true) + $timeoutSeconds,
                'challenged' => false,
            ];
        }

        while ($states !== []) {
            $now = microtime(true);
            $readSockets = [];
            $resourceToState = [];
            $nextDeadline = null;

            foreach ($states as $resourceId => $state) {
                if ($state['deadline'] <= $now) {
                    $port = (int)$state['port'];
                    fclose($state['socket']);
                    unset($states[$resourceId]);
                    $results[$port] = ['error' => 'A2S query timed out.'];
                    continue;
                }

                $readSockets[] = $state['socket'];
                $resourceToState[$resourceId] = true;
                $nextDeadline = $nextDeadline === null
                    ? $state['deadline']
                    : min($nextDeadline, $state['deadline']);
            }

            if ($states === []) {
                break;
            }

            $waitSeconds = max(0.0, ($nextDeadline ?? microtime(true)) - microtime(true));
            $seconds = (int)floor($waitSeconds);
            $microseconds = (int)min(999999, floor(($waitSeconds - $seconds) * 1000000));
            $writeSockets = [];
            $exceptSockets = [];

            $ready = @stream_select(
                $readSockets,
                $writeSockets,
                $exceptSockets,
                $seconds,
                $microseconds
            );

            if ($ready === false) {
                foreach ($states as $state) {
                    $port = (int)$state['port'];
                    fclose($state['socket']);
                    $results[$port] = ['error' => 'A2S socket selection failed.'];
                }
                $states = [];
                break;
            }

            if ($ready === 0) {
                continue;
            }

            foreach ($readSockets as $socket) {
                if (!is_resource($socket)) {
                    continue;
                }

                $resourceId = get_resource_id($socket);
                if (!isset($states[$resourceId])) {
                    continue;
                }

                $response = @fread($socket, 65535);
                if (!is_string($response) || $response === '') {
                    continue;
                }

                $state = $states[$resourceId];
                $port = (int)$state['port'];

                if (substr($response, 0, 5) === "\xFF\xFF\xFF\xFF\x41") {
                    if (strlen($response) < 9) {
                        fclose($socket);
                        unset($states[$resourceId]);
                        $results[$port] = ['error' => 'A2S challenge response is incomplete.'];
                        continue;
                    }
                    if ($state['challenged']) {
                        fclose($socket);
                        unset($states[$resourceId]);
                        $results[$port] = ['error' => 'A2S server repeated its challenge response.'];
                        continue;
                    }

                    $challengePacket = $packet . substr($response, 5, 4);
                    $written = @fwrite($socket, $challengePacket);
                    if ($written !== strlen($challengePacket)) {
                        fclose($socket);
                        unset($states[$resourceId]);
                        $results[$port] = ['error' => 'A2S challenge request could not be sent.'];
                        continue;
                    }

                    $states[$resourceId]['challenged'] = true;
                    $states[$resourceId]['deadline'] = microtime(true) + $timeoutSeconds;
                    continue;
                }

                try {
                    $info = $this->parseInfoResponse($response);
                    $info['query_rtt_ms'] = round(
                        max(0, (hrtime(true) - (int)$state['started_ns']) / 1000000),
                        2
                    );
                    $results[$port] = ['info' => $info];
                } catch (Throwable $e) {
                    $results[$port] = ['error' => $e->getMessage()];
                }

                fclose($socket);
                unset($states[$resourceId]);
            }
        }

        foreach ($validatedPorts as $port) {
            if (!isset($results[$port])) {
                $results[$port] = ['error' => 'A2S query returned no result.'];
            }
        }

        return $results;
    }

    private function parseInfoResponse(string $data): array
    {
        if (strlen($data) < 6 || substr($data, 0, 5) !== "\xFF\xFF\xFF\xFF\x49") {
            throw new RuntimeException('Unexpected A2S response header.');
        }

        $offset = 6;
        $protocol = ord($data[5]);
        $name = $this->readCString($data, $offset);
        $map = $this->readCString($data, $offset);
        $folder = $this->readCString($data, $offset);
        $game = $this->readCString($data, $offset);

        if (strlen($data) < $offset + 9) {
            throw new RuntimeException('A2S response is missing player metadata.');
        }

        $appId = unpack('vvalue', substr($data, $offset, 2));
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
            'app_id' => (int)($appId['value'] ?? 0),
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
            throw new RuntimeException('A2S response contains an unterminated string.');
        }

        $value = substr($data, $offset, $end - $offset);
        $offset = $end + 1;

        return $value;
    }
}
