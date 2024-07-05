<?php 
    //THIS OVERBOARD SCRIPT IS WRITTEN TO USE SQLI ONLY. NO OTHER DATABASE SOFTWARE IS SUPPORTED
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conf = require_once 'config.php' ;
$boardlist = $conf['boards'];
$dbDetails = $conf['dbInfo'];

/* board filter cookie */
$filterCookie = [
    'blacklist' => array(), //empty
];

//db connection
if (!$con=mysqli_connect($dbDetails['host'], $dbDetails['username'], $dbDetails['password'])) {
    echo S_SQLCONF;	//unable to connect to DB (wrong user/pass?)
    exit;
}

//db functions
function mysqli_call($query, $errarray=false) {
    global $con;
    $resource = $con->query($query);
    if(is_array($errarray) && $resource===false) die($query);
    else return $resource;
}


//simple encapsulation to keep track of what board a thread belongs to
class boardThread {
    private $board = 'placeholder'; // plaintext board name
    private $thread = 'placeholder'; // thread number
    private $root = 'placeholder'; // bump time
    
    public function getBoard() {
        return $this->board;
    }
    public function getThread() {
        return $this->thread;
    }
    public function getRoot() {
        return $this->root;
    }
    
    public function setBoard($board) {
        $this->board = $board;
    }
    public function setThread($thread) {
        $this->thread = $thread;
    }
    public function setRoot($root) {
        $this->root = $root;
    }
    
    public function __construct($thread, $board, $root) {
        $this->thread = $thread;
        $this->board = $board;
        $this->root = $root;
    }
}

//getters
function getPostData($postNum, $dbBoardDetails, $fields='*') { // return post data
    if(is_array($postNum)){ // 取多串
        $postNum = array_filter($postNum, "is_numeric");
        if (count($postNum) == 0) return array();
        $pno = implode(',', $postNum); // ID字串
        $tmpSQL = 'SELECT '.$fields.' FROM '.$dbBoardDetails['dbname'].'.'.$dbBoardDetails['tablename'].' WHERE no IN ('.$pno.') ORDER BY no';
        if(count($postNum) > 1){ if($postNum[0] > $postNum[1]) $tmpSQL .= ' DESC'; } // 由大排到小
    }else $tmpSQL = 'SELECT '.$fields.' FROM '.$dbBoardDetails['dbname'].'.'.$dbBoardDetails['tablename'].' WHERE no = '.intval($postNum); // 取單串
    $line = mysqli_call($tmpSQL, array('Fetch the post content failed', __LINE__));
    return mysqli_fetch_all($line, MYSQLI_ASSOC);
}

/* Number of articles in thread */
function postCount($resno=0, $dbBoardDetails){
    $line = mysqli_call('SELECT COUNT(no) FROM '.$dbBoardDetails['dbname'].'.'.$dbBoardDetails['tablename'].' WHERE resto = '.intval($resno),
            array('Fetch count in thread failed', __LINE__));
        $rs = $line->fetch_row();
        $countline = $rs[0] + 1;

    $line->free();
    return $countline;
}

	/* Output list of articles */
function fetchPostList($resno, $dbBoardDetails, $start=0, $amount=0, $host=0){
		$line = array();
		$resno = intval($resno);
		$tmpSQL = 'SELECT no FROM '.$dbBoardDetails['dbname'].'.'.$dbBoardDetails['tablename'].' WHERE no = '.$resno.' OR resto = '.$resno.' ORDER BY no';
		
	    $tree = mysqli_call($tmpSQL, array('Fetch post list failed', __LINE__));
		while($rows = $tree->fetch_row()) $line[] = $rows[0];
		$tree->free();
		return $line;
}

/* Output discussion thread list */
function getThreadList($dbBoardDetails, $start=0, $amount=0, $isDESC=false){
    
    $start = intval($start); $amount = intval($amount);
    $treeline = array();
    $tmpSQL = 'SELECT no,root FROM '.$dbBoardDetails['dbname'].'.'.$dbBoardDetails['tablename'].' WHERE resto = 0 ORDER BY '.($isDESC ? 'no' : 'root').' DESC';
    if($amount) $tmpSQL .= " LIMIT {$start}, {$amount}"; // Use only when there is a specified quantity LIMIT
    $tree = mysqli_call($tmpSQL, array('Fetch thread list failed', __LINE__));
    while($rows = $tree->fetch_row()) $treeline[] = new boardThread($rows[0], $dbBoardDetails, $rows[1]);
    $tree->free();
    return $treeline;
}

//get total amount of threads
function getTotalThreadCount($dbBoardDetails){
    $tree = mysqli_call('SELECT COUNT(no) FROM '.$dbBoardDetails['dbname'].'.'.$dbBoardDetails['tablename'].' WHERE resto = 0',
        array('Fetch count of threads failed', __LINE__));
    $counttree = $tree->fetch_row(); // 計算討論串目前資料筆數
    $tree->free();
    return $counttree[0];
}

//gets total thread count across boardz
function getTotalThreadCount_acrossBoards() {
    global $boardlist;
    $total = 0; //total thread count across boards
    foreach($boardlist as $board) {
        $total += getTotalThreadCount($board);
    }
    return $total;
}

function getBumpTime($threadNo, $dbBoardDetails) {
    $line = mysqli_call('SELECT root FROM '.$dbBoardDetails['dbname'].'.'.$dbBoardDetails['tablename'].' WHERE no = '.intval($threadNo),
        array('Fetch bump time in thread failed', __LINE__));

    $bumpstr = $line->fetch_row();
    
    $line->free();
    return $bumpstr;
}

//quote text
function quote_unkfunc($comment){
    $comment = preg_replace('/(^|<br \/>)((?:&gt;|＞).*?)(?=<br \/>|$)/ui', '$1<span class="unkfunc">$2</span>', $comment);
    $comment = preg_replace('/(^|<br \/>)((?:&lt;).*?)(?=<br \/>|$)/ui', '$1<span class="unkfunc2">$2</span>', $comment);
    return $comment;
}

/* quote links */
function quote_link($comment, $dbBoardDetails){
    if(preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $comment, $matches, PREG_SET_ORDER)){
        $matches_unique = array();
        foreach($matches as $val){ if(!in_array($val, $matches_unique)) array_push($matches_unique, $val); }
            foreach($matches_unique as $val) {
                $post = getPostData(intval($val[2]), $dbBoardDetails);
                if($post){
                    $comment = str_replace($val[0], '<a href="'.$dbBoardDetails['boardurl'].'koko.php?res='.($post[0]['resto']?$post[0]['resto']:$post[0]['no']).'#p'.$post[0]['no'].'" class="quotelink">'.$val[0].'</a>', $comment);
                } else {
                    $comment = str_replace($val[0], '<a href="javascript:void(0);" class="quotelink"><del>'.$val[0].'</del></a>', $comment);
                }
            }
    }
    return $comment;
}

function prepareComment($comment, $dbBoarDetails) {
    $comment = quote_unkfunc($comment, $dbBoarDetails);
    $comment = quote_link($comment, $dbBoarDetails);
    return $comment;
}

//sort threads by bump time (callback)
function sortByBump($thrXobj, $thrYobj) {
    $thrXBumpTime = $thrXobj->getRoot();
    $thrYBumpTime = $thrYobj->getRoot();
    if($thrXBumpTime == $thrYBumpTime) return 0; //they are the same
    if($thrXBumpTime > $thrYBumpTime) return -1; //X bump time is bigger than Y therefore it goes at the top of the list
    if($thrXBumpTime < $thrYBumpTime) return 1;
    
    return -1; // otherwise thread bump time is smaller so it goes down
}

function drawErrorPageAndExit_headless($mes1,$mes2=""){
    global $conf;
    echo '
    <hr>
    <center>
        <strong>'.$mes1.'</strong><br>
        <p>'.$mes2.'</p>
    </center>';
    drawFooter();
    exit;
}

function drawHeader() {
    global $conf;
    echo '
    <head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="cache-control" content="max-age=0" />
	<meta http-equiv="cache-control" content="no-cache" />
	<meta http-equiv="expires" content="0" />
	<meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />
	<meta http-equiv="pragma" content="no-cache" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="Berry" content="no" />
	<title>'.$conf['boardTitle'].'</title>
	<meta name="robots" content="follow,archive" />
	<link class="linkstyle" rel="stylesheet" type="text/css" href="static/css/heyuriclassic.css" title="Heyuri Classic" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="static/css/futaba.css" title="Futaba" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="static/css/oldheyuri.css" title="Sakomoto" />
	<link rel="shortcut icon" href="static/image/favicon.png" />
	<script type="text/javascript" src="js/koko.js"></script>
	<script type="text/javascript" src="js/style.js"></script>
	<script type="text/javascript" src="js/img.js"></script>
	<script src="https://unpkg.com/@ruffle-rs/ruffle"></script>
 	<style>
  		.boardFilterItem {
    			display: inline-block;
    		}
      
		.boardFilterItem label {
			cursor: pointer;
   		}
  	</style>

	<!--&TOPLINKS-->
	<div class="boardlist">
		<small class="toplinks">'.file_get_contents($conf['toplinks']).'</small>
		<div class="adminbar">[<a class="extr" href="'.$conf['home'].'">Home</a>]</div>
	</div>
	<!--/&TOPLINKS-->

	<!--&BODYHEAD-->

<body>
	<script id="wz_tooltip" type="text/javascript" src="js/wz_tooltip.js"></script>
	<a name="top"></a>
	<!--&TOPLINKS/-->
	<center id="header">
		<div class="logo">
			<br />
			<h1 class="mtitle">'.$conf['boardTitle'].'</h1>
			'.$conf['boardSubTitle'].'
			<hr class="top" width="90%" size="1" />
		</div>
	</center>';
}

function drawFooter() {
    echo '<center class="footer">
        - <a rel="nofollow noreferrer license" href="https://web.archive.org/web/20150701123900/http://php.s3.to/" target="_blank">GazouBBS</a> + <a rel="nofollow noreferrer license" href="http://www.2chan.net/" target="_blank">futaba</a> + <a rel="nofollow noreferrer license" href="https://pixmicat.github.io/" target="_blank">Pixmicat!</a> + <a rel="nofollow noreferrer license" href="https://github.com/Heyuri/kokonotsuba/" target="_blank">Kokonotsuba</a> -
        </center>';
}
//handle cookies for board filtering
function onBoardFilterSubmit() {
    global $conf;
    global $boardlist;
    global $filterCookie;
    
    if(sizeof($boardlist))
    $boardConfList = $conf['boards'];
    foreach($boardConfList as $board) {
        $boardToPush = array_search($board['dbname'].$board['tablename'], $_POST);
        if($boardToPush == null) {
            unset($boardlist[key($boardlist)]);
            array_push($filterCookie['blacklist'], $board['dbname'].$board['tablename']);
            setcookie("blacklist", json_encode($filterCookie['blacklist']),time()+365*24*3600);
        } else {
            array_push($boardlist, $board);
            $boardToDelete = array_search($board['dbname'].$board['tablename'], $filterCookie['blacklist']);
            unset($filterCookie[$boardToDelete]);
            setcookie("blacklist", json_encode($filterCookie['blacklist']),time()+365*24*3600);
        }
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    die;
}
function drawBoardFilterForm() {
    $boardCheckList = function() {
        global $conf;
        global $boardlist;
        global $filterCookie;
        $boardConfList = $conf['boards'];
        if(!isset($_COOKIE['blacklist'])) $blacklist = $filterCookie['blacklist']; 
        else $blacklist = json_decode($_COOKIE['blacklist']);
        
        foreach($boardConfList as $board) {
            $checked = '';
            if(!in_array($board['dbname'].$board['tablename'], $blacklist)) $checked = 'checked';
            echo '<span class="boardFilterItem"> <input type="checkbox" id="'.$board['boardname'].'" name="'.$board['boardname'].'" value="'.$board['dbname'].$board['tablename'].'" '.$checked.'><label for="'.$board['boardname'].'"> '.$board['boardname'].'</label></span>&nbsp;';
        }
    };
    echo '<center>
            <table>
                <tbody>
                    <td><details class="reply"> <summary style="cursor: pointer; padding: 3px;">Boards</summary>
                        <form action='.$_SERVER['PHP_SELF'].' method="POST">
                        '; 
                            $boardCheckList(); 
                        echo '
                          <input type="hidden" name="filter" value="1">
                          <input type="submit" value="Submit">
                        </form>
                        </details>
                    </td>
                </tbody>
            </table>
         </center>';
}

function drawPageingBar($page=1){
    global $conf;
    
    $threadCount = function() {  //get thread count across boards selected
        global $boardlist;
        global $filterCookie;
        if(!isset($_COOKIE['blacklist'])) $blacklist = $filterCookie['blacklist'];
        else $blacklist = json_decode($_COOKIE['blacklist']);
        
        $finalCount = 0;
  
        foreach($boardlist as $board) {
            if(in_array($board['dbname'].$board['tablename'], $blacklist)) continue;
            $finalCount += getTotalThreadCount($board);
        }
        return $finalCount;
    };
    $pages = ceil($threadCount() / $conf['threadsPerPage']) + 1;
   
    //'next by default'
    $rightbutton = '<td><form action="'.$_SERVER['PHP_SELF'].'" method="get"><div>  <input type="hidden" name="page" value="'.($page+1).'">  <input type="submit" value="Next"></div></form></td>'; //will go forward by one
    $leftbutton = '<td><form action="'.$_SERVER['PHP_SELF'].'" method="get"><div> <input type="hidden" name="page" value="'.($page-1).'"> <input type="submit" value="Back"></div></form></td>'; //will go back by one
    if($page == 1 || $page < 1) $leftbutton = '<td> [First] </td>';
    if($page >=  $pages || $page == $pages-1) $rightbutton = '<td> [Last] </td>';
    
    
    echo '<table id="pager" border="1" ><tbody><tr>'.$leftbutton.'<td>'; //start pager
    for($i = 1; $i < $pages; $i++) {
        if($i == $page){
            echo '[<b>'.$i.'</b>]';
        }else{
            echo ' [<a href="'.$_SERVER['PHP_SELF'].'?page='.$i.'">'.$i.'</a>] ';
        }
    }
    
    echo '</td><center>'.$rightbutton.'</center></tr></tbody></table>'; //end
}

function drawPost(array $postData, $board) {
    global $conf;
    if($postData == null) return -1;
    
    //prepare comment for drawing
    $postData['com'] = prepareComment($postData['com'], $board);
    
    echo '<table><tbody>'; //begin thread post preview
    //imageless post
    if($postData['ext'] == '') {
        echo '<tr>
        <td class="doubledash" valign="top">
        &gt;&gt;
        </td>
        <td class="post reply" id="p'.$board['tablename'].$postData['no'].'">
        <div class="postinfo"><label>
            <big class="title">
            <b>'.$postData['sub'].'</b></big> <span class="name"><b>'.$postData['name'].'</b></span> <span class="time">'.$postData['now'].'</span></label>
        <nobr><span class="postnum">
        <a href="'.$board['boardurl'].'koko.php?res='.$postData['resto'].'#p'.$postData['no'].'" class="no">No.</a><a href="'.$board['boardurl'].'koko.php?res='.$postData['resto'].'&amp;q='.$postData['no'].'#postform" class="qu" title="Quote">'.$postData['no'].'</a>
        </nobr></div>
        <blockquote class="comment">'.$postData['com'].'</blockquote>
        </td>
        </tr>';
    } else {
        $shortendImageName = $postData['fname'];
        $imgDisplayURL = '';
        $imgDisplayElement = '';
        
        if(file_exists($board['imageDir'].$postData['tim'].'s'.$conf['thumbExt'])) {
            $imgDisplayURL = $board['imageAddr'].$postData['tim'].'s'.$conf['thumbExt'];
            $imgDisplayElement = '<img src="'.$imgDisplayURL.'"  width="'.$postData['tw'].'" height="'.$postData['th'].'" class="postimg" title="Click to show full image" hspace="20" vspace="3" border="0" align="left">';
        } else if(file_exists($board['imageDir'].$postData['tim'].$postData['ext']) && $postData['ext'] != '.swf') {
            $imgDisplayURL = $board['imageAddr'].$postData['tim'].$postData['ext'];
            $imgDisplayElement = '<img src="'.$imgDisplayURL.'"  width="'.$postData['tw'].'" height="'.$postData['th'].'" class="postimg" title="Click to show full image" hspace="20" vspace="3" border="0" align="left">'; // stay the same but scaled
        } else if(!file_exists($board['imageDir'].$postData['tim'].$postData['ext'])) { 
            $imgDisplayURL = 'static/image/nothumb.gif'; //if neither thumb or full image is found, then it will use the error image
            $imgDisplayElement = '<img src="'.$imgDisplayURL.'"  width="200px" height="150px" class="postimg" title="File not found!" hspace="20" vspace="3" border="0" align="left">';
        } else if($postData['ext'] == '.swf') {
            $imgDisplayURL = 'static/image/swf_thumb.png';
            $imgDisplayElement = '<img src="'.$imgDisplayURL.'"  width="200px" height="200px" class="postimg" title="File not found!" hspace="20" vspace="3" border="0" align="left">';
        }
        if(strlen($postData['fname']) > 35) $shortendImageName = substr($postData['fname'], 0, 35).'(...)'.$postData['ext']; else $shortendImageName = $postData['fname'].$postData['ext'];
        
       
        
        $fnameJS = str_replace('&#039;', '\&#039;', $postData['fname']);
        $shortendImageNameJS = str_replace('&#039;', '\&#039;', $shortendImageName);
        echo '
            <tr>
					<td class="doubledash" valign="top">
						&gt;&gt;
					</td>
					<td class="post reply" id="p'.$board['tablename'].$postData['no'].'">
						<div class="postinfo"><label>
                            <big class="title"><b>'.$postData['sub'].'</b></big>
                                <span class="name"><b>'.$postData['name'].'</b></span> <span class="time">'.$postData['now'].'</span></label>
							<nobr><span class="postnum">
									<a href="'.$board['boardurl'].'koko.php?res='.$postData['resto'].'#p'.$postData['no'].'" class="no">No.</a><a href="'.$board['boardurl'].'koko.php?res='.$postData['resto'].'&amp;q='.$postData['no'].'#postform" class="qu" title="Quote">'.$postData['no'].'</a> </span></nobr>
						</div>
						<div class="filesize">File: <a href="'.$board['imageAddr'].$postData['tim'].$postData['ext'].'" target="_blank" rel="nofollow" onmouseover="this.textContent=\''.$fnameJS.$postData['ext'].'\';" onmouseout="this.textContent=\''.$shortendImageNameJS.'\'">'.$shortendImageName.'</a> <a href="'.$imgDisplayURL.'" download="'.$postData['fname'].$postData['ext'].'"><div class="download"></div></a> <small>('.$postData['imgsize'].', '.$postData['imgw'].'x'.$postData['imgh'].')</small> </div>
						<a href="'.$board['imageAddr'].$postData['tim'].$postData['ext'].'" target="_blank" rel="nofollow">'.$imgDisplayElement.'</a>
						    
                        <blockquote class="comment">'.$postData['com'].'</blockquote>
					</td>
				</tr>';
    }
    echo '</table></tbody>'; //end thread post preview
}

function drawThread(boardThread $thread) {
    global $conf;
    $threadOP = array_merge(...getPostData($thread->getThread(), $thread->getBoard()));   
    $board = $thread->getBoard();

    $shortendImageName = $threadOP['fname']; //used for onmouse event
    //for OP
    if(strlen($threadOP['fname']) > 35) $shortendImageName = substr($threadOP['fname'], 0, 35).'(...)'.$threadOP['ext'];
    $threadOP['com'] = prepareComment($threadOP['com'], $board);
    
    //begin thread div
    echo '<div class="thread" id="t'.$threadOP['no'].'">';
    //draw thread OP
    $imgDisplayURL = '';
    $imgDisplayElement = '';
    
    if(file_exists($board['imageDir'].$threadOP['tim'].'s'.$conf['thumbExt'])) {
        $imgDisplayURL = $board['imageAddr'].$threadOP['tim'].'s'.$conf['thumbExt'];
        $imgDisplayElement = '<img src="'.$imgDisplayURL.'"  width="'.$threadOP['tw'].'" height="'.$threadOP['th'].'" class="postimg" title="Click to show full image" hspace="20" vspace="3" border="0" align="left">';
    } else if(file_exists($board['imageDir'].$threadOP['tim'].$threadOP['ext'])  && $threadOP['ext'] != '.swf') {
        $imgDisplayURL = $board['imageAddr'].$threadOP['tim'].$threadOP['ext'];
        $imgDisplayElement = '<img src="'.$imgDisplayURL.'"  width="'.$threadOP['tw'].'" height="'.$threadOP['th'].'" class="postimg" title="Click to show full image" hspace="20" vspace="3" border="0" align="left">'; // stay the same but scaled
    } else if(!file_exists($board['imageDir'].$threadOP['tim'].$threadOP['ext'])) {
        $imgDisplayURL = 'static/image/nothumb.gif'; //if neither thumb or full image is found, then it will use the error image
        $imgDisplayElement = '<img src="'.$imgDisplayURL.'"  width="200px" height="150px" class="postimg" title="File not found!" hspace="20" vspace="3" border="0" align="left">';
    } else if($threadOP['ext'] == '.swf') {
        $imgDisplayURL = 'static/image/swf_thumb.png';
        $imgDisplayElement = '<img src="'.$imgDisplayURL.'"  width="200px" height="200px" class="postimg" title="File not found!" hspace="20" vspace="3" border="0" align="left">';
    }

    $fnameJS = str_replace('&#039;', '\&#039;', $threadOP['fname']);
    $shortendImageNameJS = str_replace('&#039;', '\&#039;', $shortendImageName);
    
    $fileDisplay = '<div class="filesize">File: <a href="'.$board['imageAddr'].$threadOP['tim'].$threadOP['ext'].'" target="_blank" rel="nofollow" onmouseover="this.textContent=\''.$fnameJS.$threadOP['ext'].'\';" onmouseout="this.textContent=\''.$shortendImageNameJS.$threadOP['ext'].'\'"> '.$shortendImageName.$threadOP['ext'].'</a> <a href="'.$board['imageAddr'].$threadOP['tim'].$threadOP['ext'].'" download="'.$threadOP['fname'].$threadOP['ext'].'"><div class="download"></div></a> <small>('.$threadOP['imgsize'].', '.$threadOP['imgw'].'x'.$threadOP['imgh'].')</small></div>
				<a href="'.$board['imageAddr'].$threadOP['tim'].$threadOP['ext'].'" target="_blank" rel="nofollow">'.$imgDisplayElement.'</a>' ;
    if($threadOP['ext'] == '') $fileDisplay = ''; // don't display file stuffz if there's no file (for textboard)
    if($threadOP['email'] == 'noko' || !isset($threadOP['email']) || $threadOP['email'] == '') {
        echo  '<b><a href="'.$board['boardurl'].'"> '.$thread->getBoard()['boardname'].' </a></b><br>
			<div class="post op" id="p'.$board['tablename'].$threadOP['no'].'">
				'.$fileDisplay.'
				<span class="postinfo"><label><big class="title"><b>'.$threadOP['sub'].'</b></big> <span class="name"><b>'.$threadOP['name'].'</b></span> <span class="time">'.$threadOP['now'].'</span></label>
					<nobr><span class="postnum">
							<a href="'.$board['boardurl'].'koko.php?res='.$threadOP['no'].'#p'.$threadOP['no'].'" class="no">No.</a><a href="'.$board['boardurl'].'koko.php?res='.$threadOP['no'].'&amp;q='.$threadOP['no'].'#postform" title="Quote">'.$threadOP['no'].'</a> </span> [<a href="'.$board['boardurl'].'koko.php?res='.$threadOP['no'].'">Reply</a>]</nobr>
					<small><i class="backlinks"></i></small>
				</span>
				<blockquote class="comment">'.$threadOP['com'].'</blockquote></div>';
    } else {
        echo '<b><a href="'.$board['boardurl'].'"> '.$board['boardname'].' </a></b><br>
			<div class="post op" id="p'.$threadOP['no'].'">
				'.$fileDisplay.'
				<span class="postinfo"><label><big class="title"><b>'.$threadOP['sub'].'</b></big> <span class="name"><b><a href="mailto:'.$threadOP['email'].'">'.$threadOP['name'].'</a></b></span> <span class="time">'.$threadOP['now'].'</span></label>
					<nobr><span class="postnum">
							<a href="'.$board['boardurl'].'koko.php?res='.$threadOP['no'].'#p'.$threadOP['no'].'" class="no">No.</a><a href="'.$board['boardurl'].'koko.php?res='.$threadOP['no'].'&amp;q='.$threadOP['no'].'#postform" title="Quote">'.$threadOP['no'].'</a> </span> [<a href="'.$board['boardurl'].'koko.php?res='.$threadOP['no'].'">Reply</a>]</nobr>
					<small><i class="backlinks"></i></small>
				</span>
				<blockquote class="comment">'.$threadOP['com'].'</blockquote></div>';
        
    }
    $countPostsInThread = postCount($threadOP['no'],$board) - 1;
    $postsOmitted = $countPostsInThread - 5; if($postsOmitted < 0) $postsOmitted = 0;
    
    if($countPostsInThread > 5) echo '<span class="omittedposts">'.$postsOmitted.' posts omitted. Click Reply to view.</span>';
    
    
    $postsInThread = fetchPostList($threadOP['no'], $board);
    $postsData = getPostData($postsInThread, $board);
    //draw last 5 posts
    if($countPostsInThread != 0) {
        for($i = $postsOmitted + 1; $i < $countPostsInThread + 1; $i++) {
            
            if($i < 0) break;
            else if ($i == 0) $i++;
            $postData = $postsData[$i];
            drawPost($postData, $board);
        }
    }
    
    
    echo '</div>'; // end of thread preview
    //do something
}

function drawOverBoardThreads($page = 1) {
        global $boardlist;
        global $conf;
        global $filterCookie;
        $threads = array(); //thread-s across boards sorted by bump time

        if(!isset($_COOKIE['blacklist'])) $blacklist = $filterCookie['blacklist']; 
        else $blacklist = json_decode($_COOKIE['blacklist']);
        $count = $conf['threadsPerPage'];
        
        $lineOffset = $count * $page;
        
        $currentLine = 0;       

        //get threads
        foreach($boardlist as $board) {
            if(in_array($board['dbname'].$board['tablename'], $blacklist)) continue;
            $preparedOPs = getThreadList($board); // thread -> boardThread
            array_push($threads, $preparedOPs);
        }
        //sort by bump time
        $threads = array_merge(...$threads);
        usort($threads, "sortByBump");

        $currentLine =  ($page - 1) * $conf['threadsPerPage'] ;
        
        //draw!
        while ($currentLine < $lineOffset) {
            if(!is_array($threads)) die('Threads not an array.');
            if($currentLine > sizeof($threads) - 1) break;
           
            drawThread($threads[$currentLine]);
            echo '<br clear="ALL"> <hr>';//clear for next line
          
            $currentLine = $currentLine + 1;
        }
        
        
}

if(isset($_POST['filter']))
    onBoardFilterSubmit();
    
if(isset($_GET['page'])){
    if($_GET['page'] == 0) //stops it from going out of range
        $_GET['page'] = 1;
    $page = $_GET['page'];
    drawHeader();
    drawBoardFilterForm();
    drawOverboardThreads($page);
    drawPageingBar($page);
    drawFooter();
    mysqli_close($con);      //close db connection
    die();
}

drawHeader();
drawBoardFilterForm();
drawOverboardThreads();
drawPageingBar(1);
drawFooter();

//close db connection
mysqli_close($con); 


?>
