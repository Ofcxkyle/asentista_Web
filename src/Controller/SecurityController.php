<?php

namespace App\Controller;

use App\Entity\PasswordReset;
use App\Entity\User;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, CartService $cartService): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            if ($this->isGranted('ROLE_STAFF')) {
                return $this->redirectToRoute('app_staff_dashboard');
            }
            return $this->redirectToRoute('app_store_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
            'cart_count'    => $cartService->getCount(),
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        CartService $cartService
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_store_index');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('register', $token)) {
                $this->addFlash('error', 'Invalid form session. Please try again.');
                return $this->redirectToRoute('app_register');
            }

            $name            = trim((string) $request->request->get('name'));
            $email           = strtolower(trim((string) $request->request->get('email')));
            $phone           = trim((string) $request->request->get('phone'));
            $password        = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            if (empty($name) || empty($email) || empty($password)) {
                $this->addFlash('error', 'Please fill in all required fields.');
                return $this->redirectToRoute('app_register');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please enter a valid email address.');
                return $this->redirectToRoute('app_register');
            }

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Password must be at least 6 characters long.');
                return $this->redirectToRoute('app_register');
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->redirectToRoute('app_register');
            }

            if ($userRepo->findOneByEmail($email)) {
                $this->addFlash('error', 'An account with this email address already exists.');
                return $this->redirectToRoute('app_register');
            }

            $user = new User();
            $user->setName($name);
            $user->setEmail($email);
            $user->setPhone($phone ?: null);
            $user->setRoles(['ROLE_CUSTOMER']);
            $user->setPassword($hasher->hashPassword($user, $password));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Registration successful! You can now log in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'cart_count' => $cartService->getCount(),
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        CartService $cartService,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = strtolower(trim((string) $request->request->get('email')));
            $user  = $userRepo->findOneByEmail($email);

            // Always show the same message to prevent email enumeration
            $this->addFlash('info', 'If that email exists in our records, a password recovery link has been sent.');

            if ($user) {
                $plainToken = bin2hex(random_bytes(24));
                $tokenHash  = hash('sha256', $plainToken);

                $reset = new PasswordReset();
                $reset->setUser($user);
                $reset->setTokenHash($tokenHash);
                $reset->setExpiresAt(time() + 3600); // 1 hour expiration
                $reset->setStatus('pending');

                $em->persist($reset);
                $em->flush();

                $resetUrl = $this->generateUrl(
                    'app_reset_password',
                    ['token' => $plainToken],
                    \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
                );

                try {
                    $email = (new TemplatedEmail())
                        ->from(new Address('noreply@asentista.com', "Asentista's Bakery"))
                        ->to(new Address($user->getEmail(), $user->getName()))
                        ->subject("Password Reset - Asentista's Bakery")
                        ->htmlTemplate('emails/password_reset.html.twig')
                        ->context([
                            'user'      => $user,
                            'resetUrl'  => $resetUrl,
                            'expiresIn' => '1 hour',
                        ]);

                    $mailer->send($email);
                } catch (\Throwable) {
                    // Silently fail — don't leak email sending errors to the user
                }
            }

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig', [
            'cart_count' => $cartService->getCount(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        PasswordResetRepository $resetRepo,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        CartService $cartService
    ): Response {
        $tokenHash = hash('sha256', $token);
        $reset     = $resetRepo->findValidToken($tokenHash);

        if (!$reset) {
            $this->addFlash('error', 'This password reset link is invalid or has expired.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password        = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Password must be at least 6 characters.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $user = $reset->getUser();
            $user->setPassword($hasher->hashPassword($user, $password));
            $user->setUpdatedAt(new \DateTime());

            $reset->setStatus('used');

            $em->flush();

            $this->addFlash('success', 'Your password has been updated! Please log in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'token'      => $token,
            'cart_count' => $cartService->getCount(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Intercepted by Symfony firewall
        throw new \LogicException('This method is intercepted by the logout key on your firewall.');
    }
}
