<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Entity;

/**
 * @ORM\Table(name="temp_th")
 * @Entity(repositoryClass="App\Repository\TempThRepository")
 * @ORM\Entity
 */
class TempTh
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $arrive;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $depart;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $jour;

    /**
     * @ORM\Column(type="time")
     */
    private $h_fin;

    /**
     * @ORM\Column(type="time")
     */
    private $h_depart;

    /**
     * @ORM\Column(type="time")
     */
    private $intervalle;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArrive(): ?string
    {
        return $this->arrive;
    }

    public function setArrive(string $arrive): self
    {
        $this->arrive = $arrive;

        return $this;
    }

    public function getDepart(): ?string
    {
        return $this->depart;
    }

    public function setDepart(string $depart): self
    {
        $this->depart = $depart;

        return $this;
    }

    public function getJour(): ?string
    {
        return $this->jour;
    }

    public function setJour(string $jour): self
    {
        $this->jour = $jour;

        return $this;
    }

    public function getHFin(): ?\DateTimeInterface
    {
        return $this->h_fin;
    }

    public function setHFin(\DateTimeInterface $h_fin): self
    {
        $this->h_fin = $h_fin;

        return $this;
    }

    public function getHDepart(): ?\DateTimeInterface
    {
        return $this->h_depart;
    }

    public function setHDepart(\DateTimeInterface $h_depart): self
    {
        $this->h_depart = $h_depart;

        return $this;
    }

    public function getIntervalle(): ?\DateTimeInterface
    {
        return $this->intervalle;
    }

    public function setIntervalle(\DateTimeInterface $intervalle): self
    {
        $this->intervalle = $intervalle;

        return $this;
    }
}
