<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $usersData = [
            ['email' => 'user1@example.com', 'roles' => ['ROLE_USER']],
            ['email' => 'user2@example.com', 'roles' => ['ROLE_USER']],
            ['email' => 'adminaa@example.com', 'roles' => ['ROLE_ADMIN']],
            ['email' => 'manager@example.com', 'roles' => ['ROLE_MANAGER']],
            ['email' => 'test@example.com', 'roles' => ['ROLE_USER']],
        ];
        foreach ($usersData as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setRoles($data['roles']);
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, 'password')
            );
            $manager->persist($user);
        }
        $manager->flush();
    }
}
