<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StoreController extends AbstractController
{
    #[Route('/', name: 'app_store_index')]
    public function index(ProductRepository $productRepo, CategoryRepository $categoryRepo, CartService $cartService): Response
    {
        // Require authentication before accessing the website
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Direct roles to their primary dashboards
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_staff_dashboard');
        }

        $featuredProducts = $productRepo->findFeatured();
        $categories = $categoryRepo->findAll();
        $cartCount = $cartService->getCount();

        return $this->render('store/index.html.twig', [
            'featured_products' => $featuredProducts,
            'categories' => $categories,
            'cart_count' => $cartCount,
        ]);
    }

    #[Route('/menu', name: 'app_store_menu')]
    public function menu(Request $request, ProductRepository $productRepo, CategoryRepository $categoryRepo, CartService $cartService): Response
    {
        // Require authentication before accessing menu
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $categorySlug = $request->query->get('category');
        $search = $request->query->get('q');

        if ($categorySlug) {
            $products = $productRepo->findByCategorySlug($categorySlug);
        } else {
            $products = $productRepo->findAllWithCategory();
        }

        if ($search) {
            $search = strtolower(trim($search));
            $products = array_filter($products, function ($p) use ($search) {
                $name = strtolower($p->getName());
                $desc = strtolower($p->getDescription() ?? '');
                return str_contains($name, $search) || str_contains($desc, $search);
            });
        }

        $categories = $categoryRepo->findAll();
        $cartCount = $cartService->getCount();

        return $this->render('store/menu.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'current_category' => $categorySlug,
            'search_query' => $search,
            'cart_count' => $cartCount,
        ]);
    }
}
