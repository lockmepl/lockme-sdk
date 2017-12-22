<?php
namespace Lockme\SDK;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Lockme\OAuth2\Client\Provider\Lockme as LockmeProvider;

/**
 * Lockme SDK object
 */
class Lockme
{
    /**
     * Lockme OAuth2 provider
     * @var Lockme
     */
    private $provider = null;
    /**
     * Default access token
     * @var AccessToken
     */
    private $accessToken = null;

    /**
     * Object constructor
     * @param array $options  Options for Lockme Provider
     */
    public function __construct(array $options=[])
    {
        $this->provider = new LockmeProvider($options);
    }

    /**
     * Generate authorization URL
     * @param  array  $scopes Array of requested scopes
     * @return string         Redirect URL
     */
    public function getAuthorizationUrl($scopes = [])
    {
        $this->provider;
        $authorizationUrl = $this->provider->getAuthorizationUrl([
            'scope' => join(' ', $scopes)
        ]);
        $_SESSION['oauth2_lockme_state'] = $this->provider->getState();
        return $authorizationUrl;
    }

    /**
     * Get Access token from AuthCode
     * @param  string $code AuthCode
     * @param  string $state State code
     * @return AccessToken       Access Token
     */
    public function getTokenForCode($code, $state)
    {
        if ($state != $_SESSION['oauth2_lockme_state']) {
            unset($_SESSION['oauth2_lockme_state']);
            throw new \Exception("Wrong state");
        }
        unset($_SESSION['oauth2_lockme_state']);

        $this->accessToken = $this->provider->getAccessToken('authorization_code', [
        'code' => $code
    ]);
        return $this->accessToken;
    }

    /**
     * Refresh access token
     * @param  AccessToken|null $accessToken Access token
     * @return AccessToken        Refreshed token
     */
    public function refreshToken($accessToken = null)
    {
        $accessToken = $accessToken ?: $this->accessToken;
        $this->accessToken = $this->provider->getAccessToken('refresh_token', [
            'refresh_token' => $accessToken->getRefreshToken()
        ]);
        return $this->accessToken;
    }

    /**
     * Create default access token
     * @param string|AccessToken $token Default access token
     * @return AccessToken
     */
    public function setDefaultAccessToken($token)
    {
        if (is_string($token)) {
            $this->accessToken = new AccessToken(json_decode($token, true));
        } elseif ($token instanceof AccessToken) {
            $this->accessToken = $token;
        } else {
            throw new \Exception("Incorrect access token");
        }
        if ($this->accessToken->hasExpired()) {
            $this->refreshToken();
        }
        return $this->accessToken;
    }

    /**
     * Send message to /test endpoint
     * @param string|AccessToken|null $accessToken Access token
     * @return string
     */
    public function Test($accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/test", $accessToken ?: $this->accessToken);
    }

    /**
     * Get list of available rooms
     * @param string|AccessToken|null $accessToken Access token
     * @return array
     */
    public function RoomList($accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/rooms", $accessToken ?: $this->accessToken);
    }

    /**
     * Get reservation data
     * @param int $roomId Room ID
     * @param string $id Reservation ID
     * @param string|AccessToken|null $accessToken Access token
     * @return array
     */
    public function Reservation($roomId, $id, $accessToken = null)
    {
        return $this->provider->executeRequest("GET", "/room/{$roomId}/reservation/{$id}", $accessToken ?: $this->accessToken);
    }

    /**
     * Add new reservation
     * @param array $data        Reservation data
     * @param string|AccessToken|null $accessToken Access token
     * @return int
     */
    public function AddReservation($data, $accessToken = null)
    {
        if (!$data['roomid']) {
            throw new \Exception("No room ID");
        }
        if (!$data["date"]) {
            throw new \Exception("No date");
        }
        if (!$data["hour"]) {
            throw new \Exception("No hour");
        }
        return $this->provider->executeRequest("PUT", "/room/{$data['roomid']}/reservation", $accessToken ?: $this->accessToken, $data);
    }

    /**
     * Delete reservation
     * @param int $roomId Room ID
     * @param string $id Reservation ID
     * @param string|AccessToken|null $accessToken Access token
     * @return bool
     */
    public function DeleteReservation($roomId, $id, $accessToken = null)
    {
        return $this->provider->executeRequest("DELETE", "/room/{$roomId}/reservation/{$id}", $accessToken ?: $this->accessToken);
    }

    /**
     * Edit reservation
     * @param int $roomId Room ID
     * @param string $id Reservation ID
     * @param array $data        Reservation data
     * @param string|AccessToken|null $accessToken Access token
     * @return bool
     */
    public function EditReservation($roomId, $id, $data, $accessToken = null)
    {
        return $this->provider->executeRequest("POST", "/room/{$roomId}/reservation/{$id}", $accessToken ?: $this->accessToken, $data);
    }

    /**
     * Get resource owner
     * @param  string|AccessToken|null $accessToken Access token
     * @return ResourceOwnerInterface              Resource owner
     */
    public function getResourceOwner($accessToken = null)
    {
        return $this->provider->getResourceOwner($accessToken ?: $this->accessToken);
    }

    /**
     * Get callback message details
     * @param int $messageId Message ID
     * @return array
     */
    public function GetMessage($messageId)
    {
        return $this->provider->executeRequest("GET", "/message/{$messageId}", $accessToken ?: $this->accessToken);
    }

    /**
     * Mark callback message as read
     * @param int $messageId Message ID
     * @return bool
     */
    public function MarkMessageRead($messageId)
    {
        return $this->provider->executeRequest("POST", "/message/{$messageId}", $accessToken ?: $this->accessToken);
    }
}
