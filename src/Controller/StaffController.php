<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Staff Controller — Rubrics: Staff Features 16pts
 *
 * Staff can:
 *  - View their own profile and change password
 *  - Create new products (records)
 *  - Edit own records only (blocked from admin/other staff records)
 *  - Delete own records only (with confirmation)
 *  - View all catalog records (read shared, edit own)
 *
 * Staff CANNOT:
 *  - Access admin dashboard or user management (/admin/* returns 403/redirect)
 *  - Edit or delete other users/staff records
 *  - Place customer orders or use shopping cart
 */
#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
class StaffController extends AbstractController
{
    #[Route('', name: 'app_staff_dashboard')]
    public function dashboard(ProductRepository $productRepo, OrderRepository $orderRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $allProducts   = $productRepo->findBy([], ['id' => 'DESC']);
        $myProducts    = array_filter($allProducts, function (Product $p) use ($user) {
            return $p->getCreatedBy() && $p->getCreatedBy()->getId() === $user->getId();
        });
        $totalOrders   = count($orderRepo->findAll());

        return $this->render('staff/dashboard.html.twig', [
            'user'           => $user,
            'my_products'    => array_values($myProducts),
            'all_products'   => $allProducts,
            'total_products' => count($allProducts),
            'my_count'       => count($myProducts),
            'total_orders'   => $totalOrders,
        ]);
    }

    #[Route('/products', name: 'app_staff_products', methods: ['GET', 'POST'])]
    public function products(
        Request $request,
        ProductRepository $productRepo,
        CategoryRepository $categoryRepo,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('staff_product_create', $token)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_staff_products');
            }

            $name        = trim((string) $request->request->get('name'));
            $categoryId  = (int) $request->request->get('category_id');
            $price       = trim((string) $request->request->get('price'));
            $stock       = (int) $request->request->get('stock', 10);
            $description = trim((string) $request->request->get('description'));

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
            if (!$category || empty($name) || empty($price) || (float) $price <= 0) {
                $this->addFlash('error', 'Please fill all required fields with valid values.');
                return $this->redirectToRoute('app_staff_products');
            }

            $product = new Product();
            $product->setName($name);
            $product->setCategory($category);
            $product->setPrice(number_format((float) $price, 2, '.', ''));
            $product->setStock($stock);
            $product->setDescription($description);
            $product->setImage($imagePath);
            $product->setIsFeatured(false);
            // Record ownership: created by this staff user
            $product->setCreatedBy($user);

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', "Product \"{$name}\" created successfully.");
            return $this->redirectToRoute('app_staff_products');
        }

        $search = trim((string) $request->query->get('search', ''));
        $filterScope = (string) $request->query->get('scope', 'all');

        $products = $productRepo->findAllWithCategory();
        $categories = $categoryRepo->findAll();

        if ($search !== '') {
            $products = array_filter($products, function (Product $p) use ($search) {
                return stripos($p->getName(), $search) !== false
                    || stripos($p->getDescription() ?? '', $search) !== false;
            });
        }

        if ($filterScope === 'mine') {
            $products = array_filter($products, function (Product $p) use ($user) {
                return $p->getCreatedBy() && $p->getCreatedBy()->getId() === $user->getId();
            });
        }

        return $this->render('staff/products.html.twig', [
            'products'     => array_values($products),
            'categories'   => $categories,
            'search'       => $search,
            'filter_scope' => $filterScope,
        ]);
    }

    #[Route('/products/{id}/edit', name: 'app_staff_product_edit', methods: ['GET', 'POST'])]
    public function editProduct(
        Product $product,
        Request $request,
        CategoryRepository $categoryRepo,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Enforce Rubric: Edit own records only — cannot edit admin or other staff records
        $isOwner = $product->getCreatedBy() && $product->getCreatedBy()->getId() === $user->getId();
        if (!$isOwner) {
            $this->addFlash('error', 'Access denied: You can only edit products you created. You cannot edit admin or other staff records.');
            return $this->redirectToRoute('app_staff_products');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('staff_product_edit_' . $product->getId(), $token)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_staff_product_edit', ['id' => $product->getId()]);
            }

            $name        = trim((string) $request->request->get('name'));
            $price       = trim((string) $request->request->get('price'));
            $stock       = (int) $request->request->get('stock', 0);
            $description = trim((string) $request->request->get('description'));
            $categoryId  = (int) $request->request->get('category_id');

            if (empty($name) || empty($price) || (float) $price <= 0) {
                $this->addFlash('error', 'Name and a valid price are required.');
                return $this->redirectToRoute('app_staff_product_edit', ['id' => $product->getId()]);
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
            }

            $em->flush();
            $this->addFlash('success', "Product \"{$product->getName()}\" updated successfully.");
            return $this->redirectToRoute('app_staff_products');
        }

        return $this->render('staff/product_edit.html.twig', [
            'product'    => $product,
            'categories' => $categoryRepo->findAll(),
        ]);
    }

    #[Route('/products/{id}/delete', name: 'app_staff_product_delete', methods: ['POST'])]
    public function deleteProduct(Product $product, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Enforce Rubric: Delete own records only (with confirmation prompt)
        $isOwner = $product->getCreatedBy() && $product->getCreatedBy()->getId() === $user->getId();
        if (!$isOwner) {
            $this->addFlash('error', 'Access denied: You can only delete products you created.');
            return $this->redirectToRoute('app_staff_products');
        }

        $token = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('staff_product_delete_' . $product->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_staff_products');
        }

        $name = $product->getName();
        $em->remove($product);
        $em->flush();

        $this->addFlash('success', "Product \"{$name}\" deleted successfully.");
        return $this->redirectToRoute('app_staff_products');
    }

    #[Route('/profile', name: 'app_staff_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('staff_profile', $token)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_staff_profile');
            }

            $name  = trim((string) $request->request->get('name'));
            $phone = trim((string) $request->request->get('phone'));

            if (empty($name)) {
                $this->addFlash('error', 'Name cannot be empty.');
                return $this->redirectToRoute('app_staff_profile');
            }

            $user->setName($name);
            $user->setPhone($phone ?: null);
            $user->setUpdatedAt(new \DateTime());

            $newPassword     = (string) $request->request->get('new_password');
            $confirmPassword = (string) $request->request->get('confirm_password');
            $currentPassword = (string) $request->request->get('current_password');

            if (!empty($newPassword)) {
                if (!$hasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'Current password is incorrect.');
                    return $this->redirectToRoute('app_staff_profile');
                }
                if (strlen($newPassword) < 6) {
                    $this->addFlash('error', 'New password must be at least 6 characters.');
                    return $this->redirectToRoute('app_staff_profile');
                }
                if ($newPassword !== $confirmPassword) {
                    $this->addFlash('error', 'New passwords do not match.');
                    return $this->redirectToRoute('app_staff_profile');
                }
                $user->setPassword($hasher->hashPassword($user, $newPassword));
            }

            $em->flush();
            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('app_staff_profile');
        }

        return $this->render('staff/profile.html.twig', [
            'user' => $user,
        ]);
    }
}
