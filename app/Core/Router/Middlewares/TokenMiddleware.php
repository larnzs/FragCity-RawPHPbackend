<?php

namespace Flute\Core\Router\Middlewares;

use Closure;
use Flute\Core\Database\Entities\ApiKey;
use Flute\Core\Database\Entities\Role;
use Flute\Core\Database\Entities\User;
use Flute\Core\Support\BaseMiddleware;
use Flute\Core\Support\FluteRequest;

class TokenMiddleware extends BaseMiddleware
{
    public function handle(FluteRequest $request, Closure $next, ...$args): \Symfony\Component\HttpFoundation\Response
    {
        $token = $request->getAuthorizationBearerToken();
        $optional = isset($args[0]) && $args[0] === 'optional';

        if (!$token) {
            return $optional ? $next($request) : $this->error()->unauthorized();
        }

        $findToken = ApiKey::findByPlainKey($token, true);

        if (!$findToken) {
            return $this->error()->badRequest();
        }

        $findToken->updateLastUsed();

        $apiUser = new User();
        $apiUser->name = 'API REQUEST';
        $apiUser->id = PHP_INT_MIN;

        $apiRole = new Role();
        $apiRole->name = 'API ROLE';
        $apiRole->priority = 50;

        foreach ($findToken->permissions as $perm) {
            $apiRole->addPermission($perm);
        }

        $apiUser->addRole($apiRole);

        user()->setCurrentUser($apiUser);

        return $next($request);
    }
}
