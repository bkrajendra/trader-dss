<?php

# set some globals
$db_hostname = 'localhost';
$db_database = 'trader';
$db_user     = 'postgres';
$db_password = 'happy';

function tr_warn($message='No message!')
{
    print('<font color="red">' . $message . '</font><br>');
}

function update_holdings($pfid)
{
    // this function is probably only going to be used by trade and watch so really shouldn't be here
    global $db_hostname, $db_database, $db_user, $db_password;
    $pf_working_date = get_pf_working_date($pfid);
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $query = "select * from holdings where pfid = '$pfid' order by symb;";
    foreach ($pdo->query($query) as $row)
    {
        $hid = $row['hid'];
        if (isset($_POST["comment_$hid"]))
        {
            $comment = $_POST["comment_$hid"];
            $update = "update holdings set comment = '$comment' where hid = '$hid';";
            try 
            {
                $pdo->exec($update);
            }
            catch (PDOException $e)
            {
                tr_warn('update_holdings:' . $update . ':' . $e->getMessage());
            }
        }
    }
}

function update_cart($cart, $pfid)
{
    // this function is probably only going to be used by trade and watch so really shouldn't be here
    global $db_hostname, $db_database, $db_user, $db_password;
    $pf_working_date = get_pf_working_date($pfid);
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $query = "select * from $cart where date <= '$pf_working_date' and pfid = '$pfid';";
    foreach ($pdo->query($query) as $row)
    {
        $symb = $row['symb'];
        if (isset($_POST["volume_$symb"]))
        {
            $volume = $_POST["volume_$symb"];
            $date = $_POST["date_$symb"];
            if (is_numeric($volume))
            {
                $update = "update $cart set volume = '$volume' where pfid = '$pfid' and date = '$date' and symb = '$symb';";
                try 
                {
                    $pdo->exec($update);
                }
                catch (PDOException $e)
                {
                    tr_warn('update_cart:' . $update . ':' . $e->getMessage());
                }
            }
        }
        if (isset($_POST["comment_$symb"]))
        {
            $comment = $_POST["comment_$symb"];
            $date = $_POST["date_$symb"];
            $update = "update $cart set comment = '$comment' where pfid = '$pfid' and date = '$date' and symb = '$symb';";
            try 
            {
                $pdo->exec($update);
            }
            catch (PDOException $e)
            {
                tr_warn('update_cart:' . $update . ':' . $e->getMessage());
            }
        }
    }
}

function sell_stock($hid, $symb, $comment = '')
{
    // Move stock out of holdings, add a record to trades and update pf_summary
    global $db_hostname, $db_database, $db_user, $db_password;
    $pfid = $_SESSION['pfid'];
    $date = get_pf_working_date($pfid);
    $exch = get_pf_exch($pfid);
    $close = get_stock_close($symb, $date, $exch);
    $volume = get_hid_volume($hid);
    if ($comment == '')
    {
        $comment = "$name: $date";
    }
    if ($volume != 0)
    {
        $qty = $volume;
    }
    else
    {
        // this means that a volume of '0' buys one parcel's worth.
        // is that what we want?
        if ($close < $parcel)
        {
            $qty = (int)($parcel/$close);
        }
        else
        {
            $qty = 1;
        }
    }
    // we're selling so the sale quantity is negative (this should still work for shorts)
    $qty = 0 - $qty;
    $total = $qty * $close;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    // find out how much money, and stocks are in the portfolio today
    $query = "select cash_in_hand, holdings from pf_summary where pfid = '$pfid' and date = '$date';";
    foreach ($pdo->query($query) as $row)
    {
        $cash_in_hand = $row['cash_in_hand'];
        $holdings = $row['holdings'];
    }
    $cash_in_hand = $cash_in_hand - $total;
    $holdings = $holdings + $total;
    try 
    {
        $pdo->beginTransaction();
        // add the trade to the trades table
        $query = "insert into trades (pfid, date, symb, price, volume, comment) values ('$pfid', '$date', '$symb', '$close', '$qty', '$comment');";
        $pdo->exec($query);
        // delete the stock from the holdings table
        $query = "delete from holdings where hid = '$hid';";
        $pdo->exec($query);
        // update the pf_summary with the trade
        $query = "update pf_summary set cash_in_hand = '$cash_in_hand', holdings = '$holdings' where date = '$date' and pfid = '$pfid';";
        $pdo->exec($query);
        $pdo->commit();
    }
    catch (PDOException $e)
    {
        tr_warn('sell_stock:' . $query . ':' . $e->getMessage());
        return false;
    }
    return true;
}

function buy_stock($symb, $comment = '', $volume = 0)
{
    // moves stock from the cart to trades and updates pf_summary
    global $db_hostname, $db_database, $db_user, $db_password;
    $pfid = $_SESSION['pfid'];
    $date = get_pf_working_date($pfid);
    $exch = get_pf_exch($pfid);
    $close = get_stock_close($symb, $date, $exch);
    if ($comment == '')
    {
        $comment = "$name: $date";
    }
    if ($volume != 0)
    {
        $qty = $volume;
    }
    else
    {
        // this means that a volume of '0' buys one parcel's worth.
        // is that what we want?
        if ($close < $parcel)
        {
            $qty = (int)($parcel/$close);
        }
        else
        {
            $qty = 1;
        }
    }
    $total = $qty * $close;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    // find out how much money, and stocks are in the portfolio today
    $query = "select cash_in_hand, holdings from pf_summary where pfid = '$pfid' and date = '$date';";
    foreach ($pdo->query($query) as $row)
    {
        $cash_in_hand = $row['cash_in_hand'];
        $holdings = $row['holdings'];
    }
    $cash_in_hand = $cash_in_hand - $total;
    $holdings = $holdings + $total;
    try 
    {
        $pdo->beginTransaction();
        // add the trade to the trades table
        $query = "insert into trades (pfid, date, symb, price, volume, comment) values ('$pfid', '$date', '$symb', '$close', '$qty', '$comment');";
        $pdo->exec($query);
        // add the stock to the holdings table
        $query = "insert into holdings (pfid, date, symb, price, volume, comment) values ('$pfid', '$date', '$symb', '$close', '$qty', '$comment');";
        $pdo->exec($query);
        // update the pf_summary with the trade
        $query = "update pf_summary set cash_in_hand = '$cash_in_hand', holdings = '$holdings' where date = '$date' and pfid = '$pfid';";
        $pdo->exec($query);
        $pdo->commit();
    }
    catch (PDOException $e)
    {
        tr_warn('buy_stock:' . $query . ':' . $e->getMessage());
        return false;
    }
    return true;
}

function add_to_cart($table, $symb, $comment = '', $volume = 0)
{
    // adds all symbols in the given list to the given table
    global $db_hostname, $db_database, $db_user, $db_password;
    $pfid = $_SESSION['pfid'];
    $date = get_pf_working_date($pfid);
    if (isset($_SESSION['sql_name']))
    {
        $name = $_SESSION['sql_name'];
    }
    else
    {
        $name = get_pf_name($pfid);
    }
    $exch = get_pf_exch($pfid);
    $close = get_stock_close($symb, $date, $exch);
    $parcel = get_pf_parcel_size($pfid);
    if ($comment == '')
    {
        $comment = "$name: $date";
    }
    if ($volume != 0)
    {
        $qty = $volume;
    }
    else
    {
        if ($close < $parcel)
        {
            $qty = (int)($parcel/$close);
        }
        else
        {
            $qty = 1;
        }
    }
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $query = "insert into $table (pfid, date, symb, volume, comment) values ('$pfid', '$date', '$symb', '$qty', '$comment');";
    try 
    {
        $pdo->exec($query);
        return true;
    }
    catch (PDOException $e)
    {
        tr_warn('add_to_cart:' . $query . ':' . $e->getMessage());
        return false;
    }
}

function is_in_cart($table, $symb)
{
    // checks if the given symbol is already in the given cart
    global $db_hostname, $db_database, $db_user, $db_password;
    $pfid = $_SESSION['pfid'];
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $query = "select count(*) from $table where pfid = '$pfid' and symb = '$symb';";
    try 
    {
        $result = $pdo->query($query);
    }
    catch (PDOException $e)
    {
        tr_warn('is_in_cart:' . $query . ':' . $e->getMessage());
        return false;
    }
    $row = $result->fetch(PDO::FETCH_ASSOC);
    return ($row['count'] > 0);
}

function del_from_cart($table, $symb)
{
    // removes a symbol from a cart
    $pfid = $_SESSION['pfid'];
    $date = $_POST["date_$symb"];
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $query = "delete from $table where symb = '$symb' and date = '$date' and pfid = '$pfid';";
    try 
    {
        $pdo->exec($query);
        return true;
    }
    catch (PDOException $e)
    {
        tr_warn('del_from_cart:' . $query . ':' . $e->getMessage());
        return false;
    }
}

function t_for_true($value)
{
    // returns true if $value is 't' and false for every other value
    if (isset($value))
    {
        if ($value == 't')
        {
            return true;
        }
    }
    return false;
}

function redirect_login_pf()
{
    /* the idea here is to redirect to the login or porftolio selection page when 
       the cookie doesn't contain a valid username or portfolio.
       This will stop someone from opening a browser and getting some page other than the login page
     */
    global $scramble_names;
    session_start();
    $login_page = "/login.php";
    $portfolio_page = "/portfolios.php";
    $URI = $_SERVER['REQUEST_URI'];
    // if a username isn't set, redirect to the login page
    if (! isset($_SESSION['username']))
    {
        // don't redirect if we're already heading for it
        if ( $URI != $login_page )
        {
            header("Location: $login_page");
            exit;
        }
    }
    // otherwise if a portfolio hasn't been selected, select one
    elseif (! isset($_SESSION['pfid']))
    {
        // don't redirect if we're already heading for it
        if ( $URI != $portfolio_page )
        {
            header("Location: $portfolio_page");
            exit;
        }
    }
    $scramble_names = t_for_true($_SESSION['hide']);
}

function draw_cell($cell_desc, $cell_link, $cell_colour, $cell_selectable)
{
    print "<td bgcolor=\"$cell_colour\">";
    if ($cell_selectable)
    {
        print "<a href=\"$cell_link\">$cell_desc</a>";
    }
    else
    {
        print "$cell_desc";
    }
    print "</td>\n";
}

function draw_summary($username, $pf_name, $exch_name, $working_date, $query_name, $chart_name)
{
    print '<table border="0" cellpadding="5" cellspacing="0" width="90%" align="center">';
    if ($username == 'N/A')
    {
        print '<tr><td></td>';
    }
    else
    {
        print "<tr><td>User: $username</td>";
    }
    if ($pf_name == 'N/A')
    {
        print '<td></td>';
    }
    else
    {
        print "<td>Portfolio: $pf_name</td>";
    }
    if ($exch_name == 'N/A')
    {
        print '<td></td>';
    }
    else
    {
        print "<td>Exchange: $exch_name</td>";
    }
    if ($working_date == 'N/A')
    {
        print '<td></td>';
    }
    else
    {
        print "<td>Working Date: $working_date</td>";
    }
    if ($query_name == 'N/A')
    {
        print '<td></td>';
    }
    else
    {
        print "<td>Query: $query_name</td>";
    }
    if ($chart_name == 'N/A')
    {
        print '<td></td>';
        print "<td></td></tr></table>\n";
    }
    else
    {
        print "<td>Chart: $chart_name</td></tr></table>\n";
    }
}

function draw_trader_header($active_page, $allow_others=true)
{
    // dray the header with a table linking the trader pages like tabs in a notebook
    // session infomation is used to communicate between the tabs
    $active_page = strtolower($active_page);
    $active_colour = 'white';
    $inactive_colour = 'grey';
    if (! isset($_SESSION['uid']))
    {
        // don't allow other page links if uid isn't set
        $active_page = 'login';
        $allow_others = false;
    }
    elseif (! isset($_SESSION['pfid']))
    {
        // uid is set but pf id isn't. Only allowable active page is 'login' or 'portfolio'
        if ($active_page != 'login' and $active_page != 'portfolios')
        {
            // force to the portfolio page
            $active_page = 'portfolios';
        }
        $allow_others = false;
    }
    if (isset($_SESSION['username']))
    {
        $username = $_SESSION['username'];
    }
    else
    {
        $username = 'N/A';
    }
    if (isset($_SESSION['pfid']))
    {
        $pfid = $_SESSION['pfid'];
        $pf_name = get_pf_name($pfid);
        $exch = new exchange(get_pf_exch($pfid));
        $exch_name = $exch->getName();
        $working_date = get_pf_working_date($pfid);
        $pf_gain = get_pf_day_gain($pfid);
        if ($pf_gain > 0)
        {
            $pf_gain = sprintf("%.2f", $pf_gain);
            $pf_gain = "<font color=\"green\">+$pf_gain</font>";
            $pf_name = "$pf_name ($pf_gain)";
        }
        else
        {
            $pf_gain = sprintf("%.2f", $pf_gain);
            $pf_gain = "<font color=\"red\">-$pf_gain</font>";
            $pf_name = "$pf_name ($pf_gain)";
        }
    }
    else
    {
        $pf_name = $exch_name = $working_date = 'N/A';
    }
    if (isset($_SESSION['sql_name']))
    {
        $qid = $_SESSION['qid'];
        $query_name = $_SESSION['sql_name'];
        $query_name = "$query_name ($qid)";
    }
    else
    {
        $query_name = 'N/A';
    }
    if (isset($_SESSION['chart_name']))
    {
        $chid = $_SESSION['chid'];
        $chart_name = $_SESSION['chart_name'];
        $chart_name = "$chart_name ($chid)";
    }
    else
    {
        $chart_name = 'N/A';
    }
    switch ($active_page) {
        case 'login':
        case 'portfolios':
        case 'booty':
        case 'select':
        case 'trade':
        case 'watch':
        case 'queries':
        case 'docs':
            break;
        default:
            tr_warn("[FATAL]Cannot create header, given $active_page\n");
            $active_page = 'login';
            $allow_others = false;
            break;
    }
    print '<table width="100%" border="0">';
    print '<table border="1" cellpadding="5" cellspacing="0" width="100%" align="center"><tr>';
    if ($active_page == 'login')
    {
        draw_cell($active_page, '/login.php', $active_colour, true);
    }
    else
    {
        // must always be possible to choose the login page
        draw_cell('login', '/login.php', $inactive_colour, true);
    }
    if ($active_page == 'portfolios')
    {
        draw_cell($active_page, '/portfolios.php', $active_colour, $allow_others);
    }
    else
    {
        draw_cell('portfolios', '/portfolios.php', $inactive_colour, $allow_others);
    }
    if ($active_page == 'booty')
    {
        draw_cell($active_page, '/booty.php', $active_colour, $allow_others);
    }
    else
    {
        draw_cell('booty', '/booty.php', $inactive_colour, $allow_others);
    }
    if ($active_page == 'select')
    {
        draw_cell($active_page, '/select.php', $active_colour, $allow_others);
    }
    else
    {
        draw_cell('select', '/select.php', $inactive_colour, $allow_others);
    }
    if ($active_page == 'trade')
    {
        draw_cell($active_page, '/trade.php', $active_colour, $allow_others);
    }
    else
    {
        draw_cell('trade', '/trade.php', $inactive_colour, $allow_others);
    }
    if ($active_page == 'watch')
    {
        draw_cell($active_page, '/watch.php', $active_colour, $allow_others);
    }
    else
    {
        draw_cell('watch', '/watch.php', $inactive_colour, $allow_others);
    }
    if ($active_page == 'queries')
    {
        draw_cell($active_page, '/queries.php', $active_colour, $allow_others);
    }
    else
    {
        draw_cell('queries', '/queries.php', $inactive_colour, $allow_others);
    }
    if ($active_page == 'docs')
    {
        draw_cell($active_page, '/docs.php', $active_colour, $allow_others);
    }
    else
    {
        draw_cell('docs', '/docs.php', $inactive_colour, $allow_others);
    }
    draw_summary($username, $pf_name, $exch_name, $working_date, $query_name, $chart_name);
    print "</tr></table></table>\n";
}

function get_hid_symb($hid)
{
    // return the symbol name of a holding
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $query = "select symb from holdings where hid = '$hid';";
    foreach ($pdo->query($query) as $row)
    {
        return $row['symb'];
    }
    return 'Unknown hid';
}

function get_hid_volume($hid)
{
    // return the symbol name of a holding
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $query = "select volume from holdings where hid = '$hid';";
    foreach ($pdo->query($query) as $row)
    {
        return $row['volume'];
    }
    return 'Unknown hid';
}

function get_pf_name($pfid)
{
    // setup the DB connection for use in this script
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $pf_id = $pdo->quote($pfid);
    $query = "select name from portfolios where pfid = $pf_id;";
    foreach ($pdo->query($query) as $row)
    {
        return $row['name'];
    }
    return 'Unknown Portfolio';
}

function get_pf_hide_names($pfid)
{
    // setup the DB connection for use in this script
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $pf_id = $pdo->quote($pfid);
    $query = "select hide_names from portfolios where pfid = $pf_id;";
    foreach ($pdo->query($query) as $row)
    {
        return $row['hide_names'];
    }
    return 'f';
}

function get_pf_opening_date($pfid)
{
    // setup the DB connection for use in this script
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $pf_id = $pdo->quote($pfid);
    $query = "select date from pf_summary where pfid = $pf_id order by date limit 1;";
    foreach ($pdo->query($query) as $row)
    {
        return $row['date'];
    }
    return 0;
}

function get_pf_days_traded($pfid)
{
    // setup the DB connection for use in this script
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $pf_id = $pdo->quote($pfid);
    $query = "select count(*) as days from pf_summary where pfid = $pf_id;";
    foreach ($pdo->query($query) as $row)
    {
        return $row['days'];
    }
    return 0;
}

function get_pf_day_gain($pfid, $days=1)
{
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $working_total = 0;
    $compare_total = 0;
    $query = "select date, (cash_in_hand + holdings) as total from pf_summary where pfid = $pfid order by date desc limit 1";
    foreach ($pdo->query($query) as $row)
    {
        $working_date = $row['date'];
        $working_total = $row['total'];
    }
    // simple hack, we select $days days before today and the last one we reach is the one we want
    $query = "select date, (cash_in_hand + holdings) as total from pf_summary where pfid = $pfid and date < '$working_date' order by date desc limit $days";
    foreach ($pdo->query($query) as $row)
    {
        $compare_date = $row['date'];
        $compare_total = $row['total'];
    }
    return $working_total - $compare_total;
}

function get_pf_holdings($pfid)
{
    // setup the DB connection for use in this script
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $pf_id = $pdo->quote($pfid);
    $query = "select holdings from pf_summary where pfid = $pf_id order by date desc limit 1;";
    foreach ($pdo->query($query) as $row)
    {
        return $row['holdings'];
    }
    return 0;
}

function get_pf_cash_in_hand($pfid)
{
    // setup the DB connection for use in this script
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $pf_id = $pdo->quote($pfid);
    $query = "select cash_in_hand from pf_summary where pfid = $pf_id order by date desc limit 1;";
    foreach ($pdo->query($query) as $row)
    {
        return $row['cash_in_hand'];
    }
    return 0;
}

function get_pf_exch($pfid)
{
    // setup the DB connection for use in this script
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $pf_id = $pdo->quote($pfid);
    $query = "select exch from portfolios where pfid = $pf_id;";
    foreach ($pdo->query($query) as $row)
    {
        return $row['exch'];
    }
    return 'Unknow Exchange';
}

function get_pf_parcel_size($pfid)
{
    // setup the DB connection for use in this script
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $pf_id = $pdo->quote($pfid);
    $query = "select parcel from portfolios where pfid = $pf_id;";
    foreach ($pdo->query($query) as $row)
    {
        return $row['parcel'];
    }
    return '1';
}

function get_pf_working_date($pfid)
{
    // setup the DB connection for use in this script
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $pf_id = $pdo->quote($pfid);
    $query = "select working_date from portfolios where pfid = $pf_id;";
    foreach ($pdo->query($query) as $row)
    {
        return $row['working_date'];
    }
    return '200-01-01';
}

function is_in_portfolio($symb, $pfid)
{
    // lookup holdings to see if the symbol's there
    return is_in_cart('holdings', $symb);
}

function gain($symb, $exch, $pfid, $pf_working_date)
{
    // work out how much the symbol has gained since it was bought
    // there might be several in holdings so average them all
    global $db_hostname, $db_database, $db_user, $db_password;
    $t_volume = 0;
    $t_price = 0;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $close = get_stock_close($symb, $pf_working_date, $exch);
    $query = "select price, volume from holdings where symb = '$symb';";
    foreach ($pdo->query($query) as $row)
    {
        $t_volume = $t_volume + $row['volume'];
        $t_price = $t_price + ($row['volume'] * $row['price']);
    }
    $avg_price = $t_price / $t_volume;
    if ( $t_volume < 0 )
    {
        // we're shorting
        if ($close > $avg_price)
        {
            return -1;
        }
        elseif ($close < $avg_price)
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }
    else
    {
        // we're going long
        if ($close > $avg_price)
        {
            return 1;
        }
        elseif ($close < $avg_price)
        {
            return -1;
        }
        else
        {
            return 0;
        }
    }
}

function get_symb_name_coloured($symb, $exch, $pfid, $pf_working_date)
{
    // retrieve any field from a table indexed on symb, date, exch
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    if (isset($_SESSION['pfid']))
    {
        // work out if the symbol is already in the portfolio and if it's winning or losing
        if (is_in_portfolio($symb, $pfid))
        {
            $gain = gain($symb, $exch, $pfid, $pf_working_date);
            if ( $gain == 0 )
            {
                $colour = 'orange';
            }
            elseif ($gain > 0 )
            {
                $colour = 'green';
            }
            else
            {
                $colour = 'red';
            }
        }
        else
        {
            $colour = 'black';
        }
    }
    $name = get_symb_name($symb, $exch);
    if ($name)
    {
        return "<font color=\"$colour\">$name</font>";
    }
    else
    {
        return false;
    }
}

function get_symb_name($symb, $exch)
{   
    // retrieve any field from a table indexed on symb, date, exch
    global $db_hostname, $db_database, $db_user, $db_password, $scramble_names;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $query = "select name from stocks where symb = '$symb' and exch = '$exch';";
    foreach ($pdo->query($query) as $row)
    {
        if ($scramble_names)
        {
            return substr(md5($row['name']), 0, 10);
        }
        else
        {
            return $row['name'];
        }
    }
    return false;
}   

function get_stock_close($symb, $date, $exch)
{
    return get_table_field('quotes', 'close', $symb, $date, $exch);
}

function get_table_field($table, $field, $symb, $date, $exch)
{
    // retrieve any field from a table indexed on symb, date, exch
    global $db_hostname, $db_database, $db_user, $db_password;
    try {
        $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Cannot connect: " . $e->getMessage());
    }
    $query = "select $field from $table where symb = '$symb' and date = '$date' and exch = '$exch';";
    foreach ($pdo->query($query) as $row)
    {
        return $row[$field];
    }
    return false;
}

function chart_option($value, $string, $selected = 0)
{
    // return an option output selected if $value == $selected
    if ($value == $selected)
    {
        return "<option value=\"$value\" selected=\"selected\">$string</option>";
    }
    else
    {
        return "<option value=\"$value\">$string</option>";
    }
}

function chart_select()
{
    // print a select list to choose the period to chart
    $select_string = '';
    if (isset($_SESSION['chart_period']))
    {
        $chart_period = $_SESSION['chart_period'];
    }
    else
    {
        $chart_period = 0;
    }
    $select_string = "<select name=\"chart_period\">\n";
    $select_string = $select_string . chart_option(7, '1 week', $chart_period) . "\n";
    $select_string = $select_string . chart_option(30, '1 month', $chart_period) . "\n";
    $select_string = $select_string . chart_option(60, '2 months', $chart_period) . "\n";
    $select_string = $select_string . chart_option(90, '3 months', $chart_period) . "\n";
    $select_string = $select_string . chart_option(180, '6 months', $chart_period) . "\n";
    $select_string = $select_string . chart_option(365, '1 year', $chart_period) . "\n";
    $select_string = $select_string . chart_option(730, '2 years', $chart_period) . "\n";
    $select_string = $select_string . chart_option(1905, '5 years', $chart_period) . "\n";
    $select_string = $select_string . chart_option(3650, '10 years', $chart_period) . "\n";
    $select_string = "$select_string </select>\n";
    return $select_string;
}

class trader_base
{
    // a base class to stop auto-vivication of object variables
    protected function __set($name, $value)
    {
        tr_warn("No such property: $name = $value");
        die("Object error");
    }
    protected function __get($name)
    {
        tr_warn("No such property: $name");
        die("Object error");
    }
    protected function get($name)
    {
        if (isset($this->$name))
        {
            return $this->$name;
        }
        else
        {
            die("[FATAL]: No such property portfolio->$name\n");
        }
    }
}

class exchange extends trader_base
{
    protected $exch, $name, $symb, $currency, $dbh;
    public function __construct($exch_id)
    {
        // setup the DB connection for use in this script
        global $db_hostname, $db_database, $db_user, $db_password;
        try {
            $pdo = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("ERROR: Cannot connect: " . $e->getMessage());
        }
        $query = "select * from exchange where exch = '$exch_id';";
        try 
        {
            $result = $pdo->query($query);
        }
        catch (PDOException $e)
        {
            tr_warn('exchange:__construct:' . $query . ':' . $e->getMessage());
            die("[FATAL]Class: exchange, function: __construct\n");
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if (isset($row['exch']) and $row['exch'] == $exch_id)
        {
            $this->exch = $row['exch'];
            $this->name = $row['name'];
            $this->symb = $row['curr_desc'];
            $this->symb = $row['curr_char'];
            $this->dbh = $pdo;
        }
        else
        {
            die("[FATAL]exchange $exch_id missing from exchange table: $query\n");
        }
    }
    public function getID()
    {
        return $this->exch;
    }
    public function getName()
    {
        return $this->name;
    }
    public function getSymb()
    {
        return $this->symb;
    }
    public function getCurrency()
    {
        return $this->currency;
    }
    public function nextTradeDay($date)
    {
        // returns the next trading day for the exchange
        $exch = $this->exch;
        $query = "select date from trade_dates where date > '$date' and exch = '$exch' order by date asc limit 1;";
        try 
        {
            $result = $this->dbh->query($query);
        }
        catch (PDOException $e)
        {
            tr_warn('nextTradeDay:' . $query . ':' . $e->getMessage());
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $next_date = $row['date'];
        return $next_date;
    }
    public function nearestTradeDay($date)
    {
        // returns the nearest trading day for the exchange
        $exch = $this->exch;
        $query = "select date from trade_dates where date >= '$date' and exch = '$exch' order by date asc limit 1;";
        try 
        {
            $result = $this->dbh->query($query);
        }
        catch (PDOException $e)
        {
            tr_warn('nearestTradeDay:' . $query . ':' . $e->getMessage());
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $next_date = $row['date'];
        return $next_date;
    }
    public function firstDate()
    {
        // returns the first trading day for the exchange
        $exch = $this->exch;
        $query = "select date from trade_dates where exch = '$exch' order by date asc limit 1;";
        try 
        {
            $result = $this->dbh->query($query);
        }
        catch (PDOException $e)
        {
            tr_warn('firstDate:' . $query . ':' . $e->getMessage());
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $next_date = $row['date'];
        return $next_date;
    }
    public function lastDate()
    {
        // returns the first trading day for the exchange
        $exch = $this->exch;
        $query = "select date from trade_dates where exch = '$exch' order by date desc limit 1;";
        try 
        {
            $result = $this->dbh->query($query);
        }
        catch (PDOException $e)
        {
            tr_warn('lastDate:' . $query . ':' . $e->getMessage());
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $next_date = $row['date'];
        return $next_date;
    }
}

class portfolio extends trader_base
{
    protected $pfid, $name, $exch, $parcel, $working_date, $hide_names, $sell_stop, $auto_sell_stop, $dbh;
    protected $cashInHand, $holdings, $openingBalance, $startDate;
    public function __construct($pfid)
    {
        // setup the DB connection for use in this script
        global $db_hostname, $db_database, $db_user, $db_password;
        try {
            $this->dbh = new PDO("pgsql:host=$db_hostname;dbname=$db_database", $db_user, $db_password);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("ERROR: Cannot connect: " . $e->getMessage());
        }
        $query = "select * from portfolios where pfid = '$pfid';";
        try 
        {
            $result = $this->dbh->query($query);
        }
        catch (PDOException $e)
        {
            tr_warn('portfolio:__construct:' . $query . ':' . $e->getMessage());
            die("[FATAL]Class: portfolio, function: __construct\n");
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if (isset($row['pfid']) and $row['pfid'] == $pfid)
        {
            $this->pfid = $row['pfid'];
            $this->name = $row['name'];
            $this->exch = new exchange($row['exch']);
            $this->parcel = $row['parcel'];
            $this->working_date = $row['working_date'];
            $this->hide_names = t_for_true($row['hide_names']);
            $this->sell_stop = $row['sell_stop'];
            $this->auto_sell_stop = t_for_true($row['auto_sell_stop']);
        }
        else
        {
            die("[FATAL]portfolio $pfid missing from portfolios table: $query\n");
        }
        // get opening balance, holdings and startdate
        $query = "select * from pf_summary where pfid = '$pfid' order by date asc limit 1";
        try 
        {
            $result = $this->dbh->query($query);
        }
        catch (PDOException $e)
        {
            tr_warn('portfolio:__construct:' . $query . ':' . $e->getMessage());
            die("[FATAL]Class: portfolio, function: __construct\n");
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if (isset($row['pfid']) and $row['pfid'] == $pfid)
        {
            $this->openingBalance = $row['cash_in_hand'];
            $this->startDate = $row['date'];
        }
        else
        {
            $this->openingBalance = 0;
        }
        // get current balance and  holdings
        $query = "select * from pf_summary where pfid = '$pfid' order by date desc limit 1";
        try 
        {
            $result = $this->dbh->query($query);
        }
        catch (PDOException $e)
        {
            tr_warn('portfolio:__construct:' . $query . ':' . $e->getMessage());
            die("[FATAL]Class: portfolio, function: __construct\n");
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if (isset($row['pfid']) and $row['pfid'] == $pfid)
        {
            $this->cashInHand = $row['cash_in_hand'];
            $this->holdings = $row['holdings'];
        }
        else
        {
            tr_warn('portfolio:__construct:' . $query . ':' . $e->getMessage());
            die("[FATAL]Class: portfolio, function: __construct\n");
        }
    }
    public function getID()
    {
        return $this->pfid;
    }
    public function getExch()
    {
        return $this->exch;
    }
    public function getName()
    {
        return $this->name;
    }
    public function getWorkingDate()
    {
        return $this->working_date;
    }
    public function getParcel()
    {
        return $this->parcel;
    }
    public function getStartDate()
    {
        return $this->startDate;
    }
    public function getCashInHand()
    {
        return $this->cashInHand;
    }
    public function getHoldings()
    {
        return $this->holdings;
    }
    public function getOpeningBalance()
    {
        return $this->openingBalance;
    }
    public function CountDaysTraded()
    {
        // returns the next trading day for the exchange
        $pfid = $this->pfid;
        $query = "select count(*) as days from pf_summary where pfid = '$pfid';";
        try 
        {
            $result = $this->dbh->query($query);
        }
        catch (PDOException $e)
        {
            tr_warn('CountDaysTraded:' . $query . ':' . $e->getMessage());
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        return $row['days'];
    }
}

?>
