<?php

namespace Cygnus\OlyticsBundle\Detector;

use \AppKernel;

class BotDetector implements DetectorInterface
{
    /**
     * JSON file and resource constants
     */
    const JSON_FILE = 'crawler-user-agents.json';
    const JSON_RESOURCE = '@CygnusOlyticsBundle/Resources/public/json';

    /**
     * An array of known bots
     *
     * @var array
     */
    protected $matchData;

    /**
     * The AppKernel instance
     *
     * @var AppKernel
     */
    protected $kernel;

    /**
     * Constructor. Injects the AppKernel to facilitate file loading from this bundle
     *
     * @param  AppKernel $kernel
     * @return void
     */
    public function __construct(AppKernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Checks if the User Agent matches a known Bot string
     *
     * @param  string $userAgent The User Agent string
     * @return bool
     */
    public function hasMatch($userAgent)
    {
        return $this->doMatch($userAgent);
    }

    /**
     * Performs the matching logic of the incoming User Agent string to the known Bot list
     *
     * @param  string $userAgent The User Agent string
     * @return bool
     */
    public function doMatch($userAgent)
    {
        $bots = $this->getMatchData();
        
        foreach ($bots as $bot) {
            if (preg_match('/' . $bot['pattern'] . '/i', $userAgent)) {
                return true;
            }
        }
        return false;

    }

    /**
     * Gets (and sets) the User Agent Bot data
     *
     * @return array
     */
    public function getMatchData()
    {
        if (is_null($this->matchData)) {
            $this->matchData = $this->getJsonFileData();
        }
        return $this->matchData;
    }

    /**
     * Retrieves and loads the Bot patterns from the Bot JSON file
     * @see https://github.com/monperrus/crawler-user-agents
     *
     * @return array
     */
    protected function getJsonFileData()
    {
        $fileLocation = self::JSON_RESOURCE . '/' . self::JSON_FILE;
        $path = $this->kernel->locateResource($fileLocation);
        return json_decode(file_get_contents($path), true);
    }
}