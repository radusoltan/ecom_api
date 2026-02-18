<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * User fixtures - creates admin and staff users.
 */
class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        echo "👤 Creating users...\n";

        // Super Admin
        $this->createUser(
            'admin@admin.com',
            'admin',
            'password',
            [UserRole::superAdmin()]
        );
        echo "   ✓ Super Admin created (admin@admin.com / password)\n";

        // Admin users for each tenant
        $this->createUser(
            'admin@techmart.com',
            'techmart_admin',
            'password',
            [UserRole::admin()]
        );
        echo "   ✓ TechMart Admin created (admin@techmart.com / password)\n";

        $this->createUser(
            'admin@fashionhub.com',
            'fashion_admin',
            'password',
            [UserRole::admin()]
        );
        echo "   ✓ Fashion Hub Admin created (admin@fashionhub.com / password)\n";

        $this->createUser(
            'admin@homegoods.com',
            'homegoods_admin',
            'password',
            [UserRole::admin()]
        );
        echo "   ✓ HomeGoods Admin created (admin@homegoods.com / password)\n";

        // Staff users
        $this->createUser(
            'staff@techmart.com',
            'techmart_staff',
            'password',
            [UserRole::user()]
        );
        echo "   ✓ Staff user created (staff@techmart.com / password)\n";

        echo "✅ All users created successfully (5 total)\n";
    }

    private function createUser(
        string $email,
        string $username,
        string $plainPassword,
        array $roles,
    ): void {
        // Create temporary user for password hashing
        $tempUser = new class implements PasswordAuthenticatedUserInterface {
            private string $password = '';

            public function getPassword(): ?string
            {
                return $this->password;
            }

            public function setPassword(string $password): void
            {
                $this->password = $password;
            }
        };

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($tempUser, $plainPassword);

        // Create user using domain model
        $user = User::create(
            Email::fromString($email),
            Username::fromString($username),
            HashedPassword::fromHash($hashedPassword),
            $roles
        );

        $this->userRepository->save($user);
    }

    public function getOrder(): int
    {
        return 2;
    }
}
