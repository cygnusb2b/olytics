<?php
namespace Cygnus\OlyticsBundle\Event\Website;

use Cygnus\OlyticsBundle\Event\RequestManager;
use Cygnus\OlyticsBundle\Event\RequestInterface;
use Cygnus\OlyticsBundle\Event\RequestFactoryInterface;
use Symfony\Component\HttpFoundation\Request as KernalRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

use Cygnus\OlyticsBundle\Model\Session\WebsiteSession;
use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Metadata\RelatedEntity;
use Cygnus\OlyticsBundle\Model\Event\WebsiteEvent;

use Cygnus\OlyticsBundle\Event\PersistorInterface;


class WebsiteRequestManager extends RequestManager
{
    /**
     * The Request Factory for creating EventRequests from a Request
     *
     * @var RequestFactoryInterface
     */
    protected $requestFactory;

    /**
     * The database Persister for writing objects to the database
     *
     * @var PersistorInterface
     */
    protected $persistor;

    /**
     * The EventRequest being processed
     *
     * @var EventRequest
     */
    protected $eventRequest;

    /**
     * The Session being processed
     *
     * @var Session
     */
    protected $session;

    protected $container;

    protected $eventEntity;

    protected $event;

    protected $relatedEntities = array();

    /**
     * Constructor. Injects the request factory and the database persister
     *
     * @param  RequestFactoryInterface $requestFactory
     * @param  PersistorInterface      $persistor
     * @return void
     */
    public function __construct(RequestFactoryInterface $requestFactory, PersistorInterface $persistor)
    {
        $this->requestFactory = $requestFactory;
        $this->persistor = $persistor;
    }

    /**
     * Manages the incoming event request
     *
     * @param  RequestInterface $eventRequest
     * @return void
     */
    public function manage(RequestInterface $eventRequest)
    {
        $this->eventRequest = $eventRequest;
        $this->manageEvent();
    }

    /**
     * Persists the website event (and it's related session and entities) to the database
     *
     * @return void
     */
    public function persist() {
        if ($this->event instanceof WebsiteEvent && $this->event->isValid()) {
            $this->persistor->persist(
                $this->event,
                $this->relatedEntities,
                $this->eventRequest->getAccount(),
                $this->eventRequest->getProduct(),
                $this->eventRequest->appendCustomer
            );
        }
    }

    /**
     * Adds a related entity for this request
     *
     * @param  Entity $entity
     * @return void
     */
    protected function addRelatedEntity(Entity $entity)
    {
        $this->relatedEntities[] = $entity;
    }

    /**
     * Manages the incoming event
     * Will create/hydrate the session, the event, and the event's related entities
     *
     * @param  Entity $entity
     * @return void
     */
    protected function manageEvent()
    {
        // Manage the incoming session
        $this->manageSession();

        // Get the event data from the incoming request
        $eventData = $this->eventRequest->getEvent();

        // Get the entity data from the event
        $entityData = $eventData->get('entity');

        if (!is_array($entityData)) $entityData = [];

        // Hydrate the event entity
        $this->eventEntity = $this->hydrateEntity($entityData);


        // Create the event object
        $this->event = new WebsiteEvent();
        $this->event->setAction($eventData->get('action'));
        $this->event->setEntity($this->eventEntity);
        $this->event->setCreatedAt(time());

        if (!is_null($eventData->get('data'))) {
            $this->event->setData($eventData->get('data'));
        }

        // Set any related entities to the event
        $relatedEntityData = $eventData->get('relatedEntities');
        if (is_array($relatedEntityData) && !empty($relatedEntityData)) {
            foreach ($relatedEntityData as $relatedEntity) {
                $relEntityObj = $this->hydrateEntity($relatedEntity);
                if ($relEntityObj->isValid()) {
                    $this->event->addRelatedEntity($relEntityObj);
                }

            }
        }
        // Set the session to the event
        $this->event->setSession($this->session);
    }

    /**
     * Manages/hydrates the incoming session from the event request
     *
     * @return void
     */
    protected function manageSession()
    {
        $this->session = new WebsiteSession();
        foreach ($this->eventRequest->getSession() as $key => $value) {

            if ($key == 'createdAt') {
                $value = time();
            }

            $method = 'set' . ucwords($key);
            if (method_exists($this->session, $method)) {
                $this->session->$method($value);
            }
        }
    }

    /**
     * Hydrates an Entity object from an array of entity data
     *
     * @param  array  $entityData
     * @return Entity
     */
    protected function hydrateEntity(array $entityData)
    {
        $entity = new Entity();
        foreach ($entityData as $key => $value) {
            $method = 'set' . ucwords($key);
            if (method_exists($entity, $method)) {
                $entity->$method($value);
            }
        }

        // Handle relatedTo
        if (array_key_exists('relatedTo', $entityData) && is_array($entityData['relatedTo']) && !empty($entityData['relatedTo'])) {
            foreach ($entityData['relatedTo'] as $relatedEntityData) {
                $relatedEntity = $this->hydrateRelatedEntity($relatedEntityData);
                if ($relatedEntity->isValid()) {
                    $entity->addRelatedTo($relatedEntity);
                    $this->addRelatedEntity($this->hydrateEntity($relatedEntityData));
                }
            }
        }

        $entity->setUpdatedAt(time());
        if ($entity->getType() == 'page') {
            $this->appendPageInfo($entity);
        }
        return $entity;
    }

    /**
     * Hydrates a RelatedEntity object from an array of entity data
     *
     * @param  array  $relatedEntityData
     * @return RelatedEntity
     */
    protected function hydrateRelatedEntity(array $relatedEntityData)
    {
        $relatedEntity = new RelatedEntity();
        foreach ($relatedEntityData as $key => $value) {
            $method = 'set' . ucwords($key);
            if (method_exists($relatedEntity, $method)) {
                $relatedEntity->$method($value);
            }
        }
        return $relatedEntity;
    }

    /**
     * Appends host, path, and query string data from a URL to a page Entity
     *
     * @param  Entity  &$page
     * @return void
     */
    protected function appendPageInfo(Entity &$page)
    {
        $keyValues = $page->getKeyValues();
        if (array_key_exists('url', $keyValues)) {
            $parsedUrl = @parse_url($keyValues['url']);
            if (is_array($parsedUrl)) {
                foreach (['host', 'path', 'query'] as $type) {
                    if (array_key_exists($type, $parsedUrl)) {
                        $page->addKeyValue($type, $parsedUrl[$type]);
                    }
                }
            }
        }
    }

    /**
     * Creates a new EventRequest and manages it
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @param  string $vertical The vertical
     * @return void
     */
    public function createAndManage(KernalRequest $request, $account, $product)
    {
        $eventRequest = $this->createRequestFromFactory($request, $account, $product);
        $this->manage($eventRequest);
    }

    /**
     * Creates a new EventRequest from a kernel Request (using the factory)
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @param  string $vertical The vertical
     * @return void
     */
    public function createRequestFromFactory(KernalRequest $request, $account, $product)
    {
        return $this->requestFactory->createFromRequest($request, $account, $product);
    }
}
