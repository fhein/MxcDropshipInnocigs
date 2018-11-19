<?php

namespace MxcDropshipInnocigs\Models;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\Model\ModelEntity;


/**
 * @ORM\Entity(repositoryClass="InnocigsOptionRepository")
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="s_plugin_mxc_dropship_innocigs_configurator_option")
 */
class InnocigsOption extends ModelEntity {
    /**
     * Primary Key - autoincrement value
     *
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string $name
     *
     * @ORM\Column()
     */
    private $name;

    /**
     * @var InnocigsGroup $group
     * @ORM\ManyToOne(targetEntity="InnocigsGroup", inversedBy="options")
     */
    private $group;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     * @ORM\ManyToMany(targetEntity="InnocigsVariant", inversedBy="options")
     * @ORM\JoinTable(name="s_plugin_mxc_dropship_innocigs_options_variants")
     */
    private $variants;

    /**
     * @var \DateTime $created
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $created = null;

    /**
     * @var \DateTime $updated
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $updated = null;

    public function __construct() {
        $this->variants = new ArrayCollection();
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateTimestamps() {
        $now = new DateTime();
        $this->updated = $now;
        if ( null === $this->created) {
            $this->created = $now;
        }
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return \DateTime
     */
    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    /**
     * @return \DateTime
     */
    public function getUpdated(): \DateTime
    {
        return $this->updated;
    }

    /**
     * @return InnocigsGroup
     */
    public function getGroup(): InnocigsGroup
    {
        return $this->group;
    }

    /**
     * @param InnocigsGroup $group
     */
    public function setGroup(InnocigsGroup $group): void
    {
        $this->group = $group;
    }

    /**
     * @return ArrayCollection
     */
    public function getVariants()
    {
        return $this->variants;
    }

    /**
     * @param InnocigsVariant $variant
     *
     * This is the 'owned' side, so we DO NOT $variant->addOption($this)
     */
    public function addVariant(InnocigsVariant $variant): void
    {
        $this->variants->add($variant);
    }
}