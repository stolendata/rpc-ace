<?php
$timeThen = microtime( true );

$rpcAceVersion = '0.6.2';

$coinName = 'Somecoin';
$rpcHttps = false;
$rpcHost = '127.0.0.1';
$rpcPort = 12345;
$rpcUser = 'username';
$rpcPass = 'password';
$numBlocksPerPage = 12;

$blockFields = ['hash', 'confirmations', 'size', 'height', 'version', 'merkleroot', 'time', 'nonce', 'bits', 'difficulty'];

require_once( 'easybitcoin.php' );
?>
<!--

    RPC Ace v<?= $rpcAceVersion ?> (RPC AnyCoin Explorer)

    by Robin Leffmann <djinn at stolendata dot net>

    https://github.com/stolendata/rpc-ace/

    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/

-->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta name="author" content="Robin Leffmann (djinn at stolendata dot net)" />
<meta name="robots" content="noindex,nofollow,nocache" />
<title><?= $coinName ?> block explorer &middot; RPC Ace v<?= $rpcAceVersion ?></title>
<style type="text/css">
@font-face { font-family: ABeeZee;
             src: url( 'http://themes.googleusercontent.com/static/fonts/abeezee/v2/zhbx7NQl8ktGP8FS60Z_oQLUuEpTyoUstqEm5AMlJo4.woff' ) format( 'woff' ); }
html { background-color: #16429a;
       color: #e6e6e6;
       font-family: ABeeZee, sans-serif;
       font-size: 15px;
       white-space: pre; }
a { color: #f0f0f0; }
div.mid { width: 840px;
          margin: 3% auto; }
td { width: 16%; }
td.urgh { width: 100%; }
tr.illu:hover { background-color: #2c58b0; }
</style>
</head>
<body><div class="mid"><table>
<?php
$rpc = new Bitcoin( $rpcUser, $rpcPass, $rpcHost, $rpcPort, $rpcHttps === true ? 'https' : 'http' );
$info = $rpc->getinfo();
$query = $_SERVER['QUERY_STRING'];

echo "<tr><td class=\"urgh\"><b>$coinName</b> block explorer</td><td>Blocks:</td><td><a href=\"?{$info['blocks']}\">{$info['blocks']}</a></td></tr>";
echo "<tr><td /><td>Difficulty:</td><td>{$info['difficulty']}</td></tr>";
echo "<tr><td>Powered by <a href=\"https://github.com/stolendata/rpc-ace/\">RPC Ace</a> v$rpcAceVersion (RPC AnyCoin Explorer)</td><td>Network hashrate: </td><td>" . round( $rpc->getnetworkhashps() / (1024*1024), 2, PHP_ROUND_HALF_DOWN ) . ' MH/s</td></tr><tr><td> </td><td /><td /></tr></table>';

if( preg_match("/^([[:xdigit:]]{64})$/", $query) === 1 ) // block hash?
{
    if( ($block = $rpc->getblock($query)) === false )
        echo 'No matching block hash.<br />';
    else
        foreach( $block as $id => $val )
            if( $id === 'tx' )
                foreach( $val as $txid ) // list of txids
                {
                    echo "$id: $txid<br />";
                    $tx = $rpc->getrawtransaction( $txid, 1 );
                    foreach( $tx['vout'] as $entry )
                        echo "     {$entry['value']} -> {$entry['scriptPubKey']['addresses'][0]}<br />";
                }
            else // block fields
            {
                if( $id === 'previousblockhash' || $id === 'nextblockhash' )
                    echo "$id: <a href=\"?$val\">$val</a><br />";
                else
                    if( in_array($id, $blockFields) === true )
                        echo "$id: $val<br />";
            }
}
else // list of blocks
{
    $offset = abs( (int)$query );
    $offset = ( !is_numeric($query) || $offset > $info['blocks'] ) ? $info['blocks'] : $offset;

    echo '<table><tr><td><b>Block</b></td><td><b>Hash</b></td><td><b>Difficulty</b></td><td><b>Time (UTC)</b></td><td><b>Tx# &middot; Value out</b></td></tr><tr><td /></tr>';
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
            $tx = $rpc->getrawtransaction( $txid, 1 );
            foreach( $tx['vout'] as $vout )
                $valueOut += $vout['value'];
        }
        $valueOut = round( $valueOut, 4, PHP_ROUND_HALF_UP );

        echo "<tr class=\"illu\"><td>$i</td><td><a href=\"?$hash\">$hashShort ...</a></td><td>$diff</td><td><a title=\"{$block['time']}\">$time</a></td><td>$txCount &middot; $valueOut</td></tr>";
        $i--;
    }

    $newer = $offset + $numBlocksPerPage;
    $older = $offset - $numBlocksPerPage;
    $newer = $offset < $info['blocks']  ? "<a href=\"?$newer\">&lt; Newer</a>" : '&lt; Newer';
    $older = $i != -1 ? "<a href=\"?$older\">Older &gt;</a>" : 'Older &gt;';
    $i++;

    echo "<tr><td colspan=\"5\" class=\"urgh\"> </td></tr><tr><td colspan=\"6\">$newer          $older</td></tr>";
}

$rpc = null;
?>
</table></div></body>
</html>
<!-- <?php printf( '%.4fs', microtime(true) - $timeThen ); ?> -->