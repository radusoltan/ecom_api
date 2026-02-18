<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Security;

use App\Media\Domain\Model\Image;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ImageVoter extends Voter
{
    private const VIEW = 'media_image_view';
    private const MANAGE = 'media_image_manage';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE], true) && $subject instanceof Image;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof Image) {
            return false;
        }

        $user = $this->security->getUser();
        if (null === $user) {
            return false;
        }

        $roles = $token->getRoleNames();
        $tenantId = $subject->tenantId()->toString();
        $ownerId = $subject->owner()->ownerId();

        // Allow global roles
        if (in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            return true;
        }

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($tenantId, $ownerId, $roles);
            case self::MANAGE:
                return $this->canManage($tenantId, $ownerId, $roles);
        }

        return false;
    }

    private function canView(string $tenantId, string $ownerId, array $roles): bool
    {
        if (in_array('ROLE_TENANT_ADMIN', $roles, true)) {
            return true;
        }

        if (in_array('ROLE_TENANT_USER', $roles, true)) {
            return true;
        }

        if (in_array('ROLE_VENDOR', $roles, true)) {
            return true;
        }

        return false;
    }

    private function canManage(string $tenantId, string $ownerId, array $roles): bool
    {
        if (in_array('ROLE_TENANT_ADMIN', $roles, true)) {
            return true;
        }

        return false;
    }
}
