RPC Ace (RPC AnyCoin Explorer)
==============================

Copyright (c) 2014 - 2017 Robin Leffmann

RPC Ace is a simple alternative block explorer written in PHP. It interacts with block chains entirely via RPC, either against a locally running wallet/daemon or remotely over the Internet, and offers optional database storage for quickly accessing previously processed blocks and transactions.

The lightweight nature of RPC Ace comes with a few drawbacks - most notably, the explorer cannot keep track of addresses or total coins generated, and as it uses RPC calls to parse blocks a transaction-heavy block chain (such as Bitcoin) can incur heavy CPU usage and/or long page generation times the first time a set of blocks is parsed. RPC Ace's primary use is quick access to oversight of a block chain; for in-depth needs it's recommended to run a tallying explorer such as Abe or Iquidus.

RPC Ace should work with any block chain regardless of what proof-of-work algorithm is used, and has been tested to work with Bitcoin, Litecoin, Dogecoin, Solcoin and a few other block chains, but as it's still at an early stage it may contain bugs. Version 0.6.5 introduced PoS support, which has been tested against a number of popular PoS block chains.

Version 0.7.0 introduced optional JSON output through a rewrite of the codebase which split the project into an example block explorer and a class, `RPCAce`, with two functions, `get_block` and `get_blocklist`, for returning a PHP array or JSON of a block's details and a summarized list of blocks respectively. This change allows for removing the example block explorer of RPC Ace in order to use the RPCAce class on its own for processing and presenting block chain data in other ways.

Version 0.8.0 introduced optional database storage via SQLite, offering faster page generation and reduced burden on the node serving RPC requests to the explorer.

Setting up RPC Ace
------------------

RPC Ace (and the extras) requires PHP version 5.4 or later, with CURL and JSON support enabled. Additionally, SQLite and zlib support is required to use the database storage feature.

Place `rpcace.php` and `easybitcoin.php` ([get it here](https://github.com/aceat64/EasyBitcoin-PHP)) together in your web directory. The first few lines of `rpcace.php` defines its configurable parameters:

    RPC_HOST = '127.0.0.1';                   // Host/IP for the daemon
    RPC_PORT = 12345;                         // RPC port for the daemon
    RPC_USER = 'username';                    // 'rpcuser' from the coin's .conf
    RPC_PASS = 'password';                    // 'rpcpassword' from the coin's .conf

    COIN_NAME = 'Somecoin';                   // Coin name/title
    COIN_POS = false;                         // Set to true for proof-of-stake coins

    RETURN_JSON = false;                      // Set to true to return JSON instead of PHP arrays
    DATE_FORMAT = 'Y-M-d H:i:s';              // Date format for blocklist
    BLOCKS_PER_LIST = 12;                     // Number of blocks to collect for the blocklist

    DB_FILE = 'db/somecoin_db.sq3';           // Set to false to disable database storage

    // for the example explorer
    COIN_HOME = 'https://www.somecoin.org/';  // Coin website
    REFRESH_TIME = 180;                       // Seconds between automatic HTML page refresh


For database storage it usually suffices to create a directory that is owned and writable by the user the httpd process runs under, and pointing the `DB_FILE` setting to a suitable filename inside that directory.

To get accurate transaction values your block chain must be reindexed (or built from scratch) with full transaction indexing, by setting `txindex=1` in the coin's .conf file.

Additional help and advice might be found in the official thread on the BitcoinTalk forum: https://bitcointalk.org/index.php?topic=686177.0


Extras
------

`tally.php` generates a "richlist". Usage: configure user/pass/host/port in the beginning of the file, and then run from command line: `php tally.php <output>`. Accurate results require the block chain being built with full transaction indexing. Avoid storing `tally.php` in your web directory where users may run it remotely, as it can be very time- and CPU-consuming when parsing long block chains.

When finished parsing blocks, `tally.php` will output its progress to a file named `RPCUSER-RPCPORT-tally.dat` which will be used to resume operations next time `tally.php` runs in order to avoid having to start over from block 1 when updating a list. Aborting the script while running by pressing `CTRL+C` will also save the progress file for later use.


Donations
---------

BTC: 1EDhbo9ejdKUxNW3GPBh1UmocC1ea1TvE5  
LTC: LaDuRFwEt1V26pmJJH94auDvxqN3rRFqPj  
DOGE: DJ7vQ1dNRfebb1umVHsHxoMcd2Zq5L6LKp  
VTC: VwDmyMR5udPkEwTJhxDsUB2C3mk8NKCSD8  
DRK: XvHfibq2f1xU6rYqAULVXHLPuRhBawzTZs  


License
-------

RPC Ace is released under the Creative Commons BY-NC-SA 4.0 license: https://creativecommons.org/licenses/by-nc-sa/4.0/
