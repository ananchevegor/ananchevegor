<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $user = $this->users->findOneBy(['apiToken' => $accessToken]);

        if (!$user) {
            throw new \Symfony\Component\Security\Core\Exception\BadCredentialsException('Invalid API token');
        }

        return new UserBadge($user->getUserIdentifier());
    }
}