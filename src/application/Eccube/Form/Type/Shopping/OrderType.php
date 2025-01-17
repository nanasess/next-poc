<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Form\Type\Shopping;

use Doctrine\Common\Collections\ArrayCollection;
use Eccube\Entity\Delivery;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\Form\Form;
use Eccube\Form\FormBuilder;
use Eccube\Form\FormEvent;
use Eccube\Form\Type\AbstractType;
use Eccube\OptionsResolver\OptionsResolver;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\DeliveryRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Request\Context;
use Eccube\Validator\Constraints\Length;
use Eccube\Validator\Constraints\NotBlank;
use Eccube\Validator\Constraints\Regex;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class OrderType extends AbstractType
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var DeliveryRepository
     */
    protected $deliveryRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var BaseInfoRepository
     */
    protected $baseInfoRepository;

    /**
     * @var Context
     */
    protected $requestContext;

    /**
     * OrderType constructor.
     *
     * @param OrderRepository $orderRepository
     * @param DeliveryRepository $deliveryRepository
     * @param PaymentRepository $paymentRepository
     * @param BaseInfoRepository $baseInfoRepository
     * @param Context $requestContext
     */
    public function __construct(
        OrderRepository $orderRepository,
        DeliveryRepository $deliveryRepository,
        PaymentRepository $paymentRepository,
        BaseInfoRepository $baseInfoRepository,
        Context $requestContext
    ) {
        $this->orderRepository = $orderRepository;
        $this->deliveryRepository = $deliveryRepository;
        $this->paymentRepository = $paymentRepository;
        $this->baseInfoRepository = $baseInfoRepository;
        $this->requestContext = $requestContext;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        // ShoppingController::checkoutから呼ばれる場合は, フォーム項目の定義をスキップする.
        if ($options['skip_add_form']) {
            return;
        }

        $builder->add('message', TextareaType::class, [
            'required' => false,
            'constraints' => [
                new Length(['min' => 0, 'max' => 3000]),
            ],
        ])->add('Shippings', CollectionType::class, [
            'entry_type' => ShippingType::class,
            'by_reference' => false,
        ])->add('redirect_to', HiddenType::class, [
            'mapped' => false,
        ]);

        if ($this->baseInfoRepository->get()->isOptionPoint() && $this->requestContext->getCurrentUser()) {
            $builder->add('use_point', IntegerType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(),
                    new Regex([
                        'pattern' => "/^\d+$/u",
                        'message' => 'form_error.numeric_only',
                    ]),
                    new Length(['max' => 11]),
                ],
            ]);
        }

        // 支払い方法のプルダウンを生成
        $builder->onPostSetData(function (FormEvent $event) {
            /** @var Order $Order */
            $Order = $event->getData();
            if (null === $Order || !$Order->getId()) {
                return;
            }

            $Deliveries = $this->getDeliveries($Order);
            $Payments = $this->getPayments($Deliveries);
            // @see https://github.com/EC-CUBE/ec-cube/issues/4881
            $charge = $Order->getPayment() ? $Order->getPayment()->getCharge() : 0;
            $Payments = $this->filterPayments($Payments, $Order->getPaymentTotal() - $charge);

            $form = $event->getForm();
            $this->addPaymentForm($form, $Payments, $Order->getPayment());
        });

        // 支払い方法のプルダウンを生成(Submit時)
        // 配送方法の選択によって使用できる支払い方法がかわるため, フォームを再生成する.
        $builder->onPreSubmit(function (FormEvent $event) {
            /** @var Order $Order */
            $Order = $event->getForm()->getData();
            $data = $event->getData();

            $Deliveries = [];
            if (!empty($data['Shippings'])) {
                foreach ($data['Shippings'] as $Shipping) {
                    if (!empty($Shipping['Delivery'])) {
                        $Delivery = $this->deliveryRepository->find($Shipping['Delivery']);
                        if ($Delivery) {
                            $Deliveries[] = $Delivery;
                        }
                    }
                }
            }

            $Payments = $this->getPayments($Deliveries);
            // @see https://github.com/EC-CUBE/ec-cube/issues/4881
            $charge = $Order->getPayment() ? $Order->getPayment()->getCharge() : 0;
            $Payments = $this->filterPayments($Payments, $Order->getPaymentTotal() - $charge);

            $form = $event->getForm();
            $this->addPaymentForm($form, $Payments);
        });

        $builder->onPostSubmit(function (FormEvent $event) {
            /** @var Order $Order */
            $Order = $event->getData();
            $Payment = $Order->getPayment();
            if ($Payment && $Payment->getMethod()) {
                $Order->setPaymentMethod($Payment->getMethod());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'Eccube\Entity\Order',
                'skip_add_form' => false,
            ]
        );
    }

    public function getBlockPrefix()
    {
        return '_shopping_order';
    }

    private function addPaymentForm(Form $form, array $choices, Payment $data = null)
    {
        $message = trans('front.shopping.payment_method_unselected');

        if (empty($choices)) {
            $message = trans('front.shopping.payment_method_not_fount');
        }

        $form->add('Payment', EntityType::class, [
            'class' => Payment::class,
            'choice_label' => 'method',
            'expanded' => true,
            'multiple' => false,
            'placeholder' => false,
            'constraints' => [
                new NotBlank(['message' => $message]),
            ],
            'choices' => $choices,
            'data' => $data,
            'invalid_message' => $message,
        ]);
    }

    /**
     * 出荷に紐づく配送方法を取得する.
     *
     * @param Order $Order
     *
     * @return Delivery[]
     */
    private function getDeliveries(Order $Order)
    {
        $Deliveries = [];
        foreach ($Order->getShippings() as $Shipping) {
            $Delivery = $Shipping->getDelivery();
            if ($Delivery->isVisible()) {
                $Deliveries[] = $Shipping->getDelivery();
            }
        }

        return array_unique($Deliveries);
    }

    /**
     * 配送方法に紐づく支払い方法を取得する
     * 各配送方法に共通する支払い方法のみ返す.
     *
     * @param Delivery[] $Deliveries
     *
     * @return ArrayCollection
     */
    private function getPayments($Deliveries)
    {
        $PaymentsByDeliveries = [];
        foreach ($Deliveries as $Delivery) {
            $PaymentOptions = $Delivery->getPaymentOptions();
            foreach ($PaymentOptions as $PaymentOption) {
                /** @var Payment $Payment */
                $Payment = $PaymentOption->getPayment();
                if ($Payment->isVisible()) {
                    $PaymentsByDeliveries[$Delivery->getId()][] = $Payment;
                }
            }
        }

        if (empty($PaymentsByDeliveries)) {
            return new ArrayCollection();
        }

        $i = 0;
        $PaymentsIntersected = [];
        foreach ($PaymentsByDeliveries as $Payments) {
            if ($i === 0) {
                $PaymentsIntersected = $Payments;
            } else {
                $PaymentsIntersected = array_intersect($PaymentsIntersected, $Payments);
            }
            $i++;
        }

        return new ArrayCollection($PaymentsIntersected);
    }

    /**
     * 支払い方法の利用条件でフィルタをかける.
     *
     * @param ArrayCollection $Payments
     * @param $total
     *
     * @return Payment[]
     */
    private function filterPayments(ArrayCollection $Payments, $total)
    {
        $PaymentArrays = $Payments->filter(function (Payment $Payment) use ($total) {
            $charge = $Payment->getCharge();
            $min = $Payment->getRuleMin();
            $max = $Payment->getRuleMax();

            if (null !== $min && ($total + $charge) < $min) {
                return false;
            }

            if (null !== $max && ($total + $charge) > $max) {
                return false;
            }

            return true;
        })->toArray();
        usort($PaymentArrays, function (Payment $a, Payment $b) {
            return $a->getSortNo() < $b->getSortNo() ? 1 : -1;
        });

        return $PaymentArrays;
    }
}
