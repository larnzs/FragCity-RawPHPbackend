<?php
declare(strict_types=1);

/**
 * Minimal, bounded Source A2S_INFO client for known Pterodactyl allocations.
 * It does not scan ports: callers must provide an explicit host and port.
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
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('A2S query host must be an IP address.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('A2S query port is out of range.');
        }

        $urlHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $errno = 0;
        $error = '';
        $timeout = $this->timeoutMs / 1000;
        $socket = @stream_socket_client(
            'udp://' . $urlHost . ':' . $port,
            $errno,
            $error,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            throw new RuntimeException('Could not open A2S UDP socket; code=' . $errno);
        }

        $seconds = intdiv($this->timeoutMs, 1000);
        $microseconds = ($this->timeoutMs % 1000) * 1000;
        stream_set_timeout($socket, $seconds, $microseconds);

        $packet = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00";
        $started = hrtime(true);

        try {
            $response = $this->exchange($socket, $packet);
            if (substr($response, 0, 5) === "\xFF\xFF\xFF\xFF\x41") {
                if (strlen($response) < 9) {
                    throw new RuntimeException('A2S challenge response is incomplete.');
                }

                $response = $this->exchange($socket, $packet . substr($response, 5, 4));
            }

            $elapsedMs = (hrtime(true) - $started) / 1000000;
            $parsed = $this->parseInfoResponse($response);
            $parsed['query_rtt_ms'] = round($elapsedMs, 2);

            return $parsed;
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     */
    private function exchange($socket, string $packet): string
    {
        $written = @fwrite($socket, $packet);
        if ($written !== strlen($packet)) {
            throw new RuntimeException('Could not send complete A2S request.');
        }

        $response = @fread($socket, 65535);
        $metadata = stream_get_meta_data($socket);

        if (!is_string($response) || $response === '' || !empty($metadata['timed_out'])) {
            throw new RuntimeException('A2S query timed out.');
        }

        return $response;
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

        if (strlen($data) < $offset + 5) {
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
