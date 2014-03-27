<?php
namespace Cygnus\OlyticsBundle\Model\Metadata;

use Cygnus\OlyticsBundle\Types\DateType;

class Entity extends AbstractEntity
{
    /**
     * The key/value pairs of this entity
     *
     * @var array
     */
    protected $keyValues = array();

    /**
     * Related entities to this entity
     *
     * @var Cygnus\OlyticsBundle\Model\Metadata\RelatedEntity
     */
    protected $relatedTo = array();

    /**
     * Last updated date 
     *
     * @var DateTime
     */
    protected $updatedAt;


    public function __construct()
    {
        $this->relatedTo = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add relatedTo
     *
     * @param Cygnus\OlyticsBundle\Model\Metadata\RelatedEntity $relatedTo
     */
    public function addRelatedTo(RelatedEntity $relatedTo)
    {
        $this->relatedTo[] = $relatedTo;
        return $this;
    }

    /**
     * Remove relatedTo
     *
     * @param Cygnus\OlyticsBundle\Model\Metadata\RelatedEntity $relatedTo
     */
    public function removeRelatedTo(RelatedEntity $relatedTo)
    {
        $key = array_search($relatedTo, $this->relatedTo, true);
        if ($key !== false) {
            unset($this->relatedTo[$key]);
        }
        return $this;
    }

    public function setRelatedTo(array $relatedTo)
    {
        foreach ($relatedTo as $relatedEntity) {
            if ($relatedEntity instanceof RelatedEntity) {
                $this->addRelatedTo($relatedEntity);
            }
        }
    }

    /**
     * Get relatedTo
     *
     * @return Doctrine\Common\Collections\Collection $relatedTo
     */
    public function getRelatedTo()
    {
        return $this->relatedTo;
    }

    /**
     * Add keyValue
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addKeyValue($key, $value)
    {
        $this->keyValues[$key] = $value;
        return $this;
    }

    /**
     * Remove keyValue
     *
     * @param string $key
     * @return self
     */
    public function removeKeyValue($key)
    {
        if (array_key_exists($this->keyValues, $key)) {
            unset($this->keyValues[$key]);
        }
        return $this;
    }

    /**
     * Set keyValues
     *
     * @param hash $keyValues
     * @return self
     */
    public function setKeyValues(array $keyValues)
    {
        $this->keyValues = $keyValues;
        return $this;
    }

    /**
     * Get keyValues
     *
     * @return hash $keyValues
     */
    public function getKeyValues()
    {
        return $this->keyValues;
    }

    /**
     * Set updatedAt
     *
     * @param mixed $updatedAt
     * @return self
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = DateType::convert($updatedAt);
        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return DateTime $updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
