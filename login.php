<?php 

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$session = new SpotifyWebAPI\Session(
    $_ENV['CLIENT_ID'],
    $_ENV['CLIENT_SECRET'],
    $_ENV['REDIRECT_URL'] . 'login.php'
);

if (isset($_GET['code'])) {
    $session->requestAccessToken($_GET['code']);

    file_put_contents('credentials/refresh.txt',$session->getRefreshToken());
    file_put_contents('credentials/access.txt',$session->getAccessToken());

    header('Location: index.php');
} else {
    $options = [
        'scope' => [
            'ugc-image-upload',
            'user-read-recently-played',
            'user-top-read',
            'user-read-playback-position',
            'user-read-playback-state',
            'user-modify-playback-state',
            'user-read-currently-playing',
            'app-remote-control',
            'streaming',
            'playlist-modify-public',
            'playlist-modify-private',
            'playlist-read-private',
            'playlist-read-collaborative',
            'user-follow-modify',
            'user-follow-read',
            'user-library-modify',
            'user-library-read',
            'user-read-email',
            'user-read-private'
        ],
    ];

    header('Location: ' . $session->getAuthorizeUrl($options));
    die();
}

