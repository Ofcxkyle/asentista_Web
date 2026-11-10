<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class OrderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CartService $cartService,
        private MailerInterface $mailer
    ) {}

    public function createOrder(
        ?User $user,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        string $orderType,
        \DateTimeInterface $reservationDate,
        ?string $specialNotes
    ): Order {
        $cartItems = $this->cartService->getItems();

        if (empty($cartItems)) {
            throw new \RuntimeException('Cannot create an order with an empty cart.');
        }

        $this->em->beginTransaction();
        try {
            $order = new Order();
            $order->setUser($user);
            $order->setCustomerName($customerName);
            $order->setCustomerEmail($customerEmail);
            $order->setCustomerPhone($customerPhone);
            $order->setOrderType($orderType);
            $order->setReservationDate($reservationDate);
            $order->setSpecialNotes($specialNotes);
            $order->setStatus('Pending');

            $total = 0.0;

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->getProduct();
                if (!$product) {
                    continue;
                }

                // Verify stock availability
                if ($product->getStock() < $cartItem->getQuantity()) {
                    throw new \RuntimeException(
                        'Not enough stock available for ' . $product->getName() .
                        '. Only ' . $product->getStock() . ' remaining.'
                    );
                }

                // Decrement product stock
                $product->setStock(max(0, $product->getStock() - $cartItem->getQuantity()));
                $this->em->persist($product);

                $subtotal = (float) $product->getPrice() * $cartItem->getQuantity();
                $total   += $subtotal;

                $orderItem = new OrderItem();
                $orderItem->setOrder($order);
                $orderItem->setProduct($product);
                $orderItem->setProductName($product->getName());
                $orderItem->setUnitPrice($product->getPrice());
                $orderItem->setQuantity($cartItem->getQuantity());
                $orderItem->setSubtotal(number_format($subtotal, 2, '.', ''));

                $order->addItem($orderItem);
            }

            $order->setTotalAmount(number_format($total, 2, '.', ''));

            $this->em->persist($order);
            $this->em->flush();

            // Clear the cart
            $this->cartService->clear();

            $this->em->commit();

            // Send order confirmation email (silently fail if mailer is unavailable)
            $this->sendOrderConfirmationEmail($order);

            return $order;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    private function sendOrderConfirmationEmail(Order $order): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address('orders@asentista.com', "Asentista's Bakery"))
                ->to(new Address($order->getCustomerEmail(), $order->getCustomerName()))
                ->subject("Order Confirmed: {$order->getOrderNumber()} - Asentista's Bakery")
                ->htmlTemplate('emails/order_confirmation.html.twig')
                ->context(['order' => $order]);

            $this->mailer->send($email);
        } catch (\Throwable) {
            // Silently swallow mailer errors — order was placed successfully regardless
        }
    }
}
