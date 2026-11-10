<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\CartService;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/checkout')]
class CheckoutController extends AbstractController
{
    #[Route('', name: 'app_checkout_index', methods: ['GET', 'POST'])]
    public function index(Request $request, CartService $cartService, OrderService $orderService): Response
    {
        // Admin and Staff cannot place orders
        if ($this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Administrators cannot place orders.');
            return $this->redirectToRoute('app_admin_dashboard');
        }
        if ($this->isGranted('ROLE_STAFF')) {
            $this->addFlash('error', 'Staff accounts cannot place orders.');
            return $this->redirectToRoute('app_staff_dashboard');
        }

        $items = $cartService->getItems();
        if (empty($items)) {
            $this->addFlash('error', 'Your cart is empty. Add products before checking out.');
            return $this->redirectToRoute('app_store_menu');
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $total = $cartService->getTotal();

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('checkout', $token)) {
                $this->addFlash('error', 'Invalid security token. Please try again.');
                return $this->redirectToRoute('app_checkout_index');
            }

            $customerName = trim((string) $request->request->get('customer_name'));
            $customerEmail = trim((string) $request->request->get('customer_email'));
            $customerPhone = trim((string) $request->request->get('customer_phone'));
            $orderType = trim((string) $request->request->get('order_type', 'In-Store Pickup'));
            $reservationDateStr = trim((string) $request->request->get('reservation_date'));
            $specialNotes = trim((string) $request->request->get('special_notes'));

            // Validation
            if (empty($customerName) || empty($customerEmail) || empty($customerPhone)) {
                $this->addFlash('error', 'Please fill in all required customer details.');
                return $this->redirectToRoute('app_checkout_index');
            }

            if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please provide a valid email address.');
                return $this->redirectToRoute('app_checkout_index');
            }

            try {
                $reservationDate = !empty($reservationDateStr) ? new \DateTime($reservationDateStr) : new \DateTime();
            } catch (\Exception) {
                $reservationDate = new \DateTime();
            }

            try {
                $order = $orderService->createOrder(
                    $user,
                    $customerName,
                    $customerEmail,
                    $customerPhone,
                    $orderType,
                    $reservationDate,
                    $specialNotes
                );

                return $this->redirectToRoute('app_checkout_success', ['orderNumber' => $order->getOrderNumber()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Order failed: ' . $e->getMessage());
                return $this->redirectToRoute('app_checkout_index');
            }
        }

        return $this->render('checkout/index.html.twig', [
            'items' => $items,
            'total' => $total,
            'user' => $user,
            'cart_count' => $cartService->getCount(),
        ]);
    }

    #[Route('/success/{orderNumber}', name: 'app_checkout_success', methods: ['GET'])]
    public function success(string $orderNumber, OrderRepository $orderRepo, CartService $cartService): Response
    {
        $order = $orderRepo->findOneBy(['orderNumber' => $orderNumber]);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $order,
            'cart_count' => $cartService->getCount(),
        ]);
    }
}
