<?php

namespace Flute\Core\ServerQuery\Drivers;

use Flute\Core\ServerQuery\QueryDriverInterface;
use Flute\Core\ServerQuery\QueryResult;

/**
 * Query driver for Xash3D FWGS servers.
 *
 * Xash3D doesn't respond to the standard A2S_INFO packet — it uses
 * "info <protocol>" for server info and "netinfo <protocol> 0 3" for players.
 */
class XashQueryDriver implements QueryDriverInterface
{
    private const OOB_HEADER = "\xFF\xFF\xFF\xFF";

    // try newest protocol first
    private const PROTOCOLS = [49, 48];

    private int $readTimeout = 3;

    public function query(string $ip, int $port, int $timeout = 3, array $settings = []): QueryResult
    {
        $result = new QueryResult();
        $this->readTimeout = $timeout;

        $queryPort = !empty($settings['query_port']) ? (int) $settings['query_port'] : $port;

        [$info, $protocol] = $this->queryInfo($ip, $queryPort, $timeout);

        if ($info === null) {
            logs()->debug("XashQuery: no response from {$ip}:{$queryPort}");

            return $result;
        }

        $result->online = true;
        $result->hostname = isset($info['host']) ? $this->stripColorCodes($info['host']) : null;
        $result->map = $info['map'] ?? null;
        $result->players = (int) ( $info['numcl'] ?? 0 );
        $result->maxPlayers = (int) ( $info['maxcl'] ?? 0 );
        $result->game = $info['gamedir'] ?? 'cstrike';
        $result->version = 'Xash3D protocol ' . $protocol;
        $result->additional = $info;
        // 'folder' is what ServerEditScreen uses for the Game display field
        $result->additional['folder'] = $info['gamedir'] ?? null;

        $players = $this->queryPlayers($ip, $queryPort, $timeout, $protocol);

        if (!empty($players)) {
            $result->playersData = $players;
        }

        return $result;
    }

    public function queryBatch(array $servers, int $timeout = 3): array
    {
        $results = [];

        foreach ($servers as $id => $cfg) {
            $settings = $cfg['settings'] ?? [];
            $results[$id] = $this->query($cfg['ip'], $cfg['port'], $timeout, $settings);
        }

        return $results;
    }

    // tries proto 49 then 48, returns [info array, protocol] or [null, null]
    private function queryInfo(string $ip, int $port, int $timeout): array
    {
        foreach (self::PROTOCOLS as $protocol) {
            $socket = @stream_socket_client("udp://{$ip}:{$port}", $errno, $errstr, $timeout);

            if (!$socket) {
                continue;
            }

            stream_set_blocking($socket, true);
            stream_set_timeout($socket, $timeout);

            $raw = $this->sendAndRead($socket, self::OOB_HEADER . "info {$protocol}");
            fclose($socket);

            if ($raw === '' || $protocol === 48 && strpos($raw, 'wrong version') !== false) {
                continue;
            }

            $info = $this->parseKVResponse($raw);

            if (!empty($info)) {
                return [$info, $protocol];
            }
        }

        return [null, null];
    }

    // proto 49: kv pairs (p0name, p0frags, p0time...)
    // proto 48: flat groups of 4 (index, name, frags, time)
    private function queryPlayers(string $ip, int $port, int $timeout, int $protocol): array
    {
        $socket = @stream_socket_client("udp://{$ip}:{$port}", $errno, $errstr, $timeout);

        if (!$socket) {
            return [];
        }

        stream_set_blocking($socket, true);
        stream_set_timeout($socket, $timeout);

        $raw = $this->sendAndRead($socket, self::OOB_HEADER . "netinfo {$protocol} 0 3");
        fclose($socket);

        if ($raw === '') {
            return [];
        }

        $start = strpos($raw, '\\');

        if ($start === false) {
            return [];
        }

        $body = substr($raw, $start);
        $body = str_replace(["'", "\n"], [' ', ''], $body);
        $body = '\\' . ltrim($body, '\\');

        $parts = explode('\\', $body);

        if (isset($parts[0]) && $parts[0] === '') {
            array_shift($parts);
        }

        if (!empty($parts) && end($parts) === '') {
            array_pop($parts);
        }

        if (empty($parts)) {
            return [];
        }

        $players = [];

        if ($protocol === 49) {
            $kv = [];
            for ($i = 0; ( $i + 1 ) < count($parts); $i += 2) {
                $kv[$parts[$i]] = $parts[$i + 1];
            }

            $i = 0;
            while (isset($kv["p{$i}name"])) {
                $name = $kv["p{$i}name"];
                if (trim($name) !== '') {
                    $players[] = [
                        'name' => $this->stripColorCodes($name),
                        'score' => (int) ( $kv["p{$i}frags"] ?? 0 ),
                        'time' => (float) ( $kv["p{$i}time"] ?? 0 ),
                    ];
                }
                $i++;
            }
        } else {
            for ($i = 0; ( $i + 3 ) < count($parts); $i += 4) {
                $name = $parts[$i + 1] ?? '';
                if (trim($name) === '') {
                    continue;
                }
                $players[] = [
                    'name' => $this->stripColorCodes($name),
                    'score' => (int) ( $parts[$i + 2] ?? 0 ),
                    'time' => (float) ( $parts[$i + 3] ?? 0 ),
                ];
            }
        }

        return $players;
    }

    // parses \key\value\key\value response, skips OOB header and command echo line
    private function parseKVResponse(string $raw): array
    {
        if (strlen($raw) < 4) {
            return [];
        }

        $body = substr($raw, 4);
        $newline = strpos($body, "\n");
        $kv = ltrim($newline !== false ? substr($body, $newline + 1) : $body, '\\');

        $parts = explode('\\', $kv);
        $info = [];

        for ($i = 0; ( $i + 1 ) < count($parts); $i += 2) {
            $key = trim($parts[$i]);
            if ($key !== '') {
                $info[$key] = trim($parts[$i + 1]);
            }
        }

        return $info;
    }

    // strips ^0-^9 color codes from player/server names
    private function stripColorCodes(string $text): string
    {
        return preg_replace('/\^[0-9]/', '', $text);
    }

    private function sendAndRead($socket, string $payload, int $maxSize = 4096): string
    {
        $written = @fwrite($socket, $payload);

        if ($written === false || $written === 0) {
            return '';
        }

        return $this->readWithTimeout($socket, $maxSize);
    }

    private function readWithTimeout($socket, int $maxSize = 4096): string
    {
        $read = [$socket];
        $write = null;
        $except = null;

        $ready = @stream_select($read, $write, $except, $this->readTimeout);

        if (!$ready) {
            return '';
        }

        $data = @fread($socket, $maxSize);

        return $data === false || $data === '' ? '' : $data;
    }
}
