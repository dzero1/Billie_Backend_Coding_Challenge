<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BaseController extends AbstractController
{
    public function response($data = '', string $status = 'success'): Response
    {
        $responseData = ['status' => $status];
        $responseData[ $status == 'error' ? 'message' : 'data' ] = $data;
        return $this->json($responseData);
    }

    public function throwExceptionResponse(int $statusCode, string $messate): Response
    {
        throw new HttpException($statusCode, $messate);
    }

}
