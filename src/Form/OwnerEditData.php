<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Owner;

/**
 * Editable owner fields; the form writes here and the owner changes only after validation.
 */
final class OwnerEditData
{
    public ?string $title = null;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $street = null;
    public ?string $zip = null;
    public ?string $city = null;
    public ?string $phone = null;
    public ?string $email = null;
    public ?\DateTimeImmutable $duedate = null;
    public bool $doNotContact = false;
    public ?string $note = null;

    public static function fromOwner(Owner $owner): self
    {
        $data = new self();
        foreach (get_object_vars($data) as $field => $value) {
            $data->$field = $owner->$field;
        }

        return $data;
    }

    public function applyTo(Owner $owner): void
    {
        foreach (get_object_vars($this) as $field => $value) {
            $owner->$field = $value;
        }
    }
}
