<?php
require_once 'sessions.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';


/**
 * Define Timezone
 */
if (isset($_SESSION['settings']['timezone'])) {
    date_default_timezone_set($_SESSION['settings']['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

// Connect to mysql server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
$tree->rebuild();
$folders = $tree->getDescendants();

if ($_SESSION['user_admin'] == 1 && (isset($k['admin_full_right'])
    && $k['admin_full_right'] == true) || !isset($k['admin_full_right'])) {
    $_SESSION['groupes_visibles'] = $_SESSION['personal_visible_groups'];
    $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
}

$ret_json = '';
if (isset($_COOKIE['jstree_select']) && !empty($_COOKIE['jstree_select'])) {
    $firstGroup = str_replace("#li_", "", $_COOKIE['jstree_select']);
} else {
    $firstGroup = "";
}
if (isset($_SESSION['list_folders_limited']) && count($_SESSION['list_folders_limited']) > 0) {
    $listFoldersLimitedKeys = @array_keys($_SESSION['list_folders_limited']);
} else {
    $listFoldersLimitedKeys = array();
}
// list of items accessible but not in an allowed folder
if (isset($_SESSION['list_restricted_folders_for_items'])
    && count($_SESSION['list_restricted_folders_for_items']) > 0) {
    $listRestrictedFoldersForItemsKeys = @array_keys($_SESSION['list_restricted_folders_for_items']);
} else {
    $listRestrictedFoldersForItemsKeys = array();
}
$parent = "#";


$completTree = $tree->getTreeWithChildren();
foreach ($completTree[0]->children as $child) {
	$data = recursiveTree($child);
}

function recursiveTree($nodeId)
{
	global $completTree, $ret_json, $listFoldersLimitedKeys, $listRestrictedFoldersForItemsKeys, $tree;
	
	// Be sure that user can only see folders he/she is allowed to
    if (
        !in_array($completTree[$nodeId]->id, $_SESSION['forbiden_pfs'])
        || in_array($completTree[$nodeId]->id, $_SESSION['groupes_visibles'])
        || in_array($completTree[$nodeId]->id, $listFoldersLimitedKeys)
        || in_array($completTree[$nodeId]->id, $listRestrictedFoldersForItemsKeys)
    ) {
        $displayThisNode = false;
        $hide_node = false;
        $nbChildrenItems = 0;
		
        // Check if any allowed folder is part of the descendants of this node
        $nodeDescendants = $tree->getDescendants($completTree[$nodeId]->id, true, false, true);
        foreach ($nodeDescendants as $node) {
            // manage tree counters
            if (isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1) {
                DB::query(
                    "SELECT * FROM ".prefix_table("items")."
                    WHERE inactif=%i AND id_tree = %i",
                    0,
                    $node
                );
                $nbChildrenItems += DB::count();
            }
            if (
                in_array(
                    $node,
                    array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items'])
                )
                || in_array($node, $listFoldersLimitedKeys)
                || in_array($node, $listRestrictedFoldersForItemsKeys)
            ) {
                $displayThisNode = true;
                //break;
            }
        }

        if ($displayThisNode == true) {
			$hide_node = false;
			
			DB::query(
                "SELECT * FROM ".prefix_table("items")."
                WHERE inactif=%i AND id_tree = %i",
                0,
                $completTree[$nodeId]->id
            );
            $itemsNb = DB::count();
			
            // If personal Folder, convert id into user name
            if ($completTree[$nodeId]->title == $_SESSION['user_id'] && $completTree[$nodeId]->nlevel == 1) {
                $completTree[$nodeId]->title = $_SESSION['login'];
            }
			
			// prepare json return for current node
			if ($completTree[$nodeId]->parent_id==0) $parent = "#";
			else $parent = "li_".$completTree[$nodeId]->parent_id;
			if (!empty($ret_json)) $ret_json .= ", ";
			$text = str_replace("&", "&amp;", $completTree[$nodeId]->title);
			$restricted = "0";
			$folderClass = "folder";
			
			if (in_array($completTree[$nodeId]->id, $_SESSION['groupes_visibles'])) {
                if (in_array($completTree[$nodeId]->id, $_SESSION['read_only_folders'])) {
                    $fldTitle = '<i class="fa fa-eye"></i>&nbsp;'.$fldTitle.'';
                    $restricted = 1;
                    $folderClass = "folder_not_droppable";
                }
				$text .= ' (<span class=\'items_count\' id=\'itcount_'.$completTree[$nodeId]->id.'\'>'.$itemsNb.'</span>';
				// display tree counters
                if (isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1) {
                    $text .= '|'.$nbChildrenItems.'|'.(count($nodeDescendants)-1);
                }
                $text .= ')';
			} elseif (in_array($completTree[$nodeId]->id, $listFoldersLimitedKeys)) {
				$restricted = "1";
				$text .= ' (<span class=\'items_count\' id=\'itcount_'.$completTree[$nodeId]->id.'">'.count($_SESSION['list_folders_limited'][$completTree[$nodeId]->id]).'</span>';
            } elseif (in_array($completTree[$nodeId]->id, $listRestrictedFoldersForItemsKeys)) {
				$restricted = "1";
				$text .= ' (<span class=\'items_count\' id=\'itcount_'.$completTree[$nodeId]->id.'">'.count($_SESSION['list_restricted_folders_for_items'][$completTree[$nodeId]->id]).'</span>';
            } else {
				$restricted = "1";
				$folderClass = "folder_not_droppable";
                if (isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] == 1) {
                    $hide_node = true;
                }
            }
				
			// json
			if ($hide_node == false) {
				$ret_json .= '{'.
					'"id":"li_'.$completTree[$nodeId]->id.'"'.
					', "parent":"'.$parent.'"'.
					', "text":"'.$text.'"'.
					', "li_attr":{"class":"jstreeopen", "title":"ID ['.$completTree[$nodeId]->id.']"}'.
					', "a_attr":{"id":"fld_'.$completTree[$nodeId]->id.'", "class":"'.$folderClass.'" , "onclick":"ListerItems(\''.$completTree[$nodeId]->id.'\', \''.$restricted.'\', 0)"}'.
				'}';
			}
			
			
			foreach ($completTree[$nodeId]->children as $child) {
				recursiveTree($child);
			}
		}
	}
	
}
echo '['.$ret_json.']';