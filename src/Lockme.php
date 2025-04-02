<?php
namespace Lockme\SDK;

use DateTime;
use Exception;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Lockme\OAuth2\Client\Provider\Lockme as LockmeProvider;
use RuntimeException;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use function is_callable;

/**
 * Lockme SDK object
 */
class Lockme
{
    /**
     * OAuth2 provider
     */
    private AbstractProvider $provider;

    private LockFactory $lockFactory;

    private AccessToken $accessToken;

    /**
     * Object constructor
     * @param array $options  Options for Lockme Provider
     */
    public function __construct(array $options = [])
    {
        if (!isset($options['scopes'])) {
            $options['scopes'] = ['rooms_manage'];
        }

        $this->provider = $options['provider'] ?? new LockmeProvider($options);
        $this->lockFactory = new LockFactory($this->lockStore($options));
    }

    private function lockStore(array $options = []): BlockingStoreInterface
    {
        try {
            return new SemaphoreStore();
        } catch (InvalidArgumentException) {
            // Semaphore is not supported
        }

        return new FlockStore($options['tmp_dir'] ?? sys_get_temp_dir());
    }

    /**
     * Generate authorization URL
     * @param  array  $scopes Array of requested scopes
     * @return string         Redirect URL
     */
    public function getAuthorizationUrl(array $scopes = [], ?array &$session = null): string
    {
        if (null === $session) {
            $session = &$_SESSION;
        }
        $authorizationUrl = $this->provider->getAuthorizationUrl([
            'scope' => implode(' ', $scopes)
        ]);
        $session['oauth2_lockme_state'] = $this->provider->getState();
        return $authorizationUrl;
    }

    /**
     * Get Access token from AuthCode
     * @param  string  $code  AuthCode
     * @param  string  $state  State code
     * @return AccessToken       Access Token
     * @throws Exception
     */
    public function getTokenForCode(string $code, string $state, ?string $sessionState = null): AccessToken
    {
        $sessionState ??= $_SESSION['oauth2_lockme_state'] ?? null;
        if (session_id() && isset($_SESSION['oauth2_lockme_state'])) {
            unset($_SESSION['oauth2_lockme_state']);
        }
        if ($state !== $sessionState) {
            throw new RuntimeException("Wrong state");
        }

        $this->accessToken = $this->provider->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        return $this->accessToken;
    }

    /**
     * Refresh access token
     * @param  AccessToken|null  $accessToken  Access token
     * @return AccessToken        Refreshed token
     * @throws IdentityProviderException
     */
    public function refreshToken(?AccessToken $accessToken = null): AccessToken
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
     * @throws Exception
     * @deprecated use loadAccessToken to solve race conditions on token refreshing
     */
    public function setDefaultAccessToken(string|AccessToken $token): AccessToken
    {
        if (is_string($token)) {
            $this->accessToken = new AccessToken(json_decode($token, true));
        } elseif ($token instanceof AccessToken) {
            $this->accessToken = $token;
        }

        if ($this->accessToken->hasExpired()) {
            $this->refreshToken();
        }
        return $this->accessToken;
    }

    private function reloadToken(callable $load): void
    {
        $token = $load();
        if (is_string($token)) {
            $this->accessToken = new AccessToken(json_decode($token, true));
        } elseif ($token instanceof AccessToken) {
            $this->accessToken = $token;
        } else {
            throw new RuntimeException("Incorrect access token");
        }
    }

    /**
     * @throws Exception
     */
    public function loadAccessToken(callable $load, ?callable $save = null): AccessToken
    {
        $this->reloadToken($load);

        if($this->accessToken->hasExpired()) {
            $lock = $this->lockFactory->createLock('lockme-refresh-token');
            $lock->acquire(true);
            $this->reloadToken($load);

            if($this->accessToken->hasExpired()) {
                $this->refreshToken();
                if (is_callable($save)) {
                    $save($this->accessToken);
                }
            }
            $lock->release();
        }

        return $this->accessToken;
    }

    /**
     * Send message to /test endpoint
     *
     * @throws IdentityProviderException
     */
    public function Test(AccessToken|string|null $accessToken = null): string
    {
        return $this->provider->executeRequest("GET", "/test", $accessToken ?: $this->accessToken);
    }

    /**
     * Get list of available rooms
     *
     * @throws IdentityProviderException
     */
    public function RoomList(AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("GET", "/rooms", $accessToken ?: $this->accessToken);
    }

    /**
     * Get reservation data
     * @param int $roomId Room ID
     * @param string $id Booking ID
     * @param AccessToken|string|null $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function Reservation(int $roomId, string $id, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("GET", "/room/$roomId/reservation/$id", $accessToken ?: $this->accessToken);
    }

    /**
     * Add new reservation
     * @param  array  $data  Reservation data
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return int
     * @throws Exception
     */
    public function AddReservation(array $data, AccessToken|string|null $accessToken = null): int
    {
        if (!$data['roomid']) {
            throw new RuntimeException("No room ID");
        }
        if (!$data["date"]) {
            throw new RuntimeException("No date");
        }
        if (!$data["hour"]) {
            throw new RuntimeException("No hour");
        }
        return $this->provider->executeRequest("PUT", "/room/{$data['roomid']}/reservation", $accessToken ?: $this->accessToken, $data);
    }

    /**
     * Delete reservation
     * @param  int  $roomId  Room ID
     * @param  string  $id  Reservation ID
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return bool
     * @throws IdentityProviderException
     */
    public function DeleteReservation(int $roomId, string $id, AccessToken|string|null $accessToken = null): bool
    {
        return $this->provider->executeRequest("DELETE", "/room/$roomId/reservation/$id", $accessToken ?: $this->accessToken);
    }

    /**
     * Delete reservation by external id
     * @param  int  $roomId  Room ID
     * @param  string  $externalId  External Reservation ID
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return bool
     * @throws IdentityProviderException
     */
    public function DeleteReservationByExternalId(int $roomId, string $externalId, AccessToken|string|null $accessToken = null): bool
    {
        return $this->provider->executeRequest("DELETE", "/room/$roomId/reservation/ext/$externalId", $accessToken ?: $this->accessToken);
    }

    /**
     * Edit reservation
     * @param  int  $roomId  Room ID
     * @param  string  $id  Reservation ID
     * @param  array  $data  Reservation data
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return array
     * @throws IdentityProviderException
     */
    public function EditReservation(int $roomId, string $id, array $data, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("POST", "/room/$roomId/reservation/$id", $accessToken ?: $this->accessToken, $data);
    }

    /**
     * @param  int  $roomId  Room ID
     * @param  string  $id  Reservation ID
     * @param  array  $data  Move data - array with roomid, date (Y-m-d) and hour (H:i:s)
     * @param  string|AccessToken|null  $accessToken  Access token
     * @return array
     * @throws IdentityProviderException
     */
    public function MoveReservation(int $roomId, string $id, array $data, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("POST", "/room/$roomId/reservation/$id/move", $accessToken ?: $this->accessToken, $data);
    }

    /**
     * @param  int  $roomId
     * @param  DateTime  $date
     * @param  string|AccessToken|null  $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function GetReservations(int $roomId, DateTime $date, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("GET", "/room/$roomId/reservations/".$date->format("Y-m-d"), $accessToken ?: $this->accessToken);
    }

    /**
     * Get resource owner
     * @param  string|AccessToken|null $accessToken Access token
     * @return ResourceOwnerInterface              Resource owner
     */
    public function getResourceOwner(AccessToken|string|null $accessToken = null): ResourceOwnerInterface
    {
        return $this->provider->getResourceOwner($accessToken ?: $this->accessToken);
    }

    /**
     * Get callback message details
     * @param int $messageId Message ID
     * @param AccessToken|string|null $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function GetMessage(int $messageId, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("GET", "/message/$messageId", $accessToken ?: $this->accessToken);
    }

    /**
     * Mark callback message as read
     * @param int $messageId Message ID
     * @param AccessToken|string|null $accessToken
     * @return bool
     * @throws IdentityProviderException
     */
    public function MarkMessageRead(int $messageId, AccessToken|string|null $accessToken = null): bool
    {
        return $this->provider->executeRequest("POST", "/message/$messageId", $accessToken ?: $this->accessToken);
    }

    /**
     * @param int $roomId
     * @param DateTime $date
     * @param AccessToken|string|null $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function GetDateSettings(int $roomId, DateTime $date, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("GET", "/room/$roomId/date/".$date->format("Y-m-d"), $accessToken ?: $this->accessToken);
    }

    /**
     * @param int $roomId
     * @param DateTime $date
     * @param array $settings
     * @param AccessToken|string|null $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function SetDateSettings(int $roomId, DateTime $date, array $settings, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("POST", "/room/$roomId/date/".$date->format("Y-m-d"), $accessToken ?: $this->accessToken, $settings);
    }

    /**
     * @param int $roomId
     * @param DateTime $date
     * @param AccessToken|string|null $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function RemoveDateSettings(int $roomId, DateTime $date, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("DELETE", "/room/$roomId/date/".$date->format("Y-m-d"), $accessToken ?: $this->accessToken);
    }

    /**
     * @param int $roomId
     * @param int $day 0 - Monday, 1 - Tuesday, ..., 6 - Sunday
     * @param AccessToken|string|null $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function GetDaySettings(int $roomId, int $day, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("GET", "/room/$roomId/day/$day", $accessToken ?: $this->accessToken);
    }

    /**
     * @param int $roomId
     * @param int $day 0 - Monday, 1 - Tuesday, ..., 6 - Sunday
     * @param array $settings
     * @param AccessToken|string|null $accessToken
     * @return array
     * @throws IdentityProviderException
     */
    public function SetDaySettings(int $roomId, int $day, array $settings, AccessToken|string|null $accessToken = null): array
    {
        return $this->provider->executeRequest("POST", "/room/$roomId/day/$day", $accessToken ?: $this->accessToken, $settings);
    }
}
