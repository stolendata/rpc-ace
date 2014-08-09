<?php
/*
    tally.php - an RPC-based "rich list" generator

    (c) 2014 Robin Leffmann <djinn at stolendata dot net>

    https://github.com/stolendata/rpc-ace/

    Licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
*/

$rpcUser = 'user';
$rpcPass = 'pass';
$rpcHost = '127.0.0.1';
$rpcPort = 12345;

$abort = false;
$numAddresses = 100;
$txBufferSize = 150;
$resume = "$rpcUser-$rpcPort-tally.dat";

if( !isset($argv[1]) )
    die( "Usage: php {$argv[0]} <output filename>\n" );

declare( ticks = 1 );
function handleInt()
{
    global $abort;
    $abort = true;
}
pcntl_signal( SIGINT, 'handleInt' );

require_once( 'easybitcoin.php' );
$rpc = new Bitcoin( $rpcUser, $rpcPass, $rpcHost, $rpcPort );
$numBlocks = $rpc->getinfo()['blocks'];
if( $rpc->status !== 200 && $rpc->error !== '' )
    die( "Failed to connect. Check your coin's .conf file and your RPC parameters.\n" );

$i = $txTotal = 0;
if( file_exists($resume) )
{
    $tally = unserialize( file_get_contents($resume) );
    if( $tally['tally'] === true )
    {
        $i = $tally['last'];
        $txTotal = $tally['txTotal'];
        $addresses = $tally['addresses'];
        $numAddresses = $tally['numAddresses'];
        echo 'resuming from block ' . ( $i + 1 ) . ' - ';
    }
}

$next = $rpc->getblockhash( $i + 1 );
echo "$numBlocks blocks ... ";
while( ++$i <= $numBlocks && $abort === false )
{
    if( $i % 1000 == 0 )
        echo "$i (tx# $txTotal)   ";

    $block = $rpc->getblock( $next );
    foreach( $block['tx'] as $txid )
    {
        $txTotal++;
        $tx = $rpc->getrawtransaction( $txid, 1 );

        foreach( $tx['vout'] as $vout )
            if( $vout['value'] > 0.0 )
                @$addresses[$vout['scriptPubKey']['addresses'][0]] += $vout['value'];

        foreach( $tx['vin'] as $vin )
            if( ($refOut = @$vin['txid']) )
            {
                if( array_key_exists($refOut, $txBuffer) )
                    $refTx = &$txBuffer[$refOut];
                else
                    $refTx = $rpc->getrawtransaction( $refOut, 1 );
                $addresses[$refTx['vout'][$vin['vout']]['scriptPubKey']['addresses'][0]] -= $refTx['vout'][$vin['vout']]['value'];
                unset( $refTx );
            }

        $txBuffer[$txid] = $tx;
        if( count($txBuffer) > $txBufferSize )
            array_shift( $txBuffer );
    }
    if( ($next = @$block['nextblockhash']) === null )
        $abort = true;
}
$rpc = null;

// save progress
file_put_contents( $resume, serialize(['tally'=>true,
                                       'numAddresses'=>$numAddresses,
                                       'addresses'=>$addresses,
                                       'txTotal'=>$txTotal,
                                       'last'=>$i-1]) );

natsort( $addresses );
$addresses = array_reverse( $addresses );

$i = 0;
while( (list($key, $value) = each( $addresses )) && $i++ < $numAddresses )
    file_put_contents( $argv[1], "$key $value\n", FILE_APPEND );

echo ( $abort ? 'aborted -' : 'done!' ) . " $txTotal transactions through " . count( $addresses ) . " unique addresses counted\n";

?>