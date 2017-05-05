<?php
// plugin needs to work on Nucleus versions <=2.0 as well
if (!function_exists('sql_table')){
	function sql_table($name) {
		return 'nucleus_' . $name;
	}
}

class NP_CommentTree extends NucleusPlugin {
	function getName() {return 'Comment Tree';}
	function getAuthor(){return 'mas + nakahara21 + taka + yu';}
	function getURL(){return 'http://japan.nucleuscms.org/bb/viewtopic.php?t=127';}
	function getVersion() {return '2.1';}
	function supportsFeature($what) { return (int)($what=='SqlTablePrefix'); }
	function getDescription() {
		// include language file for this plugin 
		$language = str_replace( array('/','\\'), '', getLanguageName()); 
		if (file_exists($this->getDirectory() . $language . '.php')) {
			include_once($this->getDirectory() . $language . '.php'); 
		} else {
			include_once($this->getDirectory() . 'english.php');
		}
		$description = _NP_COMMENTTREE_DESC;
		return $description;
	}
	
	function install () {
		// include language file for this plugin 
		$language = str_replace( array('/','\\'), '', getLanguageName()); 
		if (file_exists($this->getDirectory() . $language . '.php')) {
			include_once($this->getDirectory() . $language . '.php'); 
		} else {
			include_once($this->getDirectory() . 'english.php');
		}
		
		$this->createOption('timelocale', _NP_COMMENTTREE_TZLOC, 'text', 'ja_JP');
		$this->createOption('cmdateformat', _NP_COMMENTTREE_CDFMT, 'text',     '%m/%d');
		$this->createOption('tbdateformat', _NP_COMMENTTREE_TEFMT, 'text',     '%m/%d');
		$this->createOption('listhead', _NP_COMMENTTREE_LHEAD, 'textarea',
		 '<ul class="nobullets">');
		$this->createOption('listfoot', _NP_COMMENTTREE_LFOOT, 'textarea',
		 '</ul>');
		$this->createOption('itemtemplate', _NP_COMMENTTREE_IBODY, 'textarea',
		 '<li class="item"><a href="<%itemlink%>"><%title%></a>');
		$this->createOption('elementhead', _NP_COMMENTTREE_EHEAD, 'textarea',
		 '<ul class="nobullets">');
		$this->createOption('elementfoot',_NP_COMMENTTREE_EFOOT , 'textarea',
		 '</ul></li>');
		$this->createOption('cmttemplate', _NP_COMMENTTREE_CBODY, 'textarea',
		'<li class="comment"><a href="<%itemlink%>#c<%commentid%>"><%commentdate%> <%commentator%> <%commentbody%></a></li>');
		$this->createOption('tbktemplate', _NP_COMMENTTREE_TBODY, 'textarea',
		'<li class="trackback"><a href="<%itemlink%>#trackback"><%tbdate%> <%blogname%> ping: "<%entrytitle%>"</a></li>');
		$this->createOption('elementmorelink', _NP_COMMENTTREE_EMORE, 'textarea',     '<li class="more">and more...</li>');
		$this->createOption('titleLength', _NP_COMMENTTREE_ITLEN,'text','28');
		$this->createOption('nameLength', _NP_COMMENTTREE_NMLEN,'text','14');
		$this->createOption('flg_quote', _NP_COMMENTTREE_FQUOT,'select','title','Item title - name length|diff|Same as title length|title');
	}

	function uninstall(){
//		$this->deleteOption('timelocale');
//		$this->deleteOption('cmdateformat');
//		$this->deleteOption('tbdateformat');
//		$this->deleteOption('listhead');
//		$this->deleteOption('listfoot');
//		$this->deleteOption('itemtemplate');
//		$this->deleteOption('elementhead');
//		$this->deleteOption('elementfoot');
//		$this->deleteOption('cmttemplate');
//		$this->deleteOption('tbktemplate');
//		$this->deleteOption('elementmorelink');
//		$this->deleteOption('titleLength');
//		$this->deleteOption('nameLength');
//		$this->deleteOption('flg_quote');
	}

	function doSkinVar($skinType, $itemcnt = '5', $commentcnt = '4', $TBorCm = 'all', $filter = '') {
		global $member, $manager, $CONF, $blog;

		$itemcnt = (is_numeric($itemcnt)) ? $itemcnt : 5;
		$commentcnt = (is_numeric($commentcnt)) ? $commentcnt : 4;
		
		$toadd = '..';
		
		// for under versin 1.0
		if($TBorCm == 'comment') {
			$TBorCm = 'c';
		} elseif($TBorCm == 'trackback') {
			$TBorCm = 't';
		} elseif($TBorCm == 'both') {
			$TBorCm = 'all';
		}
		
		$b =& $manager->getBlog($CONF['DefaultBlog']);
		$this->defaultblogurl = $b->getURL() ;
		if(!$this->defaultblogurl)
			$this->defaultblogurl = $CONF['IndexURL'] ;
		if ($blog)
			$b =& $blog;
		$blogid = $b->getID();
		
		$filter = trim($filter);
		if($filter == 'current'){
			$filter = 'cblog='.$blogid;
		}elseif(strstr($filter,"=")){
			$filter = str_replace("=","",$filter);
			$filter = " cblog IN(".str_replace("/",",",$filter).")";
		}elseif(strstr($filter,"<>")){
			$filter = str_replace("<>","",$filter);
			$filter = " cblog <>".str_replace("/"," and cblog<>",$filter);
		}

		$timelocale = $this->getOption('timelocale') ? $this->getOption('timelocale') : "c";
		setlocale(LC_TIME, $timelocale);
		
		$titleLength = $this->getOption('titleLength') ? $this->getOption('titleLength') : "28";
		$nameLength  = $this->getOption('nameLength') ? $this->getOption('nameLength') : "14";
		$flg_quote = $this->getOption('flg_quote');

		$cmdateformat = $this->getOption('cmdateformat') ? $this->getOption('cmdateformat') : "%m/%d";
		$tbdateformat = $this->getOption('tbdateformat') ? $this->getOption('tbdateformat') : "%m/%d";
		
		$latest_itemid= array();
		
		//get itemid which have comments
		if ($TBorCm != 't') {
		$query = 'SELECT citem, MAX(UNIX_TIMESTAMP(ctime)) as ctimest FROM '.sql_table('comment');
		if($filter != ''){
			$query .= " WHERE ".$filter;
		}
		$query .= ' GROUP BY citem';
		$query .= ' ORDER BY ctimest DESC LIMIT 0,'.intval($itemcnt);

		$res = sql_query($query);
		while($row = mysql_fetch_object($res)){
			$latest_itemid[$row->ctimest]= $row->citem;
		}
		}
		
		//get itemid which have trackbacks
		if ($manager->pluginInstalled('NP_TrackBack') && $TBorCm != 'c') {
			$query = "SELECT t.tb_id, MAX(UNIX_TIMESTAMP(t.timestamp)) as ttimest FROM ".sql_table('plugin_tb')." t, ".sql_table('item')." i";
			$query .= " WHERE t.tb_id=i.inumber";
			if ($this->checkTBVersion()) {
				$query .= " and t.block=0";
			}
			if($filter != ''){
				$tfilter = str_replace("cblog", "i.iblog", $filter);
				$query .= " and ".$tfilter;
			}
			$query .= ' GROUP BY t.tb_id';
			$query .= " ORDER by ttimest DESC LIMIT 0,".intval($itemcnt);
			$res = sql_query($query);
			while($row = mysql_fetch_object($res)){
				if($already = array_search($row->tb_id, $latest_itemid)){
					if($row->ttimest > $already){
						unset($latest_itemid[$already]);
						$latest_itemid[$row->ttimest]= $row->tb_id;
					}
				}else{
					$latest_itemid[$row->ttimest]= $row->tb_id;
				}
			}
		}
		
		//sort itemid which have comment or trackbacks
		krsort($latest_itemid);
		$latest_itemid = array_values($latest_itemid);
		$show_itemcnt = min(intval($itemcnt),count($latest_itemid));
		
		echo $this->getOption(listhead);
		
		for($i=0;$i<$show_itemcnt;$i++){
			$item =& $manager->getItem($latest_itemid[$i],0,0);
//			$itemlink = $this->createGlobalItemLink($item['itemid'], '');
			$itemlink = createItemLink($item['itemid'], '');
			$itemtitle = strip_tags(trim($item['title']));
			$itemtitle = shorten($itemtitle,$titleLength,$toadd);

			$content['itemlink'] = $itemlink;
			$content['title'] = $itemtitle;
			echo TEMPLATE::fill($this->getOption('itemtemplate'), $content);
			
			//get comments of this item
			if ($TBorCm != 't') {

			$query = 'SELECT'
				   . ' cnumber as commentid,'
				   . ' cuser   as commentator,'
				   . ' cbody   as commentbody,'
				   . ' citem   as itemid,'
				   . ' cmember as memberid,'
				   . ' UNIX_TIMESTAMP(ctime)  as ctimest'
				   . ' FROM ' . sql_table('comment');
			$query .= ' WHERE citem='.$item['itemid'];
			$query .= ' ORDER BY cnumber DESC LIMIT 0,'.($commentcnt + 1);

			$res = sql_query($query);
			while($row = mysql_fetch_object($res)){
				$content = (array)$row;
				
				$content['commentdate'] = strftime($cmdateformat, $content['ctimest']);

				if (!empty($row->memberid)) {
						$mem = new MEMBER;
						$mem->readFromID(intval($content['memberid']));
						$content['commentator'] = shorten($mem->getRealName(),$nameLength,$toadd);
				} else {
					$content['commentator'] = shorten($content['commentator'],$nameLength,$toadd);
				}
				
				if ($flg_quote == 'diff') {
					$bodyLength = $titleLength - mb_strwidth($content['commentator'].$content['commentdate'].strip_tags($this->getOption('cmttemplate')));
				} else {
					$bodyLength = $titleLength;
				}
				$commentbody = strip_tags($content['commentbody']);
				$commentbody = htmlspecialchars($commentbody, ENT_QUOTES);
				$commentbody = shorten($commentbody, $bodyLength, $toadd);
				$content['commentbody'] = $commentbody;
				$content['itemlink'] = $itemlink;
				$ress[$row->ctimest] = TEMPLATE::fill($this->getOption('cmttemplate'), $content);

			}
			}
			
			//get trackbacks of this item
			if ($manager->pluginInstalled('NP_TrackBack') && $TBorCm != 'c') {

			$query = 'SELECT'
				   . ' id as tbid,'
				   . ' title as entrytitle,'
				   . ' blog_name as blogname,'
				   . ' UNIX_TIMESTAMP(timestamp)  as ttimest'
				   . ' FROM ' . sql_table('plugin_tb');
				$query .= " WHERE tb_id=".$item['itemid'];
				if ($this->checkTBVersion()) {
					$query .= " and block=0";
				}
				$query .= " ORDER by timestamp DESC LIMIT 0,".($commentcnt + 1);

				$tbs = sql_query($query);
				while($row = mysql_fetch_object($tbs)) {
					$content = (array)$row;
					$content['tbdate']     = strftime($tbdateformat, $content['ttimest']);
					$content['blogname'] = shorten($content['blogname'],$nameLength,$toadd);
					if ($flg_quote == 'diff') {
						$nLength = mb_strwidth($content['blogname'].$content['tbdate'].strip_tags($this->getOption('tbktemplate')));
						$bodyLength = ($titleLength >= $nLength) ? $titleLength - $nLength : 0;
					} else {
						$bodyLength = $titleLength;
					}

					$entrytitle = strip_tags($content['entrytitle']);
					$entrytitle = htmlspecialchars($entrytitle, ENT_QUOTES);
					$entrytitle = ($bodyLength > 0) ? shorten($entrytitle, $bodyLength, $toadd) : "";
					$content['entrytitle'] = $entrytitle;
					$content['itemlink'] = $itemlink;
					$ress[$row->ttimest] = TEMPLATE::fill($this->getOption('tbktemplate'), $content);
				}
			}
			
			//sort comment and trackbacks of this item
			krsort($ress);
			$ress = array_values($ress);
			$show_rescnt = min(intval($commentcnt),count($ress));
			
			echo $this->getOption(elementhead);
			// display comments and trackbacks
			for ($j=0;$j<$show_rescnt;$j++){
				echo $ress[$j]."\n";
			}
			if(count($ress) > $show_rescnt){
				echo $this->getOption(elementmorelink);
			}
			
			echo $this->getOption(elementfoot);
			unset($ress);
		}
		echo $this->getOption(listfoot);
	}
	
	
	
	function checkTBVersion(){
		$res = sql_query('SHOW FIELDS FROM ' . sql_table('plugin_tb') );
		$fieldnames = array();
		while ($co = mysql_fetch_assoc($res)) {
			$fieldnames[] = $co['Field'];
		}
		if (in_array('block', $fieldnames)) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function createGlobalItemLink($itemid, $extra = '') {
		global $CONF, $manager;
		
		if ($CONF['URLMode'] == 'pathinfo'){
			$link = $CONF['ItemURL'] . '/item/' . $itemid;
		}else{
			$blogid = getBlogIDFromItemID($itemid);
			$b_tmp =& $manager->getBlog($blogid);
			$blogurl = $b_tmp->getURL() ;
			if(!$blogurl){
				$blogurl = $this->defaultblogurl;
			}
			if(substr($blogurl, -4) != '.php'){
				if(substr($blogurl, -1) != '/')
					$blogurl .= '/';
				$blogurl .= 'index.php';
			}
			$link = $blogurl . '?itemid=' . $itemid;
		}
		return addLinkParams($link, $extra);
	}
	
}
?>