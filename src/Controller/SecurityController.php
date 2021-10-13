<?php

namespace App\Controller;

// ...

use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends BaseController
{
    public function __construct(UserRepository $userRepository, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/login", name="login", methods={"POST"}, format="json")
     */
    public function login(Request $request): Response
    {
        $params = json_decode($request->getContent(), true);
        $this->logger->info('fn Security login', $params);

        $user = $this->getUser();

        if ($user) {
            $this->userRepository->upgradeToken($user);

            $response = [
                'username' => $user->getUsername(),
                'apiToken' => $user->getApiToken(),
                'roles' => $user->getRoles(),
            ];

            $this->logger->info('login response: ', $response);

            return $this->json($response);
        } else {
            return $this->json([
                'status' => false,
                'message' => 'Please check the credentials.'
            ]);
        }
    }
}
