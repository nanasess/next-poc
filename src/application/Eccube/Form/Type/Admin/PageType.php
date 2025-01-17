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

namespace Eccube\Form\Type\Admin;

use Doctrine\ORM\EntityRepository;
use Eccube\Form\FormBuilder;
use Eccube\Form\Type\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class PageType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder
            ->add('layout', EntityType::class, [
                'label' => false,
                'class' => 'Eccube\Entity\Page',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er
                        ->createQueryBuilder('l')
                        ->where('l.id <> 0')
                        ->orderBy('l.id', 'ASC');
                },
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'admin_page';
    }
}
