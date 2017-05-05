<?php
// plugin needs to work on Nucleus versions <=2.0 as well
if (!function_exists('sql_table')){
	function sql_table($name) {
		return 'nucleus_' . $name;
	}
}

class NP_CommentTree extends NucleusPlugin {
	function getName() {return 'Comment Tree';}
	function getAuthor(){return 'mas + nakahara21';}
	function getURL(){return 'http://neconnect.net/';}
	function getVersion() {return '0.45';}
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
		$this->createOption('s_lists','List.','text','<ul class="nobullets">');
		$this->createOption('e_lists','List(close).','text','</ul>');
		$this->createOption('s_items','List Item.','text','<li>');
		$this->createOption('e_items','List Item(close).','text','</li>');
	}

	function doSkinVar($skinType, $itemcnt = '5', $commentcnt = '4',$filter = '') {
		global $member, $manager, $CONF, $blog;
		if ($blog)
			$b =& $blog;
		else
			$b =& $manager->getBlog($CONF['DefaultBlog']);
		$blogid = $b->getID();

		if ($CONF['URLMode'] == 'pathinfo') {
			 $blogurl = '' ;
		}else{
			 $blogurl = $b->getURL() ;
		}
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
			$filter = " cblog <>".str_replace("/"," or cblog<>",$filter);
		}
		
//_---------------------
		//get itemid which have comments
		$query = 'SELECT distinct citem, UNIX_TIMESTAMP(ctime) as ctimest FROM '.sql_table('comment');
		if($filter != ''){
			$query .= " WHERE ".$filter;
		}
		$query .= ' GROUP BY citem';
		$query .= ' ORDER BY cnumber DESC LIMIT 0,'.intval($itemcnt);

		$res = mysql_query($query);
		$i = 0;
		while($row = mysql_fetch_object($res)){
			$latest_itemid[$row->ctimest]= $row->citem;
			$i++;
		}

//_---------------------
		//get itemid which have trackbacks
		if ($manager->pluginInstalled('NP_TrackBack') && $this->getOption(tbflag)=='yes'){
			$query = "SELECT distinct t.tb_id, UNIX_TIMESTAMP(t.timestamp) as ttimest FROM ".sql_table('plugin_tb')." t, ".sql_table('item')." i";
			$query .= " WHERE t.tb_id=i.inumber";
			if($filter != ''){
				$tfilter = str_replace("cblog", "i.iblog", $filter);
				$query .= " and ".$tfilter;
			}
			$query .= ' GROUP BY t.tb_id';
			$query .= " ORDER by t.timestamp DESC LIMIT 0,".intval($itemcnt);
			$res = mysql_query($query);
			while($row = mysql_fetch_object($res)){
				$latest_itemid[$row->ttimest]= $row->tb_id;
			}
		}
//_---------------------
		//sort itemid which have comment or trackbacks
		krsort($latest_itemid);
		$latest_itemid = array_unique($latest_itemid);
		$latest_itemid = array_values($latest_itemid);
		$show_itemcnt = min(intval($itemcnt),count($latest_itemid));
	
//_---------------------
		echo $this->getOption(s_lists)."\n";

		for($i=0;$i<$show_itemcnt;$i++){
			$item =& $manager->getItem($latest_itemid[$i],0,0);
			$itemlink = createItemLink($item['itemid'], '');
			$itemtitle = $item['title'];
			$itemtitle = shorten($itemtitle,20,'..');
			echo $this->getOption(s_items)."<a href=\"".$blogurl.$itemlink."\">".$itemtitle."</a><br />\n";
			
			//get comments of this item
			$query = 'SELECT cnumber, cuser, citem, cmember, ctime, UNIX_TIMESTAMP(ctime) as ctimest FROM '.sql_table('comment').' WHERE citem='.$item['itemid'].' ORDER BY cnumber DESC LIMIT 0,'.$commentcnt;
			$res = mysql_query($query);
			while($row = mysql_fetch_object($res)){
				$cid = $row->cnumber;
				$ct = $row->ctimest;
				$ctst = date("m/d", $ct);
				if (!$row->cmember) $myname = $row->cuser;
				else {
					$mem = new MEMBER;
					$mem->readFromID(intval($row->cmember));
					$myname = $mem->getDisplayName();
				}
				$ress[$row->ctimest] = "└ $myname <a href=\"".$blogurl.$itemlink."#c".$cid."\">".$ctst."</a><br />\n";
			}

			//get trackbacks of this item
			if ($manager->pluginInstalled('NP_TrackBack') && $this->getOption(tbflag)=='yes'){
				$query = "SELECT title, excerpt, tb_id, blog_name, timestamp ,UNIX_TIMESTAMP(timestamp) as ttimest FROM ".sql_table('plugin_tb');
				$query .= " WHERE tb_id=".$item['itemid'];
				$query .= " ORDER by timestamp DESC LIMIT 0,".$commentcnt;

				$tbs = mysql_query($query);
				while($row = mysql_fetch_object($tbs)) {
					$ct = $row->ttimest;
					$ctst = date("m/d", $ct);
					$blogname = shorten($row->blog_name,10,'..');
					$ress[$row->ttimest] = "└ [$blogname] <a href=\"".$blogurl.$itemlink."#trackback\">".$ctst."</a><br />\n";
				}
			}

			//sort comment and trackbacks of this item
			krsort($ress);
			$ress = array_values($ress);
			$show_rescnt = min(intval($commentcnt),count($ress));

			// display comments and trackbacks
			for ($j=0;$j<$show_rescnt;$j++){
				echo $ress[$j];
			}

			echo $this->getOption(e_items)."\n";
			unset($ress);
		}
		echo $this->getOption(e_lists);
	}
}
?>