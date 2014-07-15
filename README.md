RPC Ace (RPC AnyCoin Explorer)
==============================

Copyright (c) 2014 Robin Leffmann

RPC Ace is a simple alternative block explorer written in PHP. It does not generate a database, and it interacts with block chains entirely via RPC, either against a locally running wallet/daemon or remotely over the Internet.

A database-less explorer has a few drawbacks - most notably, RPC Ace cannot keep track of addresses or total coins generated, and as it uses RPC calls to parse blocks a transaction-heavy block chain (such as Bitcoin) can incur heavy CPU usage and/or long page generation times. RPC Ace's primary use is quick access to oversight of a block chain; for in-depth needs it's recommended to run a tallying explorer such as Abe.

RPC Ace has been tested to work with Bitcoin, Litecoin, Dogecoin, Solcoin and a few other block chains, but as it's still a beta stage mishmash it may contain bugs. 0.6.5 introduced experimental PoS support which has so far only been tested to work with CryptCoin.


Setting up RPC Ace
------------------

RPC Ace requires PHP version 5.4 or later, with CURL and JSON support enabled.

Place `rpcace.php` and `easybitcoin.php` ([get it here](https://github.com/aceat64/EasyBitcoin-PHP)) together in your web directory. The first few lines of `rpcace.php` contain all of its configurable parameters:

    $coinName = 'Somecoin';                  // Coin name/title for the explorer
    $coinHome = 'http://www.somecoin.org/';  // Coin website
    $coinPoS = false;                        // Experimental; only tested on CryptCoin so far
    $rpcHost = '127.0.0.1';                  // Host/IP for the daemon
    $rpcPort = 12345;                        // RPC port for the daemon
    $rpcUser = 'username';                   // 'rpcuser' from somecoin.conf
    $rpcPass = 'password';                   // 'rpcpassword' from somecoin.conf
    $numBlocksPerPage = 12;                  // Number of blocks to parse per page

To get accurate transaction values you must maintain a full transaction index in your block chain by setting `txindex=1` in somecoin.conf.

Donations
---------

BTC: 1EDhbo9ejdKUxNW3GPBh1UmocC1ea1TvE5  
LTC: LaDuRFwEt1V26pmJJH94auDvxqN3rRFqPj  
DOGE: DK2pB2XXQ9w13UZD2J9wsEHFVDvuE767wT


License
-------

RPC Ace is released under the Creative Commons BY-NC-SA 4.0 license: http://creativecommons.org/licenses/by-nc-sa/4.0/
