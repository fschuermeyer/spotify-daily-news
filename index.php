<?php 

require 'vendor/autoload.php';

session_start();


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * DailyNews
 * @author Felix Schürmeyer
 * @version 0.1
 * 
 * Not Ready for Production!
 * 
 */
class DailyNews{
    
    /**
     * session
     * Spotify Session
     * @var SpotifyWebAPI\Session
     */
    private $session;
    
    /**
     * api
     * API Connection
     * @var SpotifyWebAPI/SpotifyWebAPI
     */
    private $api;
    
    /**
     * myShows
     * Podcast you Like
     * @var array
     */
    private $myShows = [
        "Tageschau"         => 'spotify:show:4QwUbrMJZ27DpjuYmN4Tun',
        "Kurz Informiert"   => 'spotify:show:2etf1jog8leNHbnhIArM9Z',
        "Spiegel"           => 'spotify:show:44BjJ1tkcQHVNlDf6sIrel',
        "ZeitOnline"        => 'spotify:show:4ymTKvZm5SGMtk4NlmRyKv',
        "Deutschlandfunk"   => 'spotify:show:4eYPgoQH9VLTfgAxIbwHqs',
        "SZ"                => 'spotify:show:7vFmyZR3rDf61V69UpRXbA',
        "Daily Zitat"       => 'spotify:show:5lAEpOVZ0UfGg9JOlfc3S5',
        "Man lernt nie aus" => 'spotify:show:0kn1vbUqm201kszYv18AUI',
        "An diesem Tag"     => 'spotify:show:0clGHleJDnSMbRE1ytXk9w',
        "Zurück zum Thema"  => 'spotify:show:2Wu6s3wtqlqNezSBmDqfK2',
        "1000 Antworten"    => 'spotify:show:2vO8svMNB409TKDMbHFrEn',
        'Daily Good News'   => 'spotify:show:1XuExf1T8PsIKay8KH2Lzv'
    ];
    
    /**
     * ignorePlaylist
     * Playlist ignore for Search Tracks
     * @var array
     */
    private $ignorePlaylist = ['Daily Drive','Daily News'];
    
    /**
     * myPlaylists
     * Playlist in My Spotify Account
     * @var array
     */
    private $myPlaylists;
    
    /**
     * response
     * Error/Success Messages
     * @var string
     */
    private $response;
    
    /**
     * songCounts
     * songs between Podcast
     * @var int
     */
    private $songCounts = 3;
    
    /**
     * __construct
     * Create the Playlist
     * @return void
     */
    function __construct(){

        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        $this->response = "Dein Spotify Konto muss Verbunden werden.";

        $this->go = false;

        if(!empty($_SESSION['refresh']) && !empty($_SESSION['access'])){
            $this->session = new SpotifyWebAPI\Session(
                $_ENV['CLIENT_ID'],
                $_ENV['CLIENT_SECRET'],
                $_ENV['LOGIN_URL'] . 'login.php'
            );
            
            try {
                $this->session->refreshAccessToken($_SESSION['refresh']);

                $this->api = new SpotifyWebAPI\SpotifyWebAPI();

                $this->api->setAccessToken($_SESSION['access']);

                $this->myPlaylists = $this->api->getMyPlaylists()->items;
            } catch (SpotifyWebAPI\SpotifyWebAPIAuthException $th) {
                $this->response = "Dein Login ist Abgelaufen.";

                return false;
            } catch (SpotifyWebAPI\SpotifyWebAPIException $th) {
                $this->response = "Dein Login ist Abgelaufen.";

                return false;
            }

            $this->go = true;
           
            shuffle($this->myShows);
    
            if($this->checkPlaylistExists()){
                $playlistContent = $this->createPlaylistContent();
                $playlistID      = $this->createDailyPlaylist();
                
                if($playlistContent != false){
                    $this->api->addPlaylistTracks($playlistID,$playlistContent);
        
                    $this->api->updatePlaylistImage($playlistID,base64_encode(file_get_contents('playlist/' . date('N') . '-news.png')));
                
                    $this->response = "<strong>Erfolgreich</strong> neue Spotify Playlist mit " . count($playlistContent) . " Tracks erstellt."; 
                }else{
                    $this->response = "<strong>Fehler</strong> - der Playlist Content konnte nicht Generiert werden, versuche es erneut.";
                }
     
            }else{
                $this->response = "Die <strong>Daily News Playlist</strong> Exestiert Bereits für Heute.";

                $this->response .= "<span>Unter dem Namen: " . $this->playlistName() . '<span>';
            }            
        }
    }
    
    /**
     * response
     * Get the Response Messages
     * @return string
     */
    public function response(): string{
        return $this->response;
    }
    
    /**
     * createPlaylistContent
     * Create Array of Podcast and Songs
     * @return array|bool
     */
    private function createPlaylistContent(){
        $shows = $this->pullEpisode($this->myShows);
        $songs = $this->getRandomPlaylistTracks($shows);

        $songs = array_chunk($songs,$this->songCounts);
        $chunk = [];

        foreach($songs as $key => $chunk){
            if(isset($shows[$key])):
                $songs[$key][] = $shows[$key];

                $songs[$key] = array_reverse($songs[$key]);
            endif;
        }

        $content = array_chunk(array_unique($this->flatten($songs)),99);

        if(isset($content[0])){
            return $content[0];
        }

        return false;
    }
    
    /**
     * pullEpisode
     * Get the Last Episode from the Selected Podcast
     * @param  mixed $shows
     * @return array
     */
    private function pullEpisode(array $shows): array{
        $podcasts = [];

        foreach ($shows as $show):
            $episodes = $this->api->getShowEpisodes(str_replace('spotify:show:','',$show));
            $episode = $episodes->items[0];

            $podcasts[] = $episode->uri;
        endforeach;

        return $podcasts;
    }
    
    /**
     * playlistName
     * Name of the Today Playlist
     * @return string
     */
    private function playlistName(): string{
        $monate = [
                1=>"Januar",
                2=>"Februar",
                3=>"März",
                4=>"April",
                5=>"Mai",
                6=>"Juni",
                7=>"Juli",
                8=>"August",
                9=>"September",
                10=>"Oktober",
                11=>"November",
                12=>"Dezember"
        ];

        return date("j") .'. ' . $monate[date('n')] . ' ' . date('Y') . " - Daily News";
    }
    
    /**
     * checkPlaylistExists
     * Check if Playlist Exists to Prevent Create Duplicates
     * @return bool
     */
    private function checkPlaylistExists(): bool{
        foreach ($this->myPlaylists as $key => $value) {
            if($this->checkName($value->name,[$this->playlistName()]) === true){
                return false;
            }
        }

        return true;
    }
    
    /**
     * createDailyPlaylist
     *
     * @return string
     */
    private function createDailyPlaylist(): string{
        return $this->api->createPlaylist(['name' => $this->playlistName()])->id;
    }
    
    /**
     * randPlaylist
     * get a Random Playlist
     * @return stdObject
     */
    private function randPlaylist(){
        return $this->myPlaylists[rand(0,count($this->myPlaylists) - 1)];
    }
    
    /**
     * checkName
     *
     * @param  string $stringName
     * @param  array $check
     * @return bool
     */
    private function checkName(string $stringName,array $check){
        foreach($check as $unwanted):
            if(strpos($stringName,$unwanted) !== false):
                return true;
            endif;
        endforeach;

        return false;
    }
    
    /**
     * recursiveRandPlaylist
     *
     * @param  array $check
     * @param  string $name
     * @param  stdObject|array $obj
     * @return stdObject|array
     */
    private function recursiveRandPlaylist(array $check,string $name,$obj){
        if($this->checkName($name,$check) || is_array($obj)){
            $obj = $this->randPlaylist();
            $obj = $this->recursiveRandPlaylist($check,$obj->name,$obj);
        }

        return $obj;
    }
    
    /**
     * getRandomPlaylistTracks
     *
     * @param  array $shows
     * @return array
     */
    private function getRandomPlaylistTracks($shows){
        $obj = $this->recursiveRandPlaylist($this->ignorePlaylist,"empty",[]);
        $random_playlist = $obj->id;

        if(empty($this->playlistName)):
            $this->playlistName = $obj->name;
        endif;

        $tracks = $this->api->getPlaylistTracks($random_playlist);

        $music = [];

        foreach($tracks->items as $track){
            $music[] = $track->track->uri;
        }

        if(count($music) < (count($shows) * 4)){
            $music = array_merge($music,$this->getRandomPlaylistTracks($shows));
        }

        return $music;
    }
    
    /**
     * flatten
     * Array Flatten
     * @param  mixed $array
     * @return array
     */
    private function flatten($array): array {
        if (!is_array($array)) {
            return array($array);
        }

        $result = array();
        foreach ($array as $value) {
            $result = array_merge($result, $this->flatten($value));
        }

        return $result;
    }

}

$r = new DailyNews();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily News Playlist Creation</title>
    <style>
        *{
            font-family: "Lato","Roboto",sans-serif;
            font-size: 19px;
        }    

        body{
            display: flex;
            justify-content: center;
            align-items: center;
            align-content: center;
            height: 100vh;
            background: #121212;
            color: #fff;
        }

        body .response{
            display: flex;
            justify-content: center;
            align-items: center;
            align-content: center;
            flex-direction: column;
            background: #181818;
            padding: 40px;
            border-radius: 6px;
            text-align: center;
        }

        body .response img{
            border-radius: 6px;
        }

        body .response div{
            margin-top: 40px;
        }

        body .response a{
            padding: 15px 45px;
            margin-top: 15px;
            background: pink;
            text-decoration: none;
            background: #1db954;
            color: #fff;
            border-radius: 50px;
        }

        strong{
            color: #1db954;
        }

        span{
            display: block;
            font-size: 16px;
            color: #656565;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="response">
        <img src="playlist/<?php echo date('N') ?>-news.png">
        <div><?php echo $r->response(); ?>
            <?php if(!empty($r->playlistName)): ?>
                <span>Musik für Daily News Heute: <?php echo $r->playlistName; ?></span>
            <?php endif; ?>
        </div>
        <?php if($r->go == false){ ?>
            <a href="/login.php">Spotify Verbinden</a>
        <?php } ?>
    </div>
</body>
</html>