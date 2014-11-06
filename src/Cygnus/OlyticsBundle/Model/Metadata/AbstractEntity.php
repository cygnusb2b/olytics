<?php
namespace Cygnus\OlyticsBundle\Model\Metadata;

use Cygnus\OlyticsBundle\Model\Exception\InvalidEntityException;

abstract class AbstractEntity
{
    /**
     * The metadata type. Examples: content, ad, page
     *
     * @var string
     */
    protected $type;

    /**
     * The client identifier for this entity
     *
     * @var mixed
     */
    protected $clientId;


    public function isValid()
    {
        if (empty($this->type)) {
            throw InvalidEntityException::missingType($this->type);
        }

        if (empty($this->clientId)) {
            throw InvalidEntityException::missingClientId($this->clientId);
        }
        return true;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = strtolower($type);
        return $this;
    }

    /**
     * Get type
     *
     * @return string $type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set clientId
     *
     * @param mixed $clientId
     * @return self
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * Get clientId
     *
     * @return mixed $clientId
     */
    public function getClientId()
    {
        return $this->clientId;
    }
}