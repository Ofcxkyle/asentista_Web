<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account')]
#[IsGranted('ROLE_CUSTOMER')]
class AccountController extends AbstractController
{
    #[Route('', name: 'app_account_dashboard')]
    public function dashboard(OrderRepository $orderRepo, CartService $cartService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $orders = $orderRepo->findUserOrders($user);

        return $this->render('account/dashboard.html.twig', [
            'user'       => $user,
            'orders'     => $orders,
            'cart_count' => $cartService->getCount(),
        ]);
    }

    #[Route('/profile', name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        CartService $cartService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('account_profile', $token)) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_account_profile');
            }

            $name  = trim((string) $request->request->get('name'));
            $phone = trim((string) $request->request->get('phone'));

            if (empty($name)) {
                $this->addFlash('error', 'Name cannot be empty.');
                return $this->redirectToRoute('app_account_profile');
            }

            $user->setName($name);
            $user->setPhone($phone ?: null);
            $user->setUpdatedAt(new \DateTime());

            // Change password if provided
            $newPassword = (string) $request->request->get('new_password');
            $confirmPassword = (string) $request->request->get('confirm_password');
            $currentPassword = (string) $request->request->get('current_password');

            if (!empty($newPassword)) {
                if (!$hasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'Current password is incorrect.');
                    return $this->redirectToRoute('app_account_profile');
                }
                if (strlen($newPassword) < 6) {
                    $this->addFlash('error', 'New password must be at least 6 characters.');
                    return $this->redirectToRoute('app_account_profile');
                }
                if ($newPassword !== $confirmPassword) {
                    $this->addFlash('error', 'New passwords do not match.');
                    return $this->redirectToRoute('app_account_profile');
                }
                $user->setPassword($hasher->hashPassword($user, $newPassword));
            }

            $em->flush();
            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('app_account_profile');
        }

        return $this->render('account/profile.html.twig', [
            'user'       => $user,
            'cart_count' => $cartService->getCount(),
        ]);
    }

    #[Route('/orders/{orderNumber}', name: 'app_account_order_detail', methods: ['GET'])]
    public function orderDetail(string $orderNumber, OrderRepository $orderRepo, CartService $cartService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $order = $orderRepo->findOneBy(['orderNumber' => $orderNumber]);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        // Security: only the order owner or admin can view this
        if ($order->getUser() !== null && $order->getUser()->getId() !== $user->getId()) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('You do not have permission to view this order.');
            }
        }

        return $this->render('account/order_detail.html.twig', [
            'order'      => $order,
            'user'       => $user,
            'cart_count' => $cartService->getCount(),
        ]);
    }
}
