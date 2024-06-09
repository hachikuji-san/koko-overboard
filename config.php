<?php
return $conf = [
    'boardTitle' => 'Overboard @ lolisecks.net',
    'boardSubTitle' => 'overboard for kokonotsuba boards',
    'home' => "https://example.com/main.php",
    'toplinks' => 'toplinks.txt',
    
    'threadsPerPage' => 10,
    
    'thumbExt' => 'png',
    
    'dbInfo' => [
        'host'     => 'localhost',
        'username' => 'sqlusername',
        'password' => 'sqluserpass',
    ],
    //boards to be displayed
    'boards' => [
        'b' => [
            'dbname' => 'boarddb',
            'tablename' => 'imglog',
            'boardname' => '/b/',
            'boardurl' => 'example.com/b/',
            'imageDir' => 'example.com/b/src/',
        ],
        //add more boards here
    ],
 ];