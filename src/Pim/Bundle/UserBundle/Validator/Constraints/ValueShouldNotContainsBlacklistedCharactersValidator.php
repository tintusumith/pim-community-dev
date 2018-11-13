<?php
declare(strict_types=1);

namespace Pim\Bundle\UserBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValueShouldNotContainsBlacklistedCharactersValidator extends ConstraintValidator
{
    private const BLACKLISTED_CHARACTERS = ['<', '>', '&', '"'];

    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        //strpbrk returns a string if one of the characters in second argument string was found
        if (!empty(strpbrk($value, implode('', self::BLACKLISTED_CHARACTERS)))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ items }}', implode(', ', self::BLACKLISTED_CHARACTERS))
                ->addViolation();
        }
    }
}
