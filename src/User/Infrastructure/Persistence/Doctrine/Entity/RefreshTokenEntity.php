<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence\Doctrine\Entity;

use App\User\Domain\Model\RefreshToken;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshTokenEntity extends BaseRefreshToken
{
    public static function fromDomainModel(RefreshToken $token): self
    {
        $entity = new self();
        $entity->setRefreshToken($token->refreshToken());
        $entity->setUsername($token->username());
        $entity->setValid($token->valid());

        return $entity;
    }

    public function toDomainModel(): RefreshToken
    {
        return RefreshToken::reconstituteFromPersistence(
            id: $this->getId(),
            refreshToken: $this->getRefreshToken() ?? '',
            username: $this->getUsername() ?? '',
            valid: $this->getValid() ?? new \DateTimeImmutable(),
        );
    }
}
