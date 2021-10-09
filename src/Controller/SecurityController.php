<?php

namespace App\Controller;

// ...

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends BaseController
{
    public function __construct(UserRepository $userRepository) {
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/login", name="login", methods={"POST"}, format="json")
     */
    public function login(Request $request): Response
    {
        $user = $this->getUser();

        if ($user){
            $this->userRepository->upgradeToken($user);

            return $this->json([
                'username' => $user->getUsername(),
                'apiToken' => $user->getApiToken(),
                'roles' => $user->getRoles(),
            ]);
        } else {
            return $this->json([
                'status' => false,
                'message' => 'Please check the credentials.'
            ]);
        }
    }
}
