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

use Eccube\Form\FormBuilder;
use Eccube\Form\Type\AbstractType;
use Eccube\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class PluginLocalInstallType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder
            ->add('plugin_archive', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\File([
                        'mimeTypes' => ['application/zip', 'application/x-tar', 'application/x-gzip', 'application/gzip'],
                        'mimeTypesMessage' => 'admin.store.template.invalid_upload_file',
                    ]),
                ],
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'plugin_local_install';
    }
}
