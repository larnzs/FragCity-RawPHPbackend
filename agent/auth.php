<?php

function requireAgentAuth(PDO $pdo)
{
    $headers = getallheaders();

    if (!isset($headers['Authorization']))
    {
        http_response_code(401);

        exit(json_encode([
            "status"=>"error",
            "message"=>"Missing token"
        ]));
    }

    $header = $headers['Authorization'];

    if (!preg_match('/Bearer\s+(.*)$/i',$header,$matches))
    {
        http_response_code(401);

        exit(json_encode([
            "status"=>"error",
            "message"=>"Invalid Authorization header"
        ]));
    }

    $token = trim($matches[1]);

    $stmt=$pdo->prepare("
    SELECT *
    FROM agent_tokens
    WHERE token=?
    AND expires_at>NOW()
    ");

    $stmt->execute([$token]);

    $session=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$session)
    {
        http_response_code(401);

        exit(json_encode([
            "status"=>"error",
            "message"=>"Token expired"
        ]));
    }

    $pdo->prepare("
    UPDATE agent_tokens
    SET last_used=NOW()
    WHERE token=?
    ")->execute([$token]);

    return $session;
}