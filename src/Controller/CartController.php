<?php

namespace App\Controller;

use App\Entity\Product;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cart')]
class CartController extends AbstractController
{
    /**
     * Deny access to Admin and Staff — they manage products, not orders.
     */
    private function denyIfNotCustomer(): ?Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Administrators cannot place orders or use the shopping cart.');
            return $this->redirectToRoute('app_admin_dashboard');
        }
        if ($this->isGranted('ROLE_STAFF')) {
            $this->addFlash('error', 'Staff accounts cannot place orders. Please use a customer account.');
            return $this->redirectToRoute('app_staff_dashboard');
        }
        return null;
    }

    #[Route('', name: 'app_cart_index', methods: ['GET'])]
    public function index(CartService $cartService): Response
    {
        if ($redirect = $this->denyIfNotCustomer()) {
            return $redirect;
        }

        $items = $cartService->getItems();
        $total = $cartService->getTotal();
        $cartCount = $cartService->getCount();

        return $this->render('cart/index.html.twig', [
            'items'      => $items,
            'total'      => $total,
            'cart_count' => $cartCount,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(Product $product, Request $request, CartService $cartService): Response
    {
        if ($redirect = $this->denyIfNotCustomer()) {
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                return new JsonResponse(['success' => false, 'message' => 'Administrators cannot add items to cart.'], 403);
            }
            return $redirect;
        }

        $quantity = (int) $request->request->get('quantity', 1);
        if ($quantity < 1) {
            $quantity = 1;
        }

        $result = $cartService->addItem($product, $quantity);

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
            return new JsonResponse($result);
        }

        if ($result['success']) {
            $this->addFlash('success', $result['message']);
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(int $id, Request $request, CartService $cartService): Response
    {
        if ($redirect = $this->denyIfNotCustomer()) {
            return $redirect;
        }

        $quantity = (int) $request->request->get('quantity', 1);
        $result = $cartService->updateQuantity($id, $quantity);

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
            return new JsonResponse($result);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST', 'GET'])]
    public function remove(int $id, Request $request, CartService $cartService): Response
    {
        if ($redirect = $this->denyIfNotCustomer()) {
            return $redirect;
        }

        $result = $cartService->removeItem($id);

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
            return new JsonResponse($result);
        }

        $this->addFlash('info', 'Item removed from your cart.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/count', name: 'app_cart_count', methods: ['GET'])]
    public function count(CartService $cartService): JsonResponse
    {
        return new JsonResponse([
            'count' => $cartService->getCount(),
            'total' => number_format($cartService->getTotal(), 2),
        ]);
    }
}
