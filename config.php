<?php
return $conf = [
    'boardTitle' => 'Overboard @ lolisecks.net',
    'boardSubTitle' => 'overboard for kokonotsuba boards',
    'home' => "https://example.com/main.php",
    'toplinks' => 'toplinks.txt',
    
    'threadsPerPage' => 10,
    'totalThreadsPerBoard' => 20,
    
    'thumbExt' => '.png',
    
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
            'boardurl' => 'https://example.com/b/',
            'imageAddr' => 'https://example.com/b/src/',
            'imageDir' => '/path/to/image/dir',
        ],
        //add more boards here
    ],
 ];
