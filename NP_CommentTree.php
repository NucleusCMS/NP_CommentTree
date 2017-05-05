<?php
/*
	NP_CommentTree Ver0.8

	USAGE
	-----
	<%CommentTree()%>
	<%CommentTree(5,4)%> //item amount, comment amount
	<%CommentTree(5,4,comment)%> //comments only
	<%CommentTree(5,4,trackback)%> //trackbacks only

*/

// plugin needs to work on Nucleus versions <=2.0 as well
if (!function_exists('sql_table')){
	function sql_table($name) {
		return 'nucleus_' . $name;
	}
}

class NP_CommentTree extends NucleusPlugin {
	function getName() {return 'Comment Tree';}
	function getAuthor(){return 'mas + nakahara21 + taka + yu';}
	function getURL(){return 'http://felis.jp/bb/viewtopic.php?t=127';}
	function getVersion() {return '0.82';}
	function getDescription() {return 'latest comments (and trackbacks) - tree style';}
	function supportsFeature($what) {
		switch($what){
			case 'SqlTablePrefix':
				return 1;
			default:
				return 0;
		}
	}
	
	function install () {
		$this->createOption('tbflag','Show TrackBacks?','yesno','yes');
		$this->createOption('s_lists','List.','text','<ul class="commenttree">');
		$this->createOption('e_lists','List (close).','text','</ul>');
		$this->createOption('s_items','List Item.','text','<li class="%kind%">');
		$this->createOption('e_items','List Item (close).','text','</li>');
		$this->createOption('item_format','Item Format.','text','%date% %name% %comment%');
		$this->createOption('date_format','Date Format.','text','m/d');
		$this->createOption('comment_format','Comment Format.','text','"%content%"');
		$this->createOption('title_len','Item title Length.','text','28');
		$this->createOption('name_len','Name Length.','text','14');
		$this->createOption('flg_quote','Comment Length.','select','diff','Item title - name length|diff|Same as title length|title');
	}

	function doSkinVar($skinType, $itemcnt = '5', $commentcnt = '4', $mode = 'both', $filter = '') {
		global $member, $manager, $CONF, $blog;
		if($mode == '')$mode = 'both';
		$b =& $manager->getBlog($CONF['DefaultBlog']);
		$this->defaultblogurl = $b->getURL() ;
		if(!$this->defaultblogurl)
			$this->defaultblogurl = $CONF['IndexURL'] ;
		if ($blog)
			$b =& $blog;
		$blogid = $b->getID();
		
		//format itemcnt
		if ($itemcnt == '')
			$itemcnt = 5;
		
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
		
		$item_format    = $this->getOption('item_format');
		$date_format    = $this->getOption('date_format');
		$comment_format = $this->getOption('comment_format');
		$title_len = $this->getOption('title_len');
		$name_len  = $this->getOption('name_len');
		$flg_quote = $this->getOption('flg_quote');
		
		$latest_itemid= array();
		
		//get itemid which have comments
		if ($mode == 'both' or $mode == 'comment') {
		$query = 'SELECT citem, MAX(UNIX_TIMESTAMP(ctime)) as ctimest FROM '.sql_table('comment');
		if($filter != ''){
			$query .= " WHERE ".$filter;
		}
		$query .= ' GROUP BY citem';
		$query .= ' ORDER BY ctimest DESC LIMIT 0,'.intval($itemcnt);

		$res = mysql_query($query);
		while($row = mysql_fetch_object($res)){
			$latest_itemid[$row->ctimest]= $row->citem;
		}
		}
		
		//get itemid which have trackbacks
		if ($mode == 'both' or $mode == 'trackback') {
		if ($manager->pluginInstalled('NP_TrackBack') && $this->getOption(tbflag)=='yes'){
			$query = "SELECT t.tb_id, MAX(UNIX_TIMESTAMP(t.timestamp)) as ttimest FROM ".sql_table('plugin_tb')." t, ".sql_table('item')." i";
			$query .= " WHERE t.tb_id=i.inumber";
			if($filter != ''){
				$tfilter = str_replace("cblog", "i.iblog", $filter);
				$query .= " and ".$tfilter;
			}
			$query .= ' GROUP BY t.tb_id';
			$query .= " ORDER by ttimest DESC LIMIT 0,".intval($itemcnt);
			$res = mysql_query($query);
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
		}
		
		//sort itemid which have comment or trackbacks
		krsort($latest_itemid);
		$latest_itemid = array_values($latest_itemid);
		$show_itemcnt = min(intval($itemcnt),count($latest_itemid));
		
		echo $this->getOption(s_lists)."\n";
		
		for($i=0;$i<$show_itemcnt;$i++){
			$item =& $manager->getItem($latest_itemid[$i],0,0);
			$itemlink = $this->createGlobalItemLink($item['itemid'], '');
			$itemtitle = trim($item['title']);
			$itemtitle = shorten($itemtitle,$title_len,'..');
			
			$s_item = str_replace('%kind%', 'item', $this->getOption(s_items));
			echo $s_item."<a href=\"{$itemlink}\">$itemtitle</a>\n";
			echo $this->getOption(s_lists)."\n";
			
			//get comments of this item
			if ($mode == 'both' or $mode == 'comment') {
			$query = 'SELECT cnumber, cbody, cuser, cmember, ctime, UNIX_TIMESTAMP(ctime) as ctimest FROM '.sql_table('comment').' WHERE citem='.$item['itemid'].' ORDER BY cnumber DESC LIMIT 0,'.($commentcnt + 1);
			$res = mysql_query($query);
			while($row = mysql_fetch_object($res)){
				$cid = $row->cnumber;
				$ctst = date($date_format, $row->ctimest);
				if (!$row->cmember) $commentname = shorten($row->cuser,$name_len,'..');
				else {
					$mem = new MEMBER;
					$mem->readFromID(intval($row->cmember));
					$commentname = shorten($mem->getDisplayName(),$name_len,'..');
				}
				if ($flg_quote == 'diff') $comment_len = $title_len - mb_strwidth($commentname.$ctst) -4;
				else $comment_len = $title_len;
				if ($comment_len >6)
					$comment_str = str_replace('%content%', shorten(strip_tags(trim($row->cbody)), $comment_len, '..'), $comment_format);
				else $comment_str = '';
				$rep_from = array('%date%','%name%','%comment%');
				$rep_to   = array($ctst, $commentname, $comment_str);
				$item_element = str_replace($rep_from, $rep_to, $item_format);
				
				$s_items = str_replace('%kind%', 'comment', $this->getOption(s_items));
				$ress[$row->ctimest] = $s_items. "<a href=\"{$itemlink}#c{$cid}\">$item_element</a>" .$this->getOption(e_items);
			}
			}
			
			//get trackbacks of this item
			if ($mode == 'both' or $mode == 'trackback') {
			if ($manager->pluginInstalled('NP_TrackBack') && $this->getOption(tbflag)=='yes'){
				$query = "SELECT title, blog_name, UNIX_TIMESTAMP(timestamp) as ttimest FROM ".sql_table('plugin_tb');
				$query .= " WHERE tb_id=".$item['itemid'];
				$query .= " ORDER by timestamp DESC LIMIT 0,".($commentcnt + 1);

				$tbs = mysql_query($query);
				while($row = mysql_fetch_object($tbs)) {
					$ctst     = date($date_format, $row->ttimest);
					$blogname = shorten($row->blog_name,$name_len,'..');
					if ($flg_quote == 'diff') $comment_len = $title_len - mb_strwidth($blogname.$ctst) -4;
					else $comment_len = $title_len;
					if ($comment_len >6)
						$comment_str = str_replace('%content%', shorten(strip_tags(trim($row->title)), $comment_len, '..'), $comment_format);
 					else $comment_str = '';
					$rep_from = array('%date%','%name%','%comment%');
					$rep_to   = array($ctst, $blogname, $comment_str);
					$item_element = str_replace($rep_from, $rep_to, $item_format);
					
					$s_items = str_replace('%kind%', 'trackback', $this->getOption(s_items));
					$ress[$row->ttimest] = $s_items. "<a href=\"{$itemlink}#trackback\">$item_element</a>" .$this->getOption(e_items);
				}
			}
			}
			
			//sort comment and trackbacks of this item
			krsort($ress);
			$ress = array_values($ress);
			$show_rescnt = min(intval($commentcnt),count($ress));
			
			// display comments and trackbacks
			for ($j=0;$j<$show_rescnt;$j++){
				echo $ress[$j]."\n";
			}
			if(count($ress) > $show_rescnt){
				$s_items = str_replace('%kind%', 'more', $this->getOption(s_items));
				echo $s_items. "and more..." .$this->getOption(e_items)."\n";
			}
			
			echo $this->getOption(e_lists)."\n";
			echo $this->getOption(e_items)."\n";
			unset($ress);
		}
		echo $this->getOption(e_lists);
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