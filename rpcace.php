<?php
/*
    RPC Ace v0.8.0 (RPC AnyCoin Explorer)

    (c) 2014 - 2015 Robin Leffmann <djinn at stolendata dot net>

    https://github.com/stolendata/rpc-ace/

    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
*/

const ACE_VERSION = '0.8.0';

const RPC_HOST = '127.0.0.1';
const RPC_PORT = 12345;
const RPC_USER = 'username';
const RPC_PASS = 'password';

const COIN_NAME = 'Somecoin';
const COIN_POS = false;

const RETURN_JSON = false;
const DATE_FORMAT = 'Y-M-d H:i:s';
const BLOCKS_PER_LIST = 12;

const DB_FILE = 'db/somecoin_db.sq3';

// for the example explorer
const COIN_HOME = 'https://www.coin.org/';
const REFRESH_TIME = 180;

// courtesy of https://github.com/aceat64/EasyBitcoin-PHP/
require_once( 'easybitcoin.php' );

class RPCAce
{
    private static $block_fields = [ 'hash', 'nextblockhash', 'previousblockhash', 'confirmations', 'size', 'height', 'version', 'merkleroot', 'time', 'nonce', 'bits', 'difficulty', 'mint', 'proofhash' ];

    private static function base()
    {
        $rpc = new Bitcoin( RPC_USER, RPC_PASS, RPC_HOST, RPC_PORT );
        $info = $rpc->getinfo();
        if( $rpc->status !== 200 && $rpc->error !== '' )
            return [ 'err'=>'failed to connect - node not reachable, or user/pass incorrect' ];

        if( DB_FILE )
        {
            $pdo = new PDO( 'sqlite:' . DB_FILE );
            $pdo->exec( 'create table if not exists block ( height int, hash char(64), json blob );
                         create table if not exists tx ( txid char(64), json blob );
                         create unique index if not exists ub on block ( height );
                         create unique index if not exists uh on block ( hash );
                         create unique index if not exists ut on tx ( txid );' );
        }

        $output['rpcace_version'] = ACE_VERSION;
        $output['coin_name'] = COIN_NAME;
        $output['num_blocks'] = $info['blocks'];
        $output['num_connections'] = $info['connections'];

        if( COIN_POS === true )
        {
            $output['current_difficulty_pow'] = $info['difficulty']['proof-of-work'];
            $output['current_difficulty_pos'] = $info['difficulty']['proof-of-stake'];
        }
        else
            $output['current_difficulty_pow'] = $info['difficulty'];

        if( !($hashRate = @$rpc->getmininginfo()['netmhashps']) && !($hashRate = @$rpc->getmininginfo()['networkhashps'] / 1000000) )
            $hashRate = $rpc->getnetworkhashps() / 1000000;
        $output['hashrate_mhps'] = sprintf( '%.2f', $hashRate );

        return [ 'output'=>$output, 'rpc'=>$rpc, 'pdo'=>@$pdo ];
    }

    private static function block( $base, $b )
    {
        if( DB_FILE )
        {
            $sth = $base['pdo']->prepare( 'select json from block where height = ? or hash = ?;' );
            $sth->execute( [$b, $b] );
            $block = $sth->fetchColumn();
            if( $block )
                $block = json_decode( gzinflate($block), true );
        }
        if( @$block == false )
        {
            if( strlen($b) < 64 )
                $b = $base['rpc']->getblockhash( $b );
            $block = $base['rpc']->getblock( $b );
        }

        if( DB_FILE && @$block )
        {
            $sth = $base['pdo']->prepare( 'insert into block values (?, ?, ?);' );
            $sth->execute( [$block['height'], $block['hash'], gzdeflate(json_encode($block))] );
        }

        return $block ? $block : false;
    }

    private static function tx( $base, $txid )
    {
        if( DB_FILE )
        {
            $sth = $base['pdo']->prepare( 'select json from tx where txid = ?;' );
            $sth->execute( [$txid] );
            $tx = $sth->fetchColumn();
            if( $tx )
                $tx = json_decode( gzinflate($tx), true );
        }
        if( @$tx == false )
            $tx = $base['rpc']->getrawtransaction( $txid, 1 );

        if( DB_FILE && @$tx )
        {
            $sth = $base['pdo']->prepare( 'insert into tx values (?, ?);' );
            $sth->execute( [$txid, gzdeflate(json_encode($tx))] );
        }

        return $tx ? $tx : false;
    }

    // enumerate block details from hash
    public static function get_block( $hash )
    {
        if( preg_match('/^[0-9a-f]{64}$/i', $hash) !== 1 )
            return RETURN_JSON ? json_encode( ['err'=>'not a valid block hash'] ) : [ 'err'=>'not a valid block hash' ];

        $base = self::base();
        if( isset($base['err']) )
            return RETURN_JSON ? json_encode( $base ) : $base;

        if( ($block = self::block($base, $hash)) === false )
            return RETURN_JSON ? json_encode( ['err'=>'no block with that hash'] ) : [ 'err'=>'no block with that hash' ];

        $total = 0;
        foreach( $block as $id => $val )
            if( $id === 'tx' )
                foreach( $val as $txid )
                {
                    $transaction['id'] = $txid;
                    if( ($tx = self::tx($base, $txid)) === false )
                        continue;

                    if( isset($tx['vin'][0]['coinbase']) )
                        $transaction['coinbase'] = true;

                    foreach( $tx['vout'] as $entry )
                        if( $entry['value'] > 0.0 )
                        {
                            // nasty number formatting trick that hurts my soul, but it has to be done...
                            $total += ( $transaction['outputs'][$entry['n']]['value'] = rtrim(rtrim(sprintf('%.8f', $entry['value']), '0'), '.') );
                            $transaction['outputs'][$entry['n']]['address'] = $entry['scriptPubKey']['addresses'][0];
                        }
                    $base['output']['transactions'][] = $transaction;
                    $transaction = null;
                }
            elseif( in_array($id, self::$block_fields) )
                $base['output']['fields'][$id] = $val;

        $base['output']['total_out'] = $total;
        $base['rpc'] = null;
        return RETURN_JSON ? json_encode( $base['output'] ) : $base['output'];
    }

    // create summarized list from block number
    public static function get_blocklist( $ofs, $n = BLOCKS_PER_LIST )
    {
        $base = self::base();
        if( isset($base['err']) )
            return RETURN_JSON ? json_encode( $base ) : $base;

        $offset = $ofs === null ? $base['output']['num_blocks'] : abs( (int)$ofs );
        if( $offset > $base['output']['num_blocks'] )
            return RETURN_JSON ? json_encode( ['err'=>'block does not exist'] ) : [ 'err'=>'block does not exist' ];

        $i = $offset;
        while( $i >= 0 && $n-- )
        {
            $block = self::block( $base, $i );
            $frame['hash'] = $block['hash'];
            $frame['height'] = $block['height'];
            $frame['difficulty'] = $block['difficulty'];
            $frame['time'] = $block['time'];
            $frame['date'] = gmdate( DATE_FORMAT, $block['time'] );

            $txCount = 0;
            $valueOut = 0;
            foreach( $block['tx'] as $txid )
            {
                $txCount++;
                if( ($tx = self::tx($base, $txid)) === false )
                    continue;
                foreach( $tx['vout'] as $vout )
                    $valueOut += $vout['value'];
            }
            $frame['tx_count'] = $txCount;
            $frame['total_out'] = $valueOut;

            $base['output']['blocks'][] = $frame;
            $frame = null;
            $i--;
        }

        $base['rpc'] = null;
        return RETURN_JSON ? json_encode( $base['output'] ) : $base['output'];
    }
}
?>
<?php
/*
   This is the example block explorer of RPC Ace. If you intend to use just
   the RPCAce class itself to fetch and process the array or JSON output on
   your own, you should remove this entire PHP section.
*/

$query = substr( @$_SERVER['QUERY_STRING'], 0, 64 );

if( strlen($query) == 64 )
    $ace = RPCAce::get_block( $query );
else
{
    $query = ( $query === false || !is_numeric($query) ) ? null : abs( (int)$query );
    $ace = RPCAce::get_blocklist( $query, BLOCKS_PER_LIST );
    $query = $query === null ? @$ace['num_blocks'] : $query;
}

if( isset($ace['err']) || RETURN_JSON === true )
    die( 'RPC Ace error: ' . (RETURN_JSON ? $ace : $ace['err']) );

echo <<<END
<!DOCTYPE html>
<!--
    RPC Ace v0.8.0 (RPC AnyCoin Explorer)

    (c) 2014 - 2015 Robin Leffmann <djinn at stolendata dot net>

    https://github.com/stolendata/rpc-ace/

    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
-->
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta name="robots" content="index,nofollow,nocache" />
<meta name="author" content="Robin Leffmann (djinn at stolendata dot net)" />

END;

if( empty($query) || ctype_digit($query) )
    echo '<meta http-equiv="refresh" content="' . REFRESH_TIME . '; url=' . basename( __FILE__ ) . "\" />\n";
echo '<title>' . COIN_NAME . ' block explorer &middot; RPC Ace v' . ACE_VERSION . "</title>\n";

echo <<<END
<link href="https://fonts.googleapis.com/css?family=Varela" rel="stylesheet" type="text/css">
<style type="text/css">
html { height: 100%;
       background: linear-gradient( to bottom, #80a4b4, #13437a );
       background-attachment: fixed;
       color: #f6f6f6;
       font-family: Varela, sans-serif;
       font-size: 17px;
       white-space: pre; }
a { color: #f6f6f6; }
div.mid { width: 900px;
          margin: 2% auto; }
td { width: 16%; }
td.urgh { width: 100%; }
td.key { text-align: right; }
td.value { padding-left: 16px; width: 100%; }
tr.illu:hover { background-color: #303030; }
</style>
</head>
<body>
<div class="mid">
END;

// header
echo '<table><tr><td class="urgh"><b><a href="' . COIN_HOME . '" target="_blank">' . COIN_NAME . '</a></b> block explorer</td><td>Blocks:</td><td><a href="?' . $ace['num_blocks'] . '">' . $ace['num_blocks'] . '</a>';
$diffNom = 'Difficulty';
$diff = sprintf( '%.3f', $ace['current_difficulty_pow'] );
if( COIN_POS )
{
    $diffNom .= ' &middot; PoS';
    $diff .= ' &middot;' . sprintf( '%.1f', $ace['current_difficulty_pos'] );
}
echo "<tr><td></td><td>$diffNom:</td><td>$diff</td></tr>";
echo '<tr><td>Powered by <a href="https://github.com/stolendata/rpc-ace/" target="_blank">RPC Ace</a> v' . ACE_VERSION . ' (RPC AnyCoin Explorer)</td><td>Network hashrate: </td><td>' . $ace['hashrate_mhps'] . ' MH/s</td></tr><tr><td> </td><td></td><td></td></tr></table>';

// list of blocks
if( isset($ace['blocks']) )
{
    echo "<table><tr><td><b>Block</b></td><td><b>Hash</b></td><td><b>$diffNom</b></td><td><b>Time (UTC)</b></td><td><b>Tx# &middot; Value out</b></td></tr><tr><td colspan=\"5\"></td></tr>";
    foreach( $ace['blocks'] as $block )
        echo "<tr class=\"illu\"><td>{$block['height']}</td><td><a href=\"?{$block['hash']}\">" . substr( $block['hash'], 0, 16 ) . '&hellip;</a></td><td>' . sprintf( '%.2f', $block['difficulty'] ) . "</td><td>{$block['date']}</td><td>{$block['tx_count']} &middot; " . sprintf( '%.2f', $block['total_out'] ) . '</td></tr>';

    $newer = $query < $ace['num_blocks'] ? '<a href="?' . ( $ace['num_blocks'] - $query >= BLOCKS_PER_LIST ? $query + BLOCKS_PER_LIST : $ace['num_blocks'] ) . '">&lt; Newer</a>' : '&lt; Newer';
    $older = $query - count( $ace['blocks'] ) >= 0 ? '<a href="?' . ( $query - BLOCKS_PER_LIST ) . '">Older &gt;</a>' : 'Older &gt;';

    echo "<tr><td colspan=\"5\" class=\"urgh\"> </td></tr><tr><td colspan=\"5\">$newer          $older</td></tr></table>";
}
// block details
elseif( isset($ace['transactions']) )
{
    echo '<table>';
    foreach( $ace['fields'] as $field => $val )
        if( $field == 'previousblockhash' || $field == 'nextblockhash' )
            echo "<tr><td class=\"key\">$field</td><td class=\"value\"><a href=\"?$val\">$val</a></td></tr>";
        else
            echo "<tr><td class=\"key\">$field</td><td class=\"value\">$val</td></tr>";

    foreach( $ace['transactions'] as $tx )
    {
        echo "<tr><td class=\"key\">tx</td><td class=\"value\">{$tx['id']}</td></tr>";
        foreach( $tx['outputs'] as $output )
            echo '<tr><td></td><td class="value">     ' . $output['value'] . ( isset( $tx['coinbase'] ) ? '*' : '' ) . " -&gt; {$output['address']}</td></tr>";
    }

    echo'</table>';
}

echo '</div></body></html>'
?>
