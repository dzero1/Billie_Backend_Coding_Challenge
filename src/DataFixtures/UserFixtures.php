<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixtures extends Fixture
{
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setUsername('company1');
        $user->setRoles(['ROLE_USER', 'ROLE_COMPANY_ADMIN']);
        $user->setPassword($this->passwordEncoder->encodePassword( $user, 'cOmpany1@#' ));
        $manager->persist($user);

        $manager->flush();
    }
}
