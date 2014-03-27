<?php
namespace Cygnus\OlyticsBundle\Model\Metadata;

class RelatedEntity extends AbstractEntity
{
    /**
     * The key/value pairs of the entity relationship
     *
     * @var array
     */
    protected $relFields = array();


    /**
     * Set relFields
     *
     * @param array $relFields
     * @return self
     */
    public function setRelFields(array $relFields)
    {
        $this->relFields = $relFields;
        return $this;
    }

    /**
     * Get relFields
     *
     * @return array $relFields
     */
    public function getRelFields()
    {
        return $this->relFields;
    }

    /**
     * Add relFields
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addRelField($key, $value)
    {
        $this->relFields[$key] = $value;
        return $this;
    }

    /**
     * Remove relFields
     *
     * @param string $key
     * @return self
     */
    public function removeRelField($key)
    {
        if (array_key_exists($this->relFields, $key)) {
            unset($this->relFields[$key]);
        }
        return $this;
    }

}
