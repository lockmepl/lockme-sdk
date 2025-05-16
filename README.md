# LockMe SDK

PHP SDK for interacting with the LockMe API. This library provides a simple interface for authentication and making API calls to the LockMe platform.

For comprehensive API documentation, please visit [https://apidoc.lock.me/](https://apidoc.lock.me/).

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

### Get Bookings for a Room

```php
use DateTime;

$roomId = 123;
$date = new DateTime();
$reservations = $sdk->GetReservations($roomId, $date);
```

### Add a Booking

```php
$data = [
    'date' => '2023-01-01',          // Date in YYYY-MM-DD format
    'hour' => '11:00:00',            // Time in HH:MM:SS format
    'roomid' => 123,                 // Room ID
    'extid' => 'abc123',             // External ID (your system's ID)
    'name' => 'John',                // Customer's first name
    'surname' => 'Doe',              // Customer's last name
    'email' => 'john.doe@example.com', // Customer's email
    'phone' => '+1234567890',        // Customer's phone number
    'people' => 4,                   // Number of people
    'pricer' => 1,                   // Pricer ID (pricing option)
    'comment' => 'Special requests', // Booking comment
    'price' => 100.00,               // Price
];

$reservationId = $sdk->AddReservation($data);
```

### Get Booking Details

```php
$roomId = 123;
$reservationId = 'abc123';
$reservation = $sdk->Reservation($roomId, $reservationId);
```

> **Note:** Whenever a booking ID is used, you can also use an external ID (the one set via API) by using the format "ext/{id}". For example: `$reservationId = 'ext/abc123';`

### Edit a Booking

```php
$roomId = 123;
$reservationId = 'abc123';
$data = [
    'name' => 'John',               // Customer's first name
    'surname' => 'Doe',             // Customer's last name
    'email' => 'john.doe@example.com', // Customer's email
    'phone' => '+1234567890',       // Customer's phone number
    'people' => 4,                  // Number of people
    'pricer' => 1,                  // Pricer ID (pricing option)
    'comment' => 'Updated requests', // Booking comment
    'price' => 100.00,              // Price
];

$result = $sdk->EditReservation($roomId, $reservationId, $data);
```

### Delete a Booking

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
$settings = [                  // Specific hour settings
    [
        "hour" => "16:00:00",   // Time slot
        "pricers" => [1227],    // Available pricing options for this slot
    ],
    [
        "hour" => "18:00:00",
        "pricers" => [1227, 1228],
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
- `Reservation(int $roomId, string $id)`: Get booking details
- `AddReservation(array $data)`: Create a new booking
- `DeleteReservation(int $roomId, string $id)`: Delete a booking
- `EditReservation(int $roomId, string $id, array $data)`: Edit a booking
- `MoveReservation(int $roomId, string $id, array $data)`: Move a booking
- `GetReservations(int $roomId, DateTime $date)`: Get bookings for a room on a specific date
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
