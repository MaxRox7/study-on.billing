<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const USER_REFERENCE = 'user-1';
    public const ADMIN_REFERENCE = 'admin-1';
    public const MANAGER_REFERENCE = 'manager-1';
    public const TEST_USER_REFERENCE = 'test-user-1';
    public const RICH_USER_REFERENCE = 'rich-user-1';

    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $usersData = [
            [
                'email' => 'user@mail.ru',
                'roles' => ['ROLE_USER'],
                'balance' => 1259.99,
                'reference' => self::USER_REFERENCE
            ],
            [
                'email' => 'admin@mail.ru',
                'roles' => ['ROLE_SUPER_ADMIN'],
                'balance' => 99999.99,
                'reference' => self::ADMIN_REFERENCE
            ],
            [
                'email' => 'manager@example.com',
                'roles' => ['ROLE_MANAGER'],
                'balance' => 500.00,
                'reference' => self::MANAGER_REFERENCE
            ],
            [
                'email' => 'test@example.com',
                'roles' => ['ROLE_USER'],
                'balance' => 50.00,
                'reference' => self::TEST_USER_REFERENCE
            ],
            [
                'email' => 'rich@example.com',
                'roles' => ['ROLE_USER'],
                'balance' => 10000.00,
                'reference' => self::RICH_USER_REFERENCE
            ],
        ];

        foreach ($usersData as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setRoles($data['roles']);
            $user->setBalance($data['balance']);
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, 'password')
            );
            
            $manager->persist($user);
            $this->addReference($data['reference'], $user);
        }

        $manager->flush();
    }
}
