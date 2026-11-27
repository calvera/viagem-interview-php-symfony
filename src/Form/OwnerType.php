<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Owner edit form with the legacy validation messages. A company has no title and first name.
 * Fields are added in the order legacy reports errors (name, e-mail, PSČ, date); the template places them.
 *
 * @extends AbstractType<OwnerEditData>
 */
final class OwnerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['company']) {
            $builder->add('title', TextType::class, ['constraints' => [self::max(30)]]);
            $builder->add('firstName', TextType::class, ['constraints' => [self::max(60)]]);
        }
        $builder
            ->add('lastName', TextType::class, ['constraints' => [
                new Assert\NotBlank(message: $options['company'] ? 'Vyplňte název firmy.' : 'Vyplňte příjmení.'),
                self::max(160),
            ]])
            ->add('email', TextType::class, ['constraints' => [new Assert\Email(message: 'E-mail nemá platný formát.'), self::max(120)]])
            ->add('zip', TextType::class, ['constraints' => [new Assert\Regex('/^\d{5}$/', message: 'PSČ musí mít 5 číslic.')]])
            ->add('duedate', DateType::class, ['input' => 'datetime_immutable', 'invalid_message' => 'Termín oslovení není platné datum.'])
            ->add('street', TextType::class, ['constraints' => [self::max(120)]])
            ->add('city', TextType::class, ['constraints' => [self::max(120)]])
            ->add('phone', TextType::class, ['constraints' => [self::max(20)]])
            ->add('doNotContact', CheckboxType::class)
            ->add('note', TextareaType::class, ['trim' => false]);
        // PSČ is stored without spaces as in legacy ("100 31" → "10031"), before validation.
        $builder->get('zip')->addModelTransformer(new CallbackTransformer(
            static fn (?string $zip): ?string => $zip,
            static fn (?string $zip): ?string => $zip === null ? null : str_replace(' ', '', $zip),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OwnerEditData::class,
            'company' => false,
            'required' => false,
            'csrf_token_id' => 'owner_edit',
            'csrf_message' => 'Platnost formuláře vypršela, odešlete ho prosím znovu.',
            'invalid_message' => 'Formulář obsahuje neplatné hodnoty.',
        ]);
        $resolver->setAllowedTypes('company', 'bool');
    }

    /** @param positive-int $length */
    private static function max(int $length): Assert\Length
    {
        return new Assert\Length(max: $length, maxMessage: 'Hodnota smí mít nejvýše {{ limit }} znaků.');
    }
}
