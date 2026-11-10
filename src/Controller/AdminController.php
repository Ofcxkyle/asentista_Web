<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(OrderRepository $orderRepo, ProductRepository $productRepo, UserRepository $userRepo): Response
    {
        $stats = $orderRepo->getDashboardStats();
        $recentOrders = $orderRepo->findBy([], ['createdAt' => 'DESC'], 5);
        $lowStock = $productRepo->findLowStock(15);

        // Dashboard stats per rubrics: total users, total staff, total records
        $allUsers = $userRepo->findAll();
        $totalUsers = count($allUsers);
        $totalStaff = 0;
        $totalAdmins = 0;
        $totalCustomers = 0;
        foreach ($allUsers as $u) {
            if (in_array('ROLE_ADMIN', $u->getRoles(), true)) {
                $totalAdmins++;
            } elseif (in_array('ROLE_STAFF', $u->getRoles(), true)) {
                $totalStaff++;
            } else {
                $totalCustomers++;
            }
        }
        $totalProducts = count($productRepo->findAll());

        return $this->render('admin/dashboard.html.twig', [
            'stats'           => $stats,
            'recent_orders'   => $recentOrders,
            'low_stock'       => $lowStock,
            'total_users'     => $totalUsers,
            'total_staff'     => $totalStaff,
            'total_admins'    => $totalAdmins,
            'total_customers' => $totalCustomers,
            'total_products'  => $totalProducts,
        ]);
    }

    // ============================================================
    // USER MANAGEMENT (CRUD) — Rubrics: Admin Features 19pts
    // ============================================================

    #[Route('/users', name: 'app_admin_users')]
    public function users(UserRepository $userRepo, Request $request): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $filterRole = (string) $request->query->get('role', '');

        $allUsers = $userRepo->findAll();

        // Filter by search
        if ($search !== '') {
            $allUsers = array_filter($allUsers, function (User $u) use ($search) {
                return stripos($u->getName(), $search) !== false
                    || stripos($u->getEmail(), $search) !== false;
            });
        }

        // Filter by role
        if ($filterRole !== '') {
            $allUsers = array_filter($allUsers, function (User $u) use ($filterRole) {
                return in_array($filterRole, $u->getRoles(), true);
            });
        }

        return $this->render('admin/users.html.twig', [
            'users'       => array_values($allUsers),
            'search'      => $search,
            'filter_role' => $filterRole,
        ]);
    }

    #[Route('/users/create', name: 'app_admin_user_create', methods: ['GET', 'POST'])]
    public function createUser(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('admin_user_create', $token)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_admin_user_create');
            }

            $name     = trim((string) $request->request->get('name'));
            $email    = trim(strtolower((string) $request->request->get('email')));
            $phone    = trim((string) $request->request->get('phone'));
            $role     = (string) $request->request->get('role', 'ROLE_CUSTOMER');
            $password = (string) $request->request->get('password');

            // Validation
            if (empty($name) || empty($email) || empty($password)) {
                $this->addFlash('error', 'Name, email, and password are required.');
                return $this->redirectToRoute('app_admin_user_create');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Invalid email address.');
                return $this->redirectToRoute('app_admin_user_create');
            }
            if (strlen($password) < 6) {
                $this->addFlash('error', 'Password must be at least 6 characters.');
                return $this->redirectToRoute('app_admin_user_create');
            }
            $validRoles = ['ROLE_CUSTOMER', 'ROLE_STAFF', 'ROLE_ADMIN'];
            if (!in_array($role, $validRoles, true)) {
                $role = 'ROLE_CUSTOMER';
            }

            // Check email uniqueness
            $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existing) {
                $this->addFlash('error', "Email '{$email}' is already in use.");
                return $this->redirectToRoute('app_admin_user_create');
            }

            $user = new User();
            $user->setName($name);
            $user->setEmail($email);
            $user->setPhone($phone ?: null);
            $user->setRoles([$role]);
            $user->setPassword($hasher->hashPassword($user, $password));
            $user->setIsActive(true);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', "User account for \"{$name}\" created successfully.");
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_create.html.twig');
    }

    #[Route('/users/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('admin_user_edit_' . $user->getId(), $token)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            $name     = trim((string) $request->request->get('name'));
            $email    = trim(strtolower((string) $request->request->get('email')));
            $phone    = trim((string) $request->request->get('phone'));
            $role     = (string) $request->request->get('role', 'ROLE_CUSTOMER');
            $password = (string) $request->request->get('new_password');
            $isActive = $request->request->has('is_active');

            if (empty($name) || empty($email)) {
                $this->addFlash('error', 'Name and email are required.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Invalid email address.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            $validRoles = ['ROLE_CUSTOMER', 'ROLE_STAFF', 'ROLE_ADMIN'];
            if (!in_array($role, $validRoles, true)) {
                $role = 'ROLE_CUSTOMER';
            }

            // Check email uniqueness (exclude self)
            $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', "Email '{$email}' is already in use by another account.");
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            $user->setName($name);
            $user->setEmail($email);
            $user->setPhone($phone ?: null);
            $user->setRoles([$role]);
            $user->setIsActive($isActive);
            $user->setUpdatedAt(new \DateTime());

            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $this->addFlash('error', 'New password must be at least 6 characters.');
                    return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
                }
                $user->setPassword($hasher->hashPassword($user, $password));
            }

            $em->flush();
            $this->addFlash('success', "User \"{$user->getName()}\" updated successfully.");
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('admin_user_delete_' . $user->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_users');
        }

        // Cannot delete yourself
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot delete your own admin account.');
            return $this->redirectToRoute('app_admin_users');
        }

        $name = $user->getName();
        $em->remove($user);
        $em->flush();

        $this->addFlash('success', "User account for \"{$name}\" has been permanently deleted.");
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/toggle-active', name: 'app_admin_user_toggle_active', methods: ['POST'])]
    public function toggleActive(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('admin_user_toggle_' . $user->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_users');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot disable your own account.');
            return $this->redirectToRoute('app_admin_users');
        }

        $user->setIsActive(!$user->isActive());
        $user->setUpdatedAt(new \DateTime());
        $em->flush();

        $status = $user->isActive() ? 'activated' : 'disabled';
        $this->addFlash('success', "Account for \"{$user->getName()}\" has been {$status}.");
        return $this->redirectToRoute('app_admin_users');
    }

    // ============================================================
    // ORDERS MANAGEMENT
    // ============================================================

    #[Route('/orders', name: 'app_admin_orders')]
    public function orders(OrderRepository $orderRepo, Request $request): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $filterStatus = (string) $request->query->get('status', '');

        if ($search !== '' || $filterStatus !== '') {
            $orders = $orderRepo->searchOrders($search, $filterStatus);
        } else {
            $orders = $orderRepo->findAllWithItems();
        }

        return $this->render('admin/orders.html.twig', [
            'orders'        => $orders,
            'search'        => $search,
            'filter_status' => $filterStatus,
        ]);
    }

    #[Route('/orders/status/{id}', name: 'app_admin_order_status', methods: ['POST'])]
    public function updateOrderStatus(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('order_status_' . $order->getId(), $token)) {
            $this->addFlash('error', 'Invalid token.');
            return $this->redirectToRoute('app_admin_orders');
        }

        // Once completed, order status is permanently locked and cannot be changed
        if (strtolower((string) $order->getStatus()) === 'completed') {
            $this->addFlash('error', 'Order #' . $order->getOrderNumber() . ' is already marked as Completed and is permanently locked.');
            return $this->redirectToRoute('app_admin_orders');
        }

        $newStatus = $request->request->get('status');
        $validStatuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];

        if (in_array($newStatus, $validStatuses, true)) {
            $order->setStatus($newStatus);
            $order->setUpdatedAt(new \DateTime());
            $em->flush();
            $this->addFlash('success', 'Order #' . $order->getOrderNumber() . ' status updated to ' . $newStatus);
        }

        return $this->redirectToRoute('app_admin_orders');
    }

    // ============================================================
    // PRODUCTS MANAGEMENT (Admin can edit/delete ANY product)
    // ============================================================

    #[Route('/products', name: 'app_admin_products', methods: ['GET', 'POST'])]
    public function products(
        Request $request,
        ProductRepository $productRepo,
        CategoryRepository $categoryRepo,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('admin_product', $token)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_admin_products');
            }

            $name        = trim((string) $request->request->get('name'));
            $categoryId  = (int) $request->request->get('category_id');
            $price       = trim((string) $request->request->get('price'));
            $stock       = (int) $request->request->get('stock', 15);
            $description = trim((string) $request->request->get('description'));
            $isFeatured  = $request->request->has('is_featured');

            // Handle custom picture upload or preset choice
            $imageFile = $request->files->get('image_file');
            if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                $origName = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = preg_replace('/[^a-z0-9_-]/i', '_', $origName) ?: 'product';
                $newFilename = $safeName . '-' . uniqid() . '.' . ($imageFile->guessExtension() ?: 'png');
                $imageFile->move($uploadsDir, $newFilename);
                $imagePath = 'uploads/products/' . $newFilename;
            } else {
                $imagePath = trim((string) $request->request->get('image', 'assets/breads-e1656042972619.png'));
            }

            $category = $categoryRepo->find($categoryId);
            if (!$category || empty($name) || empty($price)) {
                $this->addFlash('error', 'Please provide a valid name, category, and price.');
                return $this->redirectToRoute('app_admin_products');
            }

            $product = new Product();
            $product->setName($name);
            $product->setCategory($category);
            $product->setPrice(number_format((float) $price, 2, '.', ''));
            $product->setStock($stock);
            $product->setDescription($description);
            $product->setImage($imagePath);
            $product->setIsFeatured($isFeatured);
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $product->setCreatedBy($currentUser);

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', 'Product "' . $name . '" added successfully.');
            return $this->redirectToRoute('app_admin_products');
        }

        $search = trim((string) $request->query->get('search', ''));
        $products   = $productRepo->findAllWithCategory();
        $categories = $categoryRepo->findAll();

        // Filter by search
        if ($search !== '') {
            $products = array_filter($products, function (Product $p) use ($search) {
                return stripos($p->getName(), $search) !== false
                    || stripos($p->getDescription() ?? '', $search) !== false;
            });
        }

        return $this->render('admin/products.html.twig', [
            'products'   => array_values($products),
            'categories' => $categories,
            'search'     => $search,
        ]);
    }

    #[Route('/products/toggle-featured/{id}', name: 'app_admin_product_toggle_featured', methods: ['POST'])]
    public function toggleFeatured(Product $product, EntityManagerInterface $em): Response
    {
        $product->setIsFeatured(!$product->isFeatured());
        $em->flush();

        return $this->redirectToRoute('app_admin_products');
    }

    #[Route('/products/update/{id}', name: 'app_admin_product_update', methods: ['POST'])]
    public function updateProduct(Product $product, Request $request, CategoryRepository $categoryRepo, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('product_update_' . $product->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_products');
        }

        $name        = trim((string) $request->request->get('name'));
        $price       = trim((string) $request->request->get('price'));
        $stock       = (int) $request->request->get('stock', 0);
        $description = trim((string) $request->request->get('description'));
        $categoryId  = (int) $request->request->get('category_id');
        $isFeatured  = $request->request->has('is_featured');

        if (empty($name) || empty($price)) {
            $this->addFlash('error', 'Name and price are required.');
            return $this->redirectToRoute('app_admin_products');
        }

        if ($categoryId) {
            $category = $categoryRepo->find($categoryId);
            if ($category) {
                $product->setCategory($category);
            }
        }

        $product->setName($name);
        $product->setPrice(number_format((float) $price, 2, '.', ''));
        $product->setStock($stock);
        $product->setDescription($description);
        $product->setIsFeatured($isFeatured);

        // Update image if new upload or preset provided
        $imageFile = $request->files->get('image_file');
        if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            $origName = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeName = preg_replace('/[^a-z0-9_-]/i', '_', $origName) ?: 'product';
            $newFilename = $safeName . '-' . uniqid() . '.' . ($imageFile->guessExtension() ?: 'png');
            $imageFile->move($uploadsDir, $newFilename);
            $product->setImage('uploads/products/' . $newFilename);
        } elseif ($request->request->get('image')) {
            $product->setImage(trim((string) $request->request->get('image')));
        }

        $em->flush();

        $this->addFlash('success', 'Product "' . $product->getName() . '" updated successfully.');
        return $this->redirectToRoute('app_admin_products');
    }

    #[Route('/products/delete/{id}', name: 'app_admin_product_delete', methods: ['POST'])]
    public function deleteProduct(Product $product, Request $request, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('product_delete_' . $product->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_products');
        }

        $name = $product->getName();
        $em->remove($product);
        $em->flush();

        $this->addFlash('success', 'Product "' . $name . '" has been removed from the catalog.');
        return $this->redirectToRoute('app_admin_products');
    }

    // ============================================================
    // CUSTOMERS VIEW
    // ============================================================

    #[Route('/customers', name: 'app_admin_customers')]
    public function customers(UserRepository $userRepo): Response
    {
        $customers = $userRepo->findAll();

        return $this->render('admin/customers.html.twig', [
            'customers' => $customers,
        ]);
    }

    #[Route('/orders/export-csv', name: 'app_admin_orders_export_csv')]
    public function exportOrdersCsv(OrderRepository $orderRepo): StreamedResponse
    {
        $orders = $orderRepo->findAllWithItems();

        $response = new StreamedResponse(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order Number', 'Date', 'Customer Name', 'Email', 'Phone', 'Order Type', 'Items', 'Total (PHP)', 'Status']);

            foreach ($orders as $order) {
                $itemNames = [];
                foreach ($order->getItems() as $item) {
                    $itemNames[] = $item->getProductName() . ' (x' . $item->getQuantity() . ')';
                }

                fputcsv($handle, [
                    $order->getOrderNumber(),
                    $order->getCreatedAt()->format('Y-m-d H:i'),
                    $order->getCustomerName(),
                    $order->getCustomerEmail(),
                    $order->getCustomerPhone(),
                    $order->getOrderType(),
                    implode(', ', $itemNames),
                    $order->getTotalAmount(),
                    $order->getStatus(),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="asentista_orders_' . date('Ymd_His') . '.csv"');

        return $response;
    }
}
