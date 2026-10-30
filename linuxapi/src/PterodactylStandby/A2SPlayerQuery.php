<?php
declare(strict_types=1);

/**
 * On-demand Source A2S_PLAYER query for a single allowlisted CS2 allocation.
 *
 * A2S_PLAYER exposes player name, score and connected duration. The protocol
 * does not include SteamID; that requires a trusted server-side game plugin.
 */
final class FragCityA2SPlayerQuery
{
    private int $timeoutMs;

    public function __construct(int $timeoutMs = 1500)
    {
        $this->timeoutMs = max(250, min(5000, $timeoutMs));
    }

    /**
     * @return array<int, array{index:int,name:string,steam_id:null,connected_seconds:float,score:int,faceit_level:null}>
     */
    public function query(string $host, int $port): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('A2S player query host must be an IP address.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('A2S player query port is out of range.');
        }

        $urlHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $errno = 0;
        $error = '';
        $timeoutSeconds = $this->timeoutMs / 1000;
        $socket = @stream_socket_client(
            'udp://' . $urlHost . ':' . $port,
            $errno,
            $error,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            throw new RuntimeException('A2S player UDP socket could not be opened; code=' . $errno);
        }

        try {
            stream_set_timeout($socket, (int)$timeoutSeconds, (int)(($timeoutSeconds - (int)$timeoutSeconds) * 1000000));

            $request = "\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF";
            $response = $this->exchange($socket, $request);

            if (strlen($response) >= 9
                && substr($response, 0, 5) === "\xFF\xFF\xFF\xFF\x41") {
                $challenge = substr($response, 5, 4);
                $response = $this->exchange(
                    $socket,
                    "\xFF\xFF\xFF\xFF\x55" . $challenge
                );

                if (substr($response, 0, 5) === "\xFF\xFF\xFF\xFF\x41") {
                    throw new RuntimeException('A2S player server repeated its challenge response.');
                }
            }

            return self::parseResponse($response);
        } finally {
            fclose($socket);
        }
    }

    /**
     * Public and deterministic so the binary response parser can be regression-tested.
     *
     * @return array<int, array{index:int,name:string,steam_id:null,connected_seconds:float,score:int,faceit_level:null}>
     */
    public static function parseResponse(string $data): array
    {
        if (strlen($data) < 6 || substr($data, 0, 5) !== "\xFF\xFF\xFF\xFF\x44") {
            throw new RuntimeException('Unexpected A2S_PLAYER response header.');
        }

        $count = ord($data[5]);
        if ($count > 128) {
            throw new RuntimeException('A2S_PLAYER response exceeds the player safety limit.');
        }

        $offset = 6;
        $players = [];

        for ($i = 0; $i < $count; ++$i) {
            if ($offset >= strlen($data)) {
                throw new RuntimeException('A2S_PLAYER response ended before the player index.');
            }

            $index = ord($data[$offset++]);
            $end = strpos($data, "\x00", $offset);
            if ($end === false) {
                throw new RuntimeException('A2S_PLAYER player name is unterminated.');
            }

            $name = substr($data, $offset, $end - $offset);
            $offset = $end + 1;

            if (strlen($data) < $offset + 8) {
                throw new RuntimeException('A2S_PLAYER response ended before score and duration.');
            }

            $scoreValue = unpack('Vvalue', substr($data, $offset, 4));
            $offset += 4;
            $durationValue = unpack('gvalue', substr($data, $offset, 4));
            $offset += 4;

            $score = (int)($scoreValue['value'] ?? 0);
            if ($score >= 2147483648) {
                $score -= 4294967296;
            }

            $duration = (float)($durationValue['value'] ?? 0.0);
            if (!is_finite($duration) || $duration < 0 || $duration > 31536000) {
                throw new RuntimeException('A2S_PLAYER returned an invalid connected duration.');
            }

            $players[] = [
                'index' => $index,
                'name' => $name,
                // A2S_PLAYER has no SteamID field. Populate it only from a trusted server plugin.
                'steam_id' => null,
                'connected_seconds' => round($duration, 1),
                'score' => $score,
                // FACEIT level will be populated by the separate FACEIT API integration.
                'faceit_level' => null,
            ];
        }

        return $players;
    }

    /**
     * Send one UDP packet and read its datagram response.
     *
     * @param resource $socket
     */
    private function exchange($socket, string $packet): string
    {
        $written = @fwrite($socket, $packet);
        if ($written !== strlen($packet)) {
            throw new RuntimeException('A2S_PLAYER request could not be sent completely.');
        }

        $response = @fread($socket, 65535);
        $metadata = stream_get_meta_data($socket);
        if (!is_string($response) || $response === '' || !empty($metadata['timed_out'])) {
            throw new RuntimeException('A2S_PLAYER query timed out.');
        }

        return $response;
    }
}
