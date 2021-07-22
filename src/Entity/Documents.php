<?php

namespace App\Entity;

use App\Repository\DocumentsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DocumentsRepository::class)
 */
class Documents
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nom;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $slug;

    /**
     * @ORM\Column(type="text")
     */
    private $description;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $step;

    /**
     * @ORM\OneToMany(targetEntity=UploadedDocuments::class, mappedBy="document")
     */
    private $uploadedDocuments;

    public function __construct()
    {
        $this->uploadedDocuments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getStep(): ?string
    {
        return $this->step;
    }

    public function setStep(string $step): self
    {
        $this->step = $step;

        return $this;
    }

    /**
     * @return Collection|UploadedDocuments[]
     */
    public function getUploadedDocuments(): Collection
    {
        return $this->uploadedDocuments;
    }

    public function addUploadedDocument(UploadedDocuments $uploadedDocument): self
    {
        if (!$this->uploadedDocuments->contains($uploadedDocument)) {
            $this->uploadedDocuments[] = $uploadedDocument;
            $uploadedDocument->setDocument($this);
        }

        return $this;
    }

    public function removeUploadedDocument(UploadedDocuments $uploadedDocument): self
    {
        if ($this->uploadedDocuments->removeElement($uploadedDocument)) {
            // set the owning side to null (unless already changed)
            if ($uploadedDocument->getDocument() === $this) {
                $uploadedDocument->setDocument(null);
            }
        }

        return $this;
    }
}
