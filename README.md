# LockMe SDK

PHP SDK for interacting with the LockMe API. This library provides a simple interface for authentication and making API calls to the LockMe platform.

## Requirements

- PHP 7.2 or higher
- ext-json
- Composer

## Installation

You can install the package via composer:

```bash
composer require lustmored/lockme-sdk
```

## Configuration

To use the SDK, you need to create an instance with your client credentials:

```php
use Lockme\SDK\Lockme;

$sdk = new Lockme([
    "clientId" => 'YOUR_CLIENT_ID',
    "clientSecret" => "YOUR_CLIENT_SECRET",
    "redirectUri" => 'YOUR_REDIRECT_URI',
    // Optional: Custom API domain (defaults to https://api.lock.me)
    "apiDomain" => 'https://api.lock.me'
]);
```

## Authentication

The SDK uses OAuth2 for authentication. Here's how to implement the authentication flow:

### Step 1: Get Authorization URL

```php
// Define the scopes you need - rooms_manage is required for booking operations
$authUrl = $sdk->getAuthorizationUrl(['rooms_manage']);

// Redirect the user to the authorization URL
header('Location: ' . $authUrl);
exit;
```

### Step 2: Handle the Callback

```php
// In your callback URL handler
$code = $_GET['code'];
$state = $_GET['state'];

try {
    // Exchange the authorization code for an access token
    $accessToken = $sdk->getTokenForCode($code, $state);

    // Save the token for future use
    file_put_contents('token.json', json_encode($accessToken));
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

### Step 3: Use the Saved Token

```php
try {
    // Load the token from storage and set up automatic saving when refreshed
    $sdk->loadAccessToken(
        fn() => file_get_contents('token.json'),
        fn($token) => file_put_contents('token.json', json_encode($token))
    );

    // Now you can make API calls
    $rooms = $sdk->RoomList();
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

## API Usage Examples

### Test Connection

```php
$result = $sdk->Test();
```

### Get Room List

```php
$rooms = $sdk->RoomList();
```

### Get Reservations for a Room

```php
use DateTime;

$roomId = 123;
$date = new DateTime();
$reservations = $sdk->GetReservations($roomId, $date);
```

### Add a Reservation

```php
$data = [
    'date' => '2023-01-01',
    'hour' => '11:00:00',
    'roomid' => 123,
    'extid' => 'abc123'
];

$reservationId = $sdk->AddReservation($data);
```

### Get Reservation Details

```php
$roomId = 123;
$reservationId = 'abc123';
$reservation = $sdk->Reservation($roomId, $reservationId);
```

### Edit a Reservation

```php
$roomId = 123;
$reservationId = 'abc123';
$data = [
    'name' => 'John',
    'surname' => 'Doe',
];

$result = $sdk->EditReservation($roomId, $reservationId, $data);
```

### Delete a Reservation

```php
$roomId = 123;
$reservationId = 'abc123';
$result = $sdk->DeleteReservation($roomId, $reservationId);
```

### Get Date Settings

```php
$roomId = 123;
$date = new DateTime();
$settings = $sdk->GetDateSettings($roomId, $date);
```

### Set Date Settings

```php
$roomId = 123;
$date = new DateTime();
$settings = [
    [
        "hour" => "16:11:11",
        "pricers" => [1227]
    ],
    [
        "hour" => "18:12:12",
        "pricers" => [1227]
    ],
    [
        "hour" => "18:12:12",
        "pricers" => [1227, 1228]
    ]
];

$result = $sdk->SetDateSettings($roomId, $date, $settings);
```

## Available Methods

- `getAuthorizationUrl(array $scopes)`: Get the authorization URL for OAuth2 flow
- `getTokenForCode(string $code, string $state)`: Exchange authorization code for access token
- `refreshToken(?AccessToken $accessToken)`: Refresh an access token
- `setDefaultAccessToken($token)`: Set the default access token for API calls
- `loadAccessToken(callable $load, ?callable $save)`: Load and optionally set up saving of access token
- `Test()`: Test the API connection
- `RoomList()`: Get list of rooms
- `Reservation(int $roomId, string $id)`: Get reservation details
- `AddReservation(array $data)`: Create a new reservation
- `DeleteReservation(int $roomId, string $id)`: Delete a reservation
- `EditReservation(int $roomId, string $id, array $data)`: Edit a reservation
- `MoveReservation(int $roomId, string $id, array $data)`: Move a reservation
- `GetReservations(int $roomId, DateTime $date)`: Get reservations for a room on a specific date
- `GetMessage(int $messageId)`: Get a message
- `MarkMessageRead(int $messageId)`: Mark a message as read
- `GetDateSettings(int $roomId, DateTime $date)`: Get settings for a specific date
- `SetDateSettings(int $roomId, DateTime $date, array $settings)`: Set settings for a specific date
- `RemoveDateSettings(int $roomId, DateTime $date)`: Remove custom settings for a specific date
- `GetDaySettings(int $roomId, int $day)`: Get settings for a specific day of the week
- `SetDaySettings(int $roomId, int $day, array $settings)`: Set settings for a specific day of the week

## License

This package is licensed under the GPL-3.0-or-later License - see the [LICENSE](LICENSE) file for details.

## Author

- Jakub Caban (kuba@lock.me)
