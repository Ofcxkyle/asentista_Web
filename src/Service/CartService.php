<?php

namespace App\Service;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CartItemRepository $cartItemRepository,
        private RequestStack $requestStack,
        private Security $security
    ) {}

    private function getSessionId(): string
    {
        $session = $this->requestStack->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }
        return $session->getId();
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user : null;
    }

    public function getItems(): array
    {
        $user = $this->getCurrentUser();
        $sessionId = $this->getSessionId();

        // If user logged in, merge any leftover session items first
        if ($user !== null) {
            $this->mergeSessionCartToUser($user);
        }

        return $this->cartItemRepository->findCartItems($user, $sessionId);
    }

    public function addItem(Product $product, int $quantity = 1): array
    {
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Quantity must be at least 1.'];
        }

        if ($product->getStock() < $quantity) {
            return ['success' => false, 'message' => 'Only ' . $product->getStock() . ' units left in stock.'];
        }

        $user = $this->getCurrentUser();
        $sessionId = $this->getSessionId();

        $item = $this->cartItemRepository->findExistingItem($user, $sessionId, $product);

        if ($item) {
            $newQuantity = $item->getQuantity() + $quantity;
            if ($newQuantity > $product->getStock()) {
                return ['success' => false, 'message' => 'Cannot add more. Exceeds available stock (' . $product->getStock() . ').'];
            }
            $item->setQuantity($newQuantity);
            $item->setUpdatedAt(new \DateTime());
        } else {
            $item = new CartItem();
            $item->setProduct($product);
            $item->setQuantity($quantity);
            if ($user !== null) {
                $item->setUser($user);
            } else {
                $item->setSessionId($sessionId);
            }
            $this->em->persist($item);
        }

        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Added ' . $product->getName() . ' to cart!',
            'cartCount' => $this->getCount(),
            'cartTotal' => $this->getTotal(),
        ];
    }

    public function updateQuantity(int $itemId, int $quantity): array
    {
        $item = $this->cartItemRepository->find($itemId);
        if (!$item) {
            return ['success' => false, 'message' => 'Cart item not found.'];
        }

        if ($quantity <= 0) {
            return $this->removeItem($itemId);
        }

        if ($item->getProduct() && $quantity > $item->getProduct()->getStock()) {
            return ['success' => false, 'message' => 'Exceeds available stock (' . $item->getProduct()->getStock() . ').'];
        }

        $item->setQuantity($quantity);
        $item->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return [
            'success' => true,
            'itemSubtotal' => number_format($item->getSubtotal(), 2),
            'cartCount' => $this->getCount(),
            'cartTotal' => number_format($this->getTotal(), 2),
        ];
    }

    public function removeItem(int $itemId): array
    {
        $item = $this->cartItemRepository->find($itemId);
        if ($item) {
            $this->em->remove($item);
            $this->em->flush();
        }

        return [
            'success' => true,
            'cartCount' => $this->getCount(),
            'cartTotal' => number_format($this->getTotal(), 2),
        ];
    }

    public function getCount(): int
    {
        $user = $this->getCurrentUser();
        $sessionId = $this->getSessionId();
        return $this->cartItemRepository->countCartItems($user, $sessionId);
    }

    public function getTotal(): float
    {
        $items = $this->getItems();
        $total = 0.0;
        foreach ($items as $item) {
            $total += $item->getSubtotal();
        }
        return $total;
    }

    public function clear(): void
    {
        $user = $this->getCurrentUser();
        $sessionId = $this->getSessionId();
        $this->cartItemRepository->clearCart($user, $sessionId);
    }

    public function mergeSessionCartToUser(User $user): void
    {
        $sessionId = $this->getSessionId();
        $sessionItems = $this->cartItemRepository->findBy(['sessionId' => $sessionId, 'user' => null]);

        if (empty($sessionItems)) {
            return;
        }

        foreach ($sessionItems as $sessionItem) {
            $product = $sessionItem->getProduct();
            if (!$product) continue;

            $existingUserItem = $this->cartItemRepository->findExistingItem($user, null, $product);
            if ($existingUserItem) {
                $newQty = min($product->getStock(), $existingUserItem->getQuantity() + $sessionItem->getQuantity());
                $existingUserItem->setQuantity($newQty);
                $this->em->remove($sessionItem);
            } else {
                $sessionItem->setUser($user);
                $sessionItem->setSessionId(null);
            }
        }

        $this->em->flush();
    }
}
