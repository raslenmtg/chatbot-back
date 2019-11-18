<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Phone
 * @ORM\Table(name="phone")
 * @ORM\Entity
 */
class Phone
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=80)
     */
    private $phone;


    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }
}
