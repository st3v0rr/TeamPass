<?php
/**
 * @package       datatable.users_logged.php
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.1.27
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require '../SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../../includes/config/tp.config.php')) {
    include_once '../../includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_users") === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    die('Hacking attempt...');
}

if (isset($_GET['token']) === false || $_GET['token'] !== $_SESSION["key"]) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    die('Hacking attempt...');
}

require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

global $k, $settings;
require $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
header("Content-type: text/html; charset=utf-8");
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

//Connect to DB
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

// prepare the queries
if (isset($_GET['action'])) {
    //init SQL variables
    $sWhere = $sOrder = $sLimit = "";
    $aSortTypes = array('ASC', 'DESC');

    /* BUILD QUERY */
    //Paging
    $sLimit = "";
    if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
        $sLimit = "LIMIT ".mysqli_real_escape_string($link, filter_var($_GET['iDisplayStart'], FILTER_SANITIZE_NUMBER_INT)).", ".mysqli_real_escape_string($link, filter_var($_GET['iDisplayLength'], FILTER_SANITIZE_NUMBER_INT));
    }
}

if (isset($_GET['action']) && $_GET['action'] == "connections") {
    //Columns name
    $aColumns = array('l.date', 'l.label', 'l.qui', 'u.login');

    //Ordering
    if (isset($_GET['iSortCol_0']) && isset($_GET['sSortDir_0']) && in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        $sOrder = "ORDER BY  ";
        for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
            if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
                preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    /*
       * Filtering
       * NOTE this does not match the built-in DataTables filtering which does it
       * word by word on any field. It's possible to do here, but concerned about efficiency
       * on very large tables, and MySQL's regex functionality is very limited
    */
    $sWhere = "WHERE l.type = 'user_connection'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query(
        "SELECT l.date as date, l.label as label, l.qui as who, u.login as login
        FROM ".$pre."log_system as l
        INNER JOIN ".$pre."users as u ON (l.qui=u.id)
        $sWhere
        $sOrder",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, l.label as label, l.qui as who, u.login as login
        FROM ".$pre."log_system as l
        INNER JOIN ".$pre."users as u ON (l.qui=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );

    $iFilteredTotal = DB::count();

    /*
       * Output
    */
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.str_replace(array(CHR(10), CHR(13)), array(' ', ' '), htmlspecialchars(stripslashes($record['label']), ENT_QUOTES)).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'"';

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

/* ERRORS LOG */
} elseif (isset($_GET['action']) && $_GET['action'] == "errors") {
    //Columns name
    $aColumns = array('l.date', 'l.label', 'l.qui', 'u.login');

    //Ordering
    if (isset($_GET['iSortCol_0']) && isset($_GET['sSortDir_0']) && in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        $sOrder = "ORDER BY  ";
        for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
            if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
                preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    // Filtering
    $sWhere = " WHERE l.type = 'error'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query(
        "SELECT *
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
        $sWhere
        $sOrder",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, l.label as label, l.qui as who, u.login as login
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );

    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.str_replace(array(CHR(10), CHR(13)), array(' ', ' '), htmlspecialchars(stripslashes($record['label']), ENT_QUOTES)).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'"';

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

/* ACCESS LOG */
} elseif (isset($_GET['action']) && $_GET['action'] == "access") {
    //Columns name
    $aColumns = array('l.date', 'i.label', 'u.login');

    //Ordering
    if (isset($_GET['iSortCol_0']) && isset($_GET['sSortDir_0']) && in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        $sOrder = "ORDER BY  ";
        for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
            if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
                preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    // Filtering
    $sWhere = " WHERE l.action = 'at_shown'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query(
        "SELECT *
        FROM ".$pre."log_items as l
        INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users as u ON (l.id_user=u.id)".
        $sWhere,
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, u.login as login, i.label as label
        FROM ".$pre."log_items as l
        INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );

    $iFilteredTotal = DB::count();

    // Output
    if ($iTotal == "") {
        $iTotal = 0;
    }
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.str_replace(array(CHR(10), CHR(13)), array(' ', ' '), htmlspecialchars(stripslashes($record['label']), ENT_QUOTES)).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'"';

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

/* COPY LOG */
} elseif (isset($_GET['action']) && $_GET['action'] == "copy") {
    //Columns name
    $aColumns = array('l.date', 'i.label', 'u.login');

    //Ordering
    if (isset($_GET['iSortCol_0']) && isset($_GET['sSortDir_0']) && in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        $sOrder = "ORDER BY  ";
        for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
            if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
                preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    // Filtering
    $sWhere = " WHERE l.action = 'at_copy'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query(
        "SELECT *
        FROM ".$pre."log_items as l
        INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
        $sWhere
        $sOrder",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, u.login as login, i.label as label
        FROM ".$pre."log_items as l
        INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );

    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'"';

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

/*
* ADMIN LOG
 **/
} elseif (isset($_GET['action']) && $_GET['action'] === "admin") {
    //Columns name
    $aColumns = array('l.date', 'u.login', 'l.label');

    //Ordering
    if (isset($_GET['iSortCol_0']) && isset($_GET['sSortDir_0']) && in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        $sOrder = "ORDER BY  ";
        for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
            if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
                preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    /*
     * Filtering
    */
    $sWhere = "WHERE l.type = 'admin_action'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3);
        $sWhere .= ") ";
    }

    DB::query(
        "SELECT *
        FROM ".$pre."log_system as l
        INNER JOIN ".$pre."users as u ON (l.qui=u.id)".
        $sWhere,
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, u.login as login, l.label as label
        FROM ".$pre."log_system as l
        INNER JOIN ".$pre."users as u ON (l.qui=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );

    $iFilteredTotal = DB::count();

    /*
     * Output
    */
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';

    foreach ($rows as $record) {
        $get_item_in_list = true;
        $sOutput_item = "[";

        //col1
        $sOutput_item .= '"'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date']).'", ';

        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'", ';

        //col3
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'" ';

        //Finish the line
        $sOutput_item .= '], ';

        if ($get_item_in_list === true) {
            $sOutput .= $sOutput_item;
        }
    }
    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, "", -2);
    }
    $sOutput .= '] }';

/* ITEMS */
} elseif (isset($_GET['action']) && $_GET['action'] === "items") {
    require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
    //Columns name
    $aColumns = array('l.date', 'i.label', 'u.login', 'l.action', 'i.perso');

    //Ordering
    if (isset($_GET['iSortCol_0']) && isset($_GET['sSortDir_0']) && in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        $sOrder = "ORDER BY  ";
        for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
            if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true"
                && preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    /*
     * Filtering
    */
    $sWhere = "";
    if ($_GET['sSearch'] !== "") {
        $sWhere .= " WHERE (";
        for ($i = 1; $i < count($aColumns) - 1; $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3);
        $sWhere .= ") ";
    }

    DB::query(
        "SELECT *
        FROM ".$pre."log_items AS l
        INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
        INNER JOIN ".$pre."nested_tree AS t ON (i.id_tree=t.id)
        $sWhere
        $sOrder",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '4' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date AS date, u.login AS login, i.label AS label,
            i.perso AS perso, l.action AS action, t.title AS folder
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            INNER JOIN ".$pre."nested_tree AS t ON (i.id_tree=t.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '4' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iFilteredTotal = DB::count();

    /*
     * Output
    */
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';

    foreach ($rows as $record) {
        $get_item_in_list = true;
        $sOutput_item = "[";

        //col1
        $sOutput_item .= '"'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date']).'", ';

        //col3
        $sOutput_item .= '"'.(stripslashes("<b>".handleBackslash($record['label'])."</b>&nbsp;<span style='font-size:10px;font-style:italic;'><i class='fa fa-folder-o'></i>&nbsp;".$record['folder']."</span>")).'", ';

        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'", ';

        //col4
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($LANG[$record['action']]), ENT_QUOTES).'", ';

        //col5
        if ($record['perso'] == 1) {
            $sOutput_item .= '"'.htmlspecialchars(stripslashes($LANG['yes']), ENT_QUOTES).'"';
        } else {
            $sOutput_item .= '"'.htmlspecialchars(stripslashes($LANG['no']), ENT_QUOTES).'"';
        }

        //Finish the line
        $sOutput_item .= '], ';

        if ($get_item_in_list === true) {
            $sOutput .= $sOutput_item;
        }
    }
    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, "", -2);
    }
    $sOutput .= '] }';
/* FAILED AUTHENTICATIO? */
} elseif (isset($_GET['action']) && $_GET['action'] == "failed_auth") {
    //Columns name
    $aColumns = array('l.date', 'l.label', 'l.qui', 'l.field_1');

    //Ordering
    if (isset($_GET['iSortCol_0']) && isset($_GET['sSortDir_0']) && in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        $sOrder = "ORDER BY  ";
        for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
            if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
                preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    // Filtering
    $sWhere = " WHERE l.type = 'failed_auth'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query(
        "SELECT *
        FROM ".$pre."log_system as l".
        $sWhere,
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as auth_date, l.label as label, l.qui as who, l.field_1
        FROM ".$pre."log_system as l
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );

    $iFilteredTotal = DB::count();

    // Output
    if ($iTotal == "") {
        $iTotal = 0;
    }
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['auth_date']).'", ';

        //col2
        if ($record['label'] === 'user_password_not_correct' || $record['label'] === 'user_not_exists') {
            $sOutput .= '"'.str_replace(array(CHR(10), CHR(13)), array(' ', ' '), htmlspecialchars(stripslashes($record['label']), ENT_QUOTES)).' - '.$record['field_1'].'", ';
        } else {
            $sOutput .= '"'.str_replace(array(CHR(10), CHR(13)), array(' ', ' '), htmlspecialchars(stripslashes($record['label']), ENT_QUOTES)).'", ';
        }
        

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['who']), ENT_QUOTES).'"';

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($_GET['action']) && $_GET['action'] == "users" && isset($_GET['userid'])) {
    // user id
    $getUserId = filter_var($_GET['userid'], FILTER_SANITIZE_NUMBER_INT);

    //Columns name
    $aColumns = array('l.date', 'i.label', 'l.action', 'i.perso');

    //Ordering
    if (isset($_GET['iSortCol_0']) && isset($_GET['sSortDir_0']) && in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        $sOrder = "ORDER BY  ";
        for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
            if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
                preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    // Filtering
    $sWhere = " WHERE u.id = '".$getUserId."'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }
    
    DB::query(
        "SELECT *
        FROM ".$pre."log_items AS l
        INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
        INNER JOIN ".$pre."nested_tree AS t ON (i.id_tree=t.id)".
        $sWhere,
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date AS date, u.login AS login, i.label AS label,
            i.perso AS perso, l.action AS action, t.title AS folder
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            INNER JOIN ".$pre."nested_tree AS t ON (i.id_tree=t.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iFilteredTotal = DB::count();

    // Output
    if ($iTotal == "") {
        $iTotal = 0;
    }
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
 
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date']).'", ';

        //col3
        $sOutput .= '"'.(stripslashes("<b>".handleBackslash($record['label'])."</b>&nbsp;<span style='font-size:10px;font-style:italic;'><i class='fa fa-folder-o'></i>&nbsp;".$record['folder']."</span>")).'", ';

        //col4
        $sOutput .= '"'.htmlspecialchars(stripslashes($LANG[$record['action']]), ENT_QUOTES).'", ';

        //col5
        if ($record['perso'] == 1) {
            $sOutput .= '"'.htmlspecialchars(stripslashes($LANG['yes']), ENT_QUOTES).'"';
        } else {
            $sOutput .= '"'.htmlspecialchars(stripslashes($LANG['no']), ENT_QUOTES).'"';
        }

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
}

echo $sOutput;
