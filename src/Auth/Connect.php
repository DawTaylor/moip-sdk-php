<?php

namespace Moip\Auth;

use JsonSerializable;
use Moip\Contracts\Authentication;
use Moip\Exceptions\InvalidArgumentException;
use Moip\Exceptions\UnexpectedException;
use Moip\Moip;
use Moip\Resource\MoipResource;
use Requests_Hooks;
use Requests_Session;
use stdClass;

/**
 * Class Connect
 *
 * For all requests involving more than one Moip Account directly, authentication through an OAuth token is required.
 * Using the OAuth 2.0 standard it is possible to authenticate to the Moip APIs and request the use of the APIs on behalf of another user.
 * In this way, another Moip user can grant you the most diverse permissions,
 * from receiving payments as a secondary receiver to even special actions like repayment of a payment.
 */
class Connect implements Authentication, JsonSerializable
{
    /**
     * @const string
     */
    const ENDPOINT_SANDBOX = 'https://connect-sandbox.moip.com.br';

    /**
     * @const string
     */
    const ENDPOINT_PRODUCTION = 'https://connect.moip.com.br';

    /**
     * @const string
     */
    const OAUTH_AUTHORIZE = '/oauth/authorize';

    /**
     * @const string
     */
    const OAUTH_TOKEN = '/oauth/token';

    /**
     * Define the type of response to be obtained. Possible values: CODE.
     *
     * @const string
     */
    const RESPONSE_TYPE = 'code';

    /**
     * Permission for creation and consultation of ORDERS, PAYMENTS, MULTI ORDERS, MULTI PAYMENTS, CUSTOMERS and consultation of LAUNCHES.
     *
     * @const string
     */
    const RECEIVE_FUNDS = 'RECEIVE_FUNDS';

    /**
     * Permission to create and consult reimbursements of ORDERS, PAYMENTS.
     *
     * @const string
     */
    const REFUND = 'REFUND';

    /**
     * Permission to consult ACCOUNTS registration information.
     *
     * @const string
     */
    const MANAGE_ACCOUNT_INFO = 'MANAGE_ACCOUNT_INFO';

    /**
     * Permission to query balance through the ACCOUNTS endpoint.
     *
     * @const string
     */
    const RETRIEVE_FINANCIAL_INFO = 'RETRIEVE_FINANCIAL_INFO';

    /**
     * Permission for bank transfers or for Moip accounts through the TRANSFERS endpoint.
     *
     * @const string
     */
    const TRANSFER_FUNDS = 'TRANSFER_FUNDS';

    /**
     * Permission to create, change, and delete notification preferences through the PREFERENCES endpoint.
     *
     * @const string
     */
    const DEFINE_PREFERENCES = 'DEFINE_PREFERENCES';

    /**
     * List all scopes.
     *
     * @const array
     */
    const SCOPE_ALL  = [
        self::RECEIVE_FUNDS,
        self::REFUND,
        self::MANAGE_ACCOUNT_INFO,
        self::RETRIEVE_FINANCIAL_INFO,
        self::TRANSFER_FUNDS,
        self::DEFINE_PREFERENCES,
    ];

    /**
     * Unique identifier of the application that will be carried out the request.
     *
     * @var string (16)
     */
    private $client_id;

    /**
     * Client Redirect URI.
     *
     * @var string (255)
     */
    private $redirect_uri;

    /**
     * Endpoint.
     *
     * @var string
     */
    private $endpoint;

    /**
     * Permissions that you want (Possible values depending on the feature.).
     *
     * @var array
     */
    private $scope = [];

    /**
     * @var Requests_Session HTTP session configured to use the moip API.
     */
    private $session;

    /**
     * Connect constructor.
     *
     * @param string $client_id
     * @param string $redirect_uri
     * @param array $scope
     * @param string $endpoint
     */
    public function __construct($client_id = '', $redirect_uri = '', $scope = [], $endpoint = self::ENDPOINT_PRODUCTION)
    {
        $this->client_id = $client_id;
        $this->redirect_uri = $redirect_uri;
        $this->scope = $this->setScope($endpoint);
        $this->endpoint = $endpoint;

        $this->createNewSession();
    }

    /**
     * Creates a new Request_Session with all the default values.
     * A Session is created at construction.
     *
     * @param float $timeout         How long should we wait for a response?(seconds with a millisecond precision, default: 30, example: 0.01).
     * @param float $connect_timeout How long should we wait while trying to connect? (seconds with a millisecond precision, default: 10, example: 0.01)
     */
    public function createNewSession($timeout = 30.0, $connect_timeout = 30.0)
    {
        if (function_exists('posix_uname')) {
            $uname = posix_uname();
            $user_agent = sprintf('Mozilla/4.0 (compatible; %s; PHP/%s %s; %s; %s)',
                Moip::CLIENT, PHP_SAPI, PHP_VERSION, $uname['sysname'], $uname['machine']);
        } else {
            $user_agent = sprintf('Mozilla/4.0 (compatible; %s; PHP/%s %s; %s)',
                Moip::CLIENT, PHP_SAPI, PHP_VERSION, PHP_OS);
        }
        $sess = new Requests_Session($this->endpoint);
        $sess->options['timeout'] = $timeout;
        $sess->options['connect_timeout'] = $connect_timeout;
        $sess->options['useragent'] = $user_agent;
        $this->session = $sess;
    }

    /**
     * URI of oauth.
     *
     * @param $auth_endpoint
     *
     * @return string
     */
    public function getAuthUrl($auth_endpoint)
    {
        $query_string = [
            'response_type' => self::RESPONSE_TYPE,
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => implode(',', $this->scope)
        ];

        return $auth_endpoint.self::OAUTH_AUTHORIZE.'?'.http_build_query($query_string);;
    }

    /**
     * @param bool $scope
     */
    public function setScodeAll($scope)
    {
        if (!is_bool($scope)) {
            throw new InvalidArgumentException('$scope deve ser boolean, foi passado '.gettype($scope));
        }

        if ($scope === false) {
            $this->scope = [];
        } else {
            $this->setReceiveFunds(true)
                ->setRefund(true)
                ->setManageAccountInfo(true)
                ->setRetrieveFinancialInfo(true)
                ->setTransferFunds(true)
                ->setDefinePreferences(true);
        }
    }

    /**
     * Permission for creation and consultation of ORDERS, PAYMENTS, MULTI ORDERS, MULTI PAYMENTS, CUSTOMERS and consultation of LAUNCHES.
     *
     * @param bool $receive_funds
     *
     * @throws \Moip\Exceptions\InvalidArgumentException
     *
     * @return \Moip\Auth\Connect $this
     */
    public function setReceiveFunds($receive_funds)
    {
        if (!is_bool($receive_funds)) {
            throw new InvalidArgumentException('$receive_funds deve ser boolean, foi passado '.gettype($receive_funds));
        }

        if ($receive_funds === true) {
            $this->setScope(self::RECEIVE_FUNDS);
        }

        return $this;
    }

    /**
     * Permission to create and consult reimbursements ofORDERS, PAYMENTS.
     *
     * @param bool $refund
     *
     * @throws \Moip\Exceptions\InvalidArgumentException
     *
     * @return \Moip\Auth\Connect $this
     */
    public function setRefund($refund)
    {
        if (!is_bool($refund)) {
            throw new InvalidArgumentException('$refund deve ser boolean, foi passado '.gettype($refund));
        }

        if ($refund === true) {
            $this->setScope(self::REFUND);
        }

        return $this;
    }

    /**
     * Permission to consult ACCOUNTS registration information.
     *
     * @param bool $manage_account_info
     *
     * @throws \Moip\Exceptions\InvalidArgumentException
     *
     * @return \Moip\Auth\Connect $this
     */
    public function setManageAccountInfo($manage_account_info)
    {
        if (!is_bool($manage_account_info)) {
            throw new InvalidArgumentException('$manage_account_info deve ser boolean, foi passado '.gettype($manage_account_info));
        }

        if ($manage_account_info === true) {
            $this->setScope(self::MANAGE_ACCOUNT_INFO);
        }

        return $this;
    }

    /**
     * Permission to query balance through the ACCOUNTS endpoint.
     *
     * @param bool $retrieve_financial_info
     *
     * @throws \Moip\Exceptions\InvalidArgumentException
     *
     * @return \Moip\Auth\Connect $this
     */
    public function setRetrieveFinancialInfo($retrieve_financial_info)
    {
        if (!is_bool($retrieve_financial_info)) {
            throw new InvalidArgumentException('$retrieve_financial_info deve ser boolean, foi passado '.gettype($retrieve_financial_info));
        }

        if ($retrieve_financial_info === true) {
            $this->setScope(self::RETRIEVE_FINANCIAL_INFO);
        }

        return $this;
    }

    /**
     * Permission for bank transfers or for Moip accounts through the TRANSFERS endpoint.
     *
     * @param bool $transfer_funds
     *
     * @throws \Moip\Exceptions\InvalidArgumentException
     *
     * @return \Moip\Auth\Connect $this
     */
    public function setTransferFunds($transfer_funds)
    {
        if (!is_bool($transfer_funds)) {
            throw new InvalidArgumentException('$transfer_funds deve ser boolean, foi passado '.gettype($transfer_funds));
        }

        if ($transfer_funds === true) {
            $this->setScope(self::TRANSFER_FUNDS);
        }

        return $this;
    }

    /**
     * Permission to create, change, and delete notification preferences through the PREFERENCES endpoint.
     *
     * @param bool $define_preferences
     *
     * @throws \Moip\Exceptions\InvalidArgumentException
     *
     * @return $this
     */
    public function setDefinePreferences($define_preferences)
    {
        if (!is_bool($define_preferences)) {
            throw new InvalidArgumentException('$define_preferences deve ser boolean, foi passado '.gettype($define_preferences));
        }

        if ($define_preferences === true) {
            $this->setScope(self::DEFINE_PREFERENCES);
        }

        return $this;
    }

    /**
     * Unique identifier of the application that will be carried out the request.
     *
     * @return mixed
     */
    public function getClientId()
    {
        return $this->client_id;
    }

    /**
     * Unique identifier of the application that will be carried out the request.
     *
     * @param mixed $client_id
     *
     * @return \Moip\Auth\Connect
     */
    public function setClientId($client_id)
    {
        $this->client_id = $client_id;

        return $this;
    }

    /**
     * Client Redirect URI.
     *
     * @return mixed
     */
    public function getRedirectUri()
    {
        return $this->redirect_uri;
    }

    /**
     * Client Redirect URI.
     *
     * @param mixed $redirect_uri
     *
     * @return \Moip\Auth\Connect
     */
    public function setRedirectUri($redirect_uri)
    {
        $this->redirect_uri = $redirect_uri;

        return $this;
    }

    /**
     * Permissions that you want (Possible values depending on the feature.).
     *
     * @return mixed
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * Permissions that you want (Possible values depending on the feature.).
     *
     * @param array|string $scope
     *
     * @return \Moip\Auth\Connect
     */
    public function setScope($scope)
    {
        if (! in_array($scope, self::SCOPE_ALL, true)) {
            throw new InvalidArgumentException();
        }

        if (is_array($scope)) {
            $this->scope = $scope;
        }

        $this->scope[] = $scope;

        return $this;
    }

    /**
     * Register hooks as needed.
     *
     * This method is called in {@see Requests::request} when the user has set
     * an instance as the 'auth' option. Use this callback to register all the
     * hooks you'll need.
     *
     * @see Requests_Hooks::register
     *
     * @param Requests_Hooks $hooks Hook system
     */
    public function register(Requests_Hooks &$hooks)
    {
        // TODO: Implement register() method.
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        // TODO: Implement jsonSerialize() method.
    }

    /**
     * @param string $endpoint
     *
     * @return \Moip\Auth\Connect
     */
    public function setEndpoint(string $endpoint)
    {
        if ($endpoint === self::ENDPOINT_SANDBOX || $endpoint === self::ENDPOINT_PRODUCTION) {
            $this->endpoint = $endpoint;

            return $this;
        }

        throw new InvalidArgumentException('Endpoint inválido.');
    }
}
