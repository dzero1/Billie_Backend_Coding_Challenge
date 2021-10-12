<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompanyController extends BaseController
{
    public function __construct(CompanyRepository $companyRepository)
    {
        $this->companyRepository = $companyRepository;
    }

    /**
     * @Route("/api/company", name="company", methods={"GET"}, format="json")
     */
    public function index(): Response
    {
        return $this->json([
            'message' => 'Welcome to company controller!',
            'path' => 'src/Controller/CompanyController.php',
        ]);
    }

    /**
     * @Route("/api/company/register", name="Register a Company", methods={"POST"}, format="json")
     */
    public function register(Request $request): Response
    {
        $parameters = json_decode($request->getContent(), true);
        if (
            !isset($parameters['name']) && empty($parameters['name']) ||
            !isset($parameters['debter_limit']) && empty($parameters['debter_limit']) ||
            !is_numeric($parameters['debter_limit'])
        ) {
            return $this->response('Missing required informations', 'error');
        } else {
            $registerd = $this->companyRepository->register($parameters['name'], $parameters['debter_limit']);
            if ($registerd) {
                return $this->response($registerd);
            } else {
                return $this->response('Error register a company', 'error');
            }
        }
    }

    /**
     * @Route("/api/company/:id/invoice", name="Create an invoice", methods={"POST"}, format="json")
     */
    public function createInvoice(Request $request): Response
    {
        $parameters = json_decode($request->getContent(), true);
        
        return $this->response('Error register a company', 'error');
    }
}
