<?php
$timeThen = microtime( true );

$aceVersion = '0.6.7';

$rpcHost = '127.0.0.1';
$rpcPort = 12345;
$rpcUser = 'username';
$rpcPass = 'password';
$coinName = 'Somecoin';
$coinHome = 'http://www.somecoin.org/';
$coinPoS = false;
$numBlocksPerPage = 12;
$refreshTime = 180;

$blockFields = [ 'hash', 'confirmations', 'size', 'height', 'version', 'merkleroot', 'time', 'nonce', 'bits', 'difficulty', 'mint', 'proofhash' ];

require_once( 'easybitcoin.php' );
$query = $_SERVER['QUERY_STRING'];
?>
<!--

    RPC Ace v<?= $aceVersion ?> (RPC AnyCoin Explorer)

    (c) 2014 Robin Leffmann <djinn at stolendata dot net>

    https://github.com/stolendata/rpc-ace/

    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/

-->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<?php if( empty($query) ) echo "<meta http-equiv=\"refresh\" content=\"$refreshTime; " . basename( __FILE__ ) . "\" />\n"; ?>
<meta name="author" content="Robin Leffmann (djinn at stolendata dot net)" />
<meta name="robots" content="nofollow,nocache" />
<title><?= "$coinName block explorer &middot; RPC Ace v$aceVersion" ?></title>
<style type="text/css">
@font-face { font-family: Montserrat;
             src: url( 'http://fonts.gstatic.com/s/montserrat/v5/zhcz-_WihjSQC0oHJ9TCYL3hpw3pgy2gAi-Ip7WPMi0.woff' ) format( 'woff' ); }
html { background-color: #e0e0e0;
       color: #303030;
       font-family: Montserrat, sans-serif;
       font-size: 17px;
       white-space: pre; }
a { color: #303030; }
div.mid { width: 900px;
          margin: 2% auto; }
td { width: 16%; }
td.urgh { width: 100%; }
td.key { text-align: right; }
td.value { padding-left: 16px; width: 100%; }
tr.illu:hover { background-color: #c8c8c8; }
</style>
</head>
<body>
<?php
$rpc = new Bitcoin( $rpcUser, $rpcPass, $rpcHost, $rpcPort );
$info = $rpc->getinfo();
if( $rpc->status !== 200 && $rpc->error !== '' )
    die( 'Failed to connect. Check your coin\'s .conf file and your RPC parameters.' );

if( $coinPoS === true )
{
    $diffNom = 'Difficulty &middot; PoS';
    $diff = sprintf( '%.4f', $info['difficulty']['proof-of-work'] ) . ' &middot; ' . sprintf( '%.4f', $info['difficulty']['proof-of-stake'] );
    $hashRate = $rpc->getmininginfo()['netmhashps'];
}
else
{
    $diffNom = 'Difficulty';
    $diff = $info['difficulty'];
    $hashRate = $rpc->getnetworkhashps() / 1000000;
}
$hashRate = sprintf( '%.2f', $hashRate );

echo "<div class=\"mid\"><table><tr><td class=\"urgh\"><b><a href=\"$coinHome\">$coinName</a></b> block explorer</td><td>Blocks:</td><td><a href=\"?{$info['blocks']}\">{$info['blocks']}</a></td></tr>";
echo "<tr><td /><td>$diffNom:</td><td>$diff</td></tr>";
echo "<tr><td>Powered by <a href=\"https://github.com/stolendata/rpc-ace/\">RPC Ace</a> v$aceVersion (RPC AnyCoin Explorer)</td><td>Network hashrate: </td><td>$hashRate MH/s</td></tr><tr><td> </td><td /><td /></tr></table>";

if( preg_match("/^([[:xdigit:]]{64})$/", $query) === 1 ) // block hash?
{
    if( ($block = $rpc->getblock($query)) === false )
        echo 'No matching block hash.<br />';
    else
    {
        echo '<table>';
        foreach( $block as $id => $val )
            if( $id === 'tx' )
                foreach( $val as $txid ) // list of txids
                {
                    echo "<tr><td class=\"key\">$id</td><td class=\"value\">$txid</td></tr>";
                    if( ($tx = $rpc->getrawtransaction($txid, 1)) === false )
                        continue;
                    foreach( $tx['vout'] as $entry )
                        if( $entry['value'] > 0.0 )
                            // nasty number formatting trick that hurts my soul, but it had to be done...
                            echo '<tr><td /><td class="value">     ' . rtrim( rtrim(sprintf('%.8f', $entry['value']), '0'), '.' ) . " -> {$entry['scriptPubKey']['addresses'][0]}</td></tr>";
                }
            else // block fields
            {
                if( $id === 'previousblockhash' || $id === 'nextblockhash' )
                    echo "<tr><td class=\"key\">$id</td><td class=\"value\"><a href=\"?$val\">$val</a></td></tr>";
                elseif( in_array($id, $blockFields) === true )
                    echo "<tr><td class=\"key\">$id</td><td class=\"value\">$val</td></tr>";
            }
        echo '</table>';
    }
}
else // list of blocks
{
    $offset = abs( (int)$query );
    $offset = ( !is_numeric($query) || $offset > $info['blocks'] ) ? $info['blocks'] : $offset;

    echo "<table><tr><td><b>Block</b></td><td><b>Hash</b></td><td><b>$diffNom</b></td><td><b>Time (UTC)</b></td><td><b>Tx# &middot; Value out</b></td></tr><tr><td /></tr>";
    $n = $numBlocksPerPage;
    $i = $offset;
    while( $i >= 0 && $n-- )
    {
        $hash = $rpc->getblockhash( $i );
        $hashShort = substr( $hash, 0, 16 );
        $block = $rpc->getblock( $hash );
        $diff = round( $block['difficulty'], 4, PHP_ROUND_HALF_DOWN );
        $time = gmdate( 'H:i:s d-M-Y', $block['time'] );

        $txCount = 0;
        $valueOut = 0;
        foreach( $block['tx'] as $txid )
        {
            $txCount++;
            if( ($tx = $rpc->getrawtransaction($txid, 1)) === false )
                continue;
            foreach( $tx['vout'] as $vout )
                $valueOut += $vout['value'];
        }
        $valueOut = round( $valueOut, 4, PHP_ROUND_HALF_UP );

        echo "<tr class=\"illu\"><td>$i</td><td><a href=\"?$hash\">$hashShort ...</a></td><td>$diff</td><td><a title=\"{$block['time']}\">$time</a></td><td>$txCount &middot; $valueOut</td></tr>";
        $i--;
    }

    $newer = $offset < $info['blocks'] ? '<a href="?' . ( $offset + $numBlocksPerPage ) . '">&lt; Newer</a>' : '&lt; Newer';
    $older = $i != -1 ? '<a href="?' . ( $offset - $numBlocksPerPage ) . '">Older &gt;</a>' : 'Older &gt;';
    $i++;

    echo "<tr><td colspan=\"5\" class=\"urgh\"> </td></tr><tr><td colspan=\"6\">$newer          $older</td></tr></table>";
}

$rpc = null;
?>
</div></body>
</html>
<!-- <?php printf( '%.4fs', microtime(true) - $timeThen ); ?> -->
