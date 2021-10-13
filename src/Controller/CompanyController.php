<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompanyController extends BaseController
{
    public function __construct(CompanyRepository $companyRepository, InvoiceRepository $invoiceRepository, ProductRepository $productRepository, UserRepository $userRepository)
    {
        $this->companyRepository = $companyRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->productRepository = $productRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/api/company/{id}", name="Get all Companies", methods={"GET"}, format="json")
     */
    public function index($id = false): Response
    {
        if ($id){
            return $this->response($this->companyRepository->findOneBy(['id' => $id]));
        }
        return $this->response($this->companyRepository->findAll());
    }

    /**
     * @Route("/api/company/register", name="Register a Company", methods={"POST"}, format="json")
     */
    public function register(Request $request): Response
    {
        $params = json_decode($request->getContent(), true);
        if (
            !isset($params['name']) && empty($params['name']) ||
            !isset($params['debter_limit']) && empty($params['debter_limit']) ||
            !is_numeric($params['debter_limit'])
        ) {
            return $this->response('Missing required informations', 'error');
        } else {
            $registerd = $this->companyRepository->register($params['name'], $params['debter_limit']);
            if ($registerd) {
                return $this->response($registerd);
            } else {
                return $this->response('Error register a company', 'error');
            }
        }
    }

    /**
     * @Route("/api/company/{id}/invoice", name="Create an invoice", methods={"POST"}, format="json")
     */
    public function createInvoice($id, Request $request): Response
    {
        // Get request body json
        $params = json_decode($request->getContent(), true);

        // Check for the comany existence
        $company = $this->companyRepository->findOneBy(['id' => $id]);
        if ($company){

            // Foolproof the date
            $date = new DateTimeImmutable(!isset($params['date']) ? 'now' : $params['date']);

            // Check for the debtor
            if (isset($params['debtor'])){

                // Get the debtor user
                $debtor = $this->userRepository->findOneBy(['id' => $params['debtor']]);

                // Check debtor existing invoices and its limits
                $debtorInvoices = $debtor->getInvoices();
                $total = 0;
                foreach ($debtorInvoices as $debtorInvoice) {
                    $products = $debtorInvoice->getProducts();
                    foreach ($products as $item) {
                        $total +=  $item->getPrice() * $item->getQuantity();
                    }
                }

                // Add this invoice amounts, so we can sure the limit will never exceed.
                if (isset($params['products']) && is_array($params['products'])) {
                    $total += array_reduce($params['products'], function ($carry, $item) {
                        $carry += $item['price'] * $item['quantity'];
                        return $carry;
                    });
                }

                // Check for exceeding limits
                if ($total > $company->getDebtorLimit()){
                    return $this->response('Debtor limits for this company is exceeded', 'error');
                } else {

                    // Make the new invoice and add it's items
                    $invoice = $this->invoiceRepository->create($company, $params['code'], $params['description'], $debtor, $date);

                    if (isset($params['products']) && is_array($params['products'])){
                        foreach($params['products'] as $productDef){
                            $this->productRepository->add($invoice,
                                $productDef['name'],
                                $productDef['description'],
                                $productDef['quantity'],
                                $productDef['price'],
                                $productDef['unit'],
                                $productDef['image']
                            );
                        }
                    }
                }

                return $this->response($invoice->getId());
            } else {
                return $this->response('Debtor not found', 'error');
            }
        } else {
            return $this->response('Company not found', 'error');
        }
    }

    /**
     * @Route("/api/company/{id}/invoice/{inv_id}", name="Get invoice", methods={"GET"}, format="json")
     */
    public function getInvoice($id, $inv_id): Response
    {
        $company = $this->companyRepository->findOneBy(['id' => $id]);
        if ($company){
            $invoice = $this->invoiceRepository->findOneBy(['company' => $company, 'id' => $inv_id]);

            $products = [];
            foreach($invoice->getProducts() as $product){
                $products[] = [
                    'name' => $product->getName(),
                    'description' => $product->getDescription(),
                    'quantity' => $product->getQuantity(),
                    'price' => $product->getPrice(),
                    'image' => $product->getImage(),
                    'unit' => $product->getUnit(),
                ];
            }

            return $this->response([
                'id' => $invoice->getId(),
                'code' => $invoice->getCode(),
                'description' => $invoice->getDescription(),
                'date' => $invoice->getDate(),
                'created_at' => $invoice->getCreatedAt(),
                'updated_at' => $invoice->getUpdatedAt(),
                'product' => $products,
            ]);
        } else {
            return $this->response('Company not found', 'error');
        }
    }

    /**
     * @Route("/api/company/{id}/invoice/{inv_id}/product", name="Add product", methods={"POST"}, format="json")
     */
    public function addProduct($id, $inv_id, Request $request): Response
    {
        $company = $this->companyRepository->findOneBy(['id' => $id]);
        if ($company){
            $invoice = $this->invoiceRepository->findOneBy(['company' => $company, 'id' => $inv_id]);
            if ($invoice){
                $params = json_decode($request->getContent(), true);

                $product = $this->productRepository->add($invoice,
                    $params['name'],
                    $params['description'],
                    $params['quantity'],
                    $params['price'],
                    $params['unit'],
                    $params['image']
                );

                return $this->response($product->getId());
            } else {
                return $this->response('Invoice not found', 'error');
            }
        } else {
            return $this->response('Company not found', 'error');
        }
    }

}
