<?php

namespace Zikula\Module\CoreManagerModule\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Zikula\Common\Translator\TranslatorInterface;
use Zikula\Module\CoreManagerModule\Manager\GitHubApiWrapper;

class CoreVersionType extends AbstractType
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var array
     */
    private $versions;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        TranslatorInterface $translator,
        GitHubApiWrapper $api
    ) {
        $this->translator = $translator;
        $this->versions = $api->getAllowedCoreVersions();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('version', ChoiceType::class, [
                'label' => $this->translator->__('Core version'),
                'label_attr' => ['class' => 'col-sm-3'],
                'choices' => new ArrayChoiceList($this->versions),
            ])
            ->add('next', SubmitType::class, [
                'label' => $this->translator->__('Next'),
            ])
        ;
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'coreVersion';
    }
}