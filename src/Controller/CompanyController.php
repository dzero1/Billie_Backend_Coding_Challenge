<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompanyController extends BaseController
{
    public function __construct(CompanyRepository $companyRepository, InvoiceRepository $invoiceRepository, ProductRepository $productRepository, UserRepository $userRepository, LoggerInterface $logger)
    {
        $this->logger = $logger;
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
        $this->logger->info('fn Company index', $id);
        if ($id) {
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

        $this->logger->info('fn Company register', $params);
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
        $this->logger->info('fn Company createInvoice', [$id]);

        // Get request body json
        $params = json_decode($request->getContent(), true);
        $this->logger->info("request body json", [$params]);

        // Check for the company existence
        $company = $this->companyRepository->findOneBy(['id' => $id]);
        if ($company) {
            $this->logger->info("Company :", [$company->getName()]);

            // Foolproof the date
            $date = new DateTimeImmutable(!isset($params['date']) ? 'now' : $params['date']);

            // Check for the debtor
            if (isset($params['debtor'])) {

                // Get the debtor user
                $debtor = $this->userRepository->findOneBy(['id' => $params['debtor']]);
                $this->logger->info("Debter :", [$debtor->getId(), $debtor->getUsername()]);

                // Check debtor existing and active invoices and its limits
                $debtorInvoices = $this->invoiceRepository->findBy(['debtor' => $debtor, 'status' => 'ACTIVE']);
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

                $this->logger->info("Debter Total : " . $total);
                $this->logger->info("Company Debter limit : " . $company->getDebtorLimit());

                // Check for exceeding limits
                if ($total > $company->getDebtorLimit()) {
                    return $this->response('Debtor limits for this company is exceeded', 'error');
                } else {

                    // Make the new invoice and add it's items
                    $invoice = $this->invoiceRepository->create($company, $params['code'], $params['description'], $debtor, $date);

                    if (isset($params['products']) && is_array($params['products'])) {
                        foreach ($params['products'] as $productDef) {
                            $this->productRepository->add(
                                $invoice,
                                $productDef['name'],
                                $productDef['description'],
                                $productDef['quantity'],
                                $productDef['price'],
                                $productDef['unit'],
                                $productDef['image']
                            );
                            $this->logger->info("Product add :", [$productDef['name']]);

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
        $this->logger->info('fn Company getInvoice', [$id, $inv_id]);

        // Check for the company existence
        $company = $this->companyRepository->findOneBy(['id' => $id]);
        if ($company) {
            // Check invoice existence
            $invoice = $this->invoiceRepository->findOneBy(['company' => $company, 'id' => $inv_id]);
            if ($invoice) {
                    $products = [];
                    foreach ($invoice->getProducts() as $product) {
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
                        'status' => $invoice->getStatus(),
                        'created_at' => $invoice->getCreatedAt(),
                        'updated_at' => $invoice->getUpdatedAt(),
                        'product' => $products,
                    ]);
            } else {
                return $this->response('Invoice not found', 'error');
            }
        } else {
            return $this->response('Company not found', 'error');
        }
    }

    /**
     * @Route("/api/company/{id}/invoice/{inv_id}/product", name="Add product", methods={"POST"}, format="json")
     */
    public function addProduct($id, $inv_id, Request $request): Response
    {
        $this->logger->info('fn Company addProduct', [$id, $inv_id]);

        // Check for the company existence
        $company = $this->companyRepository->findOneBy(['id' => $id]);
        if ($company) {

            // Check invoice existence
            $invoice = $this->invoiceRepository->findOneBy(['company' => $company, 'id' => $inv_id]);
            if ($invoice) {
                $params = json_decode($request->getContent(), true);

                $product = $this->productRepository->add(
                    $invoice,
                    $params['name'],
                    $params['description'],
                    $params['quantity'],
                    $params['price'],
                    $params['unit'],
                    $params['image']
                );
                $this->logger->info("Product add :", $params['name']);

                return $this->response($product->getId());
            } else {
                return $this->response('Invoice not found', 'error');
            }
        } else {
            return $this->response('Company not found', 'error');
        }
    }

    /**
     * @Route("/api/company/{id}/invoice/{inv_id}/pay", name="Pay invoice", methods={"POST"}, format="json")
     */
    public function payInvoice($id, $inv_id): Response
    {
        $this->logger->info('fn Company payInvoice', [$id, $inv_id]);

        // Check for the company existence
        $company = $this->companyRepository->findOneBy(['id' => $id]);
        if ($company) {

            // Check invoice existence
            $invoice = $this->invoiceRepository->findOneBy(['company' => $company, 'id' => $inv_id]);
            if ($invoice) {
                $this->invoiceRepository->markAsPaid($invoice);
                return $this->response("");
            } else {
                return $this->response('Invoice not found', 'error');
            }
        } else {
            return $this->response('Company not found', 'error');
        }
    }
}
