<?php
// plugin needs to work on Nucleus versions <=2.0 as well
if (!function_exists('sql_table')){
	function sql_table($name) {
		return 'nucleus_' . $name;
	}
}

class NP_CommentTree extends NucleusPlugin {

	// name of plugin
	function getName() {
		return 'Comment Tree';
	}

	// author of plugin
	function getAuthor()  {
		return 'mas';
	}

	// an URL to the plugin website
	// can also be of the form mailto:foo@bar.com
	function getURL()
	{
		return 'http://neconnect.net/';
	}

	// version of the plugin
	function getVersion() {
		return '0.4';
	}

	// a description to be shown on the installed plugins listing
	function getDescription() {
		return 'latest comments - tree style';
	}

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
		if ($itemcnt == "")
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
		//get 5 items which have comments
		$query = 'SELECT distinct citem, UNIX_TIMESTAMP(ctime) as ctimest FROM '.sql_table('comment');
		if($filter != ''){
			$query .= " WHERE ".$filter;
		}
		$query .= ' GROUP BY citem';
		$query .= ' ORDER BY cnumber DESC LIMIT 0,'.intval($itemcnt);

		$res = mysql_query($query);
		$i = 0;
		while($row = mysql_fetch_object($res)){
			//set 5 item's title
			$item =& $manager->getItem(intval($row->citem));
			$title[$i] = strip_tags($item['title']);
			$number[$i] = $item['itemid'];
			$latest_itemid[$row->ctimest]= $row->citem;
			$i++;
		}

//_---------------------
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
		$i = 0;
		while($row = mysql_fetch_object($res)){
			//set 5 item's title
			$latest_itemid[$row->ttimest]= $row->tb_id;
			$i++;
		}
	}
//_---------------------
	krsort($latest_itemid);
	$latest_itemid = array_unique($latest_itemid);
	$latest_itemid = array_values($latest_itemid);
	$show_itemcnt = min(intval($itemcnt),count($latest_itemid));
	
//	print_r($latest_itemid);

/*
*/
		echo $this->getOption(s_lists)."\n";

		for($i=0;$i<$show_itemcnt;$i++){
			$item =& $manager->getItem($latest_itemid[$i]);
			$itemlink = createItemLink($item['itemid'], '');
			$itemtitle = $item['title'];
			$itemtitle = shorten($itemtitle,20,'..');
			echo $this->getOption(s_items)."<a href=\"".$blogurl.$itemlink."\">".$itemtitle."</a><br />\n";
			
			
			//get comments
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

			//get trackbacks
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
	krsort($ress);
	$ress = array_values($ress);
	$show_rescnt = min(intval($commentcnt),count($ress));

			// display comments and trackbacks
			for ($j=0;$j<$show_rescnt;$j++){
				echo $ress[$j];
			}

			echo $this->getOption(e_items)."\n";

			unset( $ress );
		}
		echo $this->getOption(e_lists);

//_---------------------
/*
		echo $this->getOption(s_lists)."\n";

		foreach($title as $key => $val){
			//show title
			$itemlink = createItemLink($number[$key], '');
			echo $this->getOption(s_items)."<a href=\"".$blogurl.$itemlink."\">".$val."</a><br />\n";
			
			//get comments
			$query = 'SELECT cnumber, cuser, citem, cmember, ctime, UNIX_TIMESTAMP(ctime) as ctimest FROM '.sql_table('comment').' WHERE citem='.$number[$key].' ORDER BY cnumber DESC LIMIT 0,'.$commentcnt;
			$res = mysql_query($query);
			$k = 0;
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

				$item[$k] = "└ $myname <a href=\"".$blogurl.$itemlink."#c".$cid."\">".$ctst."</a><br />\n";
				$k++;
			}
			
			// display $item
			for ( $j = sizeof($item) - 1; $j >= 0; $j--){
				echo $item[$j];
			}
			
			// delete $item
			unset( $item );

			
			echo $this->getOption(e_items)."\n";
		}

		echo $this->getOption(e_lists);
*/

	}

}
?>