HeatMiser WiFi PHP Interface
============================

Current State
-------------

Development started on 2013-01-29. Reads raw DCB but does not parse it yet.


Intro
-----

PHP class to interface with HeatMiser wifi thermostats via their binary interface. Allows reading/writing of DCB.


Usage example
-------------

```php
<?php

require_once 'heatmiser-wifi.php';

try {
        $hm = new Heatmiser_Wifi('192.168.1.123', 1234);
        var_dump($hm->get_dcb());
}
catch (ConnectionFailedException $e) {
        die("Failed to connect: ".$e->getMessage()."\n");
}
```


Requirements
------------

* PHP >= 5.4
* A HeatMiser WiFi Thermostat. Tested on a PRT-TS thermostat. Will possibly work on others or indeed act as a base for your own development.
