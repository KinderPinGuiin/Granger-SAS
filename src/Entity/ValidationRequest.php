<?php

namespace App\Entity;

use App\Repository\ValidationRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ValidationRequestRepository::class)
 */
class ValidationRequest
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity=User::class, cascade={"persist", "remove"})
     */
    private $user;

    /**
     * @ORM\Column(type="blob")
     */
    private $permis;

    /**
     * @ORM\Column(type="blob")
     */
    private $contrat;

    /**
     * @ORM\Column(type="boolean", nullable="true")
     */
    private $accepted;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getPermis()
    {
        return $this->permis;
    }

    public function setPermis($permis): self
    {
        $this->permis = $permis;

        return $this;
    }

    public function getContrat()
    {
        return $this->contrat;
    }

    public function setContrat($contrat): self
    {
        $this->contrat = $contrat;

        return $this;
    }

    public function getAccepted(): ?bool
    {
        return $this->accepted;
    }

    public function setAccepted(bool $accepted): self
    {
        $this->accepted = $accepted;

        return $this;
    }
}
