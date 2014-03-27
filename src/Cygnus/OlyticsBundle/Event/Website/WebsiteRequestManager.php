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
    protected $requestFactory;

    protected $persistor;

    protected $eventRequest;

    protected $session;

    protected $container;

    protected $eventEntity;

    protected $event;

    protected $relatedEntities = array();

    public function __construct(RequestFactoryInterface $requestFactory, PersistorInterface $persistor)
    {
        $this->requestFactory = $requestFactory;
        $this->persistor = $persistor;
    }

    public function manage(RequestInterface $eventRequest)
    {
        $this->eventRequest = $eventRequest;
        $this->manageSession();
        $this->manageContainer();
        $this->manageEvent();
    }

    public function persist() {
        if ($this->event instanceof WebsiteEvent && $this->event->isValid()) {
            $this->persistor->persist(
                $this->event, 
                $this->relatedEntities,
                $this->eventRequest->getVertical(), 
                $this->eventRequest->getProduct()
            );
        }
    }

    protected function addRelatedEntity(Entity $entity)
    {
        $this->relatedEntities[] = $entity;
    }

    protected function manageEvent()
    {
        $eventData = $this->eventRequest->getEvent();
        $entityData = $eventData->get('entity');

        if (!is_array($entityData)) $entityData = array();
        $this->eventEntity = $this->hydrateEntity($entityData);

        $this->event = new WebsiteEvent();
        $this->event->setAction($eventData->get('action'));
        $this->event->setEntity($this->eventEntity);
        if (!is_null($eventData->get('createdAt'))) {
            $this->event->setCreatedAt($eventData->get('createdAt'));
        }
        if (!is_null($eventData->get('data'))) {
            $this->event->setData($eventData->get('data'));
        }
        $this->event->setContainer($this->container);
        $this->event->setSession($this->session);
    }

    protected function manageContainer()
    {
        $container = $this->eventRequest->getContainer();
        $this->container = $this->hydrateEntity($container->all());
    }

    protected function manageSession()
    {
        $this->session = new WebsiteSession();
        foreach ($this->eventRequest->getSession() as $key => $value) {
            $method = 'set' . ucwords($key);
            if (method_exists($this->session, $method)) {
                $this->session->$method($value);
            }
        }
    }

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

    protected function appendPageInfo(Entity &$page)
    {
        $clientId = md5($page->getClientId());
        $page->setClientId($clientId);

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

    public function createAndManage(KernalRequest $request)
    {
        $eventRequest = $this->createRequestFromFactory($request);
        $this->manage($eventRequest);
    }

    public function createRequestFromFactory(KernalRequest $request)
    {
        return $this->requestFactory->createFromRequest($request);
    }
}
