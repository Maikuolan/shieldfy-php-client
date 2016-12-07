<?php

namespace Shieldfy;

use Shieldfy\Analyze\Analyzer;
use Shieldfy\Callbacks\CallbackHandler;
use Shieldfy\Exceptions\ExceptionHandler;

class Guard
{
    /**
     * @var Singleton Reference to singleton class instance
     */
    private static $instance;

    /**
     * @var api endpoint
     */
    public $apiEndpoint = 'http://api.shieldfy.io/v1';

    /**
     * @var version
     */
    public $version = '1.0.0';

    /**
     * Default configurations items.
     *
     * @var array
     */
    protected $defaults = [
        'debug'          => false,
        'action'         => 'block',
        'disabledHeaders'=> [],
    ];

    protected $config;
    protected $event;

    /**
     * Initialize Shielfy guard.
     *
     * @param array $config
     *
     * @return object
     */
    public static function init(array $config, $cache = null)
    {
        if (!isset(self::$instance)) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    private function __construct(array $config, $cache = null)
    {

        //set config container
        $config = new Config($this->defaults, $config);
        //set base non override by user config
        $config['apiEndpoint'] = $this->apiEndpoint;
        $config['rootDir'] = __dir__;
        $config['version'] = $this->version;

        $this->config = $config;

        // Defines Shieldfy's own exception handler
        $exceptionHandler = new ExceptionHandler($config);
        $exceptionHandler->setHandler();

        if ($cache == null) {
            //create a new file cache
            $cache = new Cache($exceptionHandler);
            $cache = $cache->setDriver('file', [
                'path'=> realpath($config['rootDir'].'/../tmp').'/',
            ]);
        }

        //expose useful headers
        $headers = new Headers($config);
        $headers->expose();

        //capture the current request
        $request = new Request($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);

        //capture the current user
        $user = new User($request);

        /* init api & event for further needs */
        $api = new ApiClient($config, $exceptionHandler);
        $event = new Event($api, $exceptionHandler);
        $this->event = $event;

        //install if not installed
        $install = new Install($config, $request, $event, $exceptionHandler);
        $install->run();

        $session = new Session($user, $request, $event, $cache);
        $analyzer = new Analyzer($session, $cache, $config);
        $analyzer->run();

        $result = $analyzer->getResult();

        if ($result['status'] != Analyzer::CLEAN) {
            //dangerous spotted lets report it
            $response = $event->trigger('activity', [
                'sessionId' => $session->getId(),
                'status'    => $result['status'],
                'request'   => $request->getInfo(),
                'user'      => $user->getInfo(),
                'result'    => $result,
                'history'   => $session->getHistory(),
            ]);

            $incidentId = '';
            if ($response && $response->status == 'success') {
                $incidentId = $response->incidentId;
            }

            if ($result['status'] == Analyzer::DANGEROUS && $config['action'] == 'block') {
                (new Action())->block($incidentId);
            }

            $session->save(false);
        } else {
            $session->save();
        }

        // Close Shieldfy's exception handler
        $exceptionHandler->closeHandler();
    }

    /**
     * A mirror to catchCallbacks in callback handler.
     *
     * @return void
     */
    public function catchCallbacks()
    {
        $handler = new CallbackHandler($this->config, $this->event);
        $handler->catchCallbacks();
    }
}
