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
		return '0.2';
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
		$this->createOption('s_lists','List.','text','<ul class="nobullets">');
		$this->createOption('e_lists','List(close).','text','</ul>');
		$this->createOption('s_items','List Item.','text','<li>');
		$this->createOption('e_items','List Item(close).','text','</li>');
	}

	function doSkinVar($skinType, $itemcnt = '5', $commentcnt = '4') {
		
		global $member, $manager, $CONF;
		$b =& $manager->getBlog($CONF['DefaultBlog']);

		if ($CONF['URLMode'] == 'pathinfo') {
			 $blogurl = '' ;
		}else{
			 $blogurl = $b->getURL() ;
		}
		//format itemcnt
		if ($itemcnt == "")
			$itemcnt = 5;
		
		//get 5 items which have comments
		$query = 'SELECT distinct citem FROM '.sql_table('comment').' ORDER BY cnumber DESC LIMIT 0,'.intval($itemcnt);
		$res = mysql_query($query);
		$i = 0;
		while($row = mysql_fetch_object($res)){
			
			//set 5 item's title
			$query_item = 'SELECT inumber,ititle FROM '.sql_table('item').' WHERE inumber='.intval($row->citem);
			$res_item = mysql_query($query_item);
			while($row_item = mysql_fetch_object($res_item)){
				//get title
				$title[$i] = strip_tags($row_item->ititle);
				$number[$i] = intval($row_item->inumber);
			}

			$i++;
		}

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

	}

}
?>