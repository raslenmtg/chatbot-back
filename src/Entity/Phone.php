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

    /**
     * @var bool|null
     *
     * @ORM\Column(name="notif_auto", type="boolean", nullable=true)
     */
    protected $notif_auto;


    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }


    public function isNotifAuto(): bool
    {
        return $this->notif_auto;
    }

    public function setNotifAuto(bool $notif_auto): self
    {
        $this->notif_auto = $notif_auto;
        return $this;
    }




}
