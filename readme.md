HeatMiser WiFi PHP Interface
============================

Intro
-----

PHP library to interface with HeatMiser wifi thermostats via their binary interface. Allows reading/writing of DCB.



Installation
------------

Supports both Composer installation and regular file inclusion. For Composer, add the following to your composer.json:

```Javascript
"require": {
    "phil-lavin/heat-miser-wifi-php-interface": "1.0.1"
}
```

Or if you want to use the latest commit rather than a (supposedly) stable release:

```Javascript
"require": {
    "phil-lavin/heat-miser-wifi-php-interface": "dev-master"
}
```

For Composer, the library's classes will autoload if you use Composer's autoloader.


Usage example
-------------

```php
<?php

// If you installed the library with Composer
//require_once 'vendor/autoload.php';

// If you didn't install the library with Composer
require_once 'src/PhilLavin/HeatMiser/Wifi.php';

try {
	// ip, pin, optional port
	$hm = new \PhilLavin\HeatMiser\Wifi('192.168.1.123', 1234);
	$dcb = $hm->get_dcb();

	var_dump($dcb); // Dump existing DCB

	// Change heat programming for the weekend. 3rd period (return), starts at 16:00, target temp 25C
	$heat_data = $dcb['heat_data'];
	$heat_data['6-7'][2] = ['time'=>'16:00:00', 'target'=>25];
	$dcb->set_heat_data($heat_data); // Could be set_ followed by any of the below writable values - e.g. set_enabled(1)

	// Write and dump new DCB
	var_dump($hm->put_dcb($dcb));
}
catch (\PhilLavin\HeatMiser\ConnectionFailedException $e) {
	die("Failed to connect: ".$e->getMessage()."\n");
}
catch (\Exception $e) {
	die("Exception of type ".get_class($e)." thrown. Error was: {$e->getMessage()}\n");
}
```


Writable Values
---------------

Only the following values can be changed. This is a restriction of the device:

* time
* enabled
* keylock
* holiday_enabled
* holiday
* runmode
* frostprotect_target
* floorlimit_floormax
* heating_target
* heating_hold
* hotwater_on
* heat_data
* water_data


Example DCB object data array format (for reading and writing)
------------------

```php
<?php
array(28) {
  'vendor' =>
  string(9) "Heatmiser"
  'version' =>
  double(1.6)
  'model' =>
  string(3) "PRT"
  'time' =>
  string(19) "2013-01-31 23:34:24"
  'enabled' =>
  int(1)
  'keylock' =>
  int(0)
  'holiday' =>
  string(19) "2013-01-31 23:34:00"
  'holiday_enabled' =>
  int(0)
  'units' =>
  string(1) "C"
  'switchdiff' =>
  int(1)
  'caloffset' =>
  int(0)
  'outputdelay' =>
  int(0)
  'locklimit' =>
  int(0)
  'sensor' =>
  string(8) "internal"
  'optimumstart' =>
  int(0)
  'runmode' =>
  string(7) "heating"
  'frostprotect_enabled' =>
  int(1)
  'frostprotect_target' =>
  int(12)
  'remote_temperature' =>
  NULL
  'floor_temperature' =>
  NULL
  'internal_temperature' =>
  double(15.7)
  'heating_on' =>
  int(0)
  'heating_target' =>
  int(14)
  'heating_hold' =>
  int(0)
  'rateofchange' =>
  int(20)
  'errorcode' =>
  NULL
  'progmode' =>
  string(3) "5/2"
  'heat_data' =>
  array(2) {
    '1-5' =>
    array(4) {
      [0] =>
      array(2) {
        'time' =>
        string(8) "04:00:00"
        'target' =>
        int(19)
      }
      [1] =>
      array(2) {
        'time' =>
        string(8) "08:30:00"
        'target' =>
        int(14)
      }
      [2] =>
      array(2) {
        'time' =>
        string(8) "16:30:00"
        'target' =>
        int(18)
      }
      [3] =>
      array(2) {
        'time' =>
        string(8) "22:00:00"
        'target' =>
        int(14)
      }
    }
    '6-7' =>
    array(4) {
      [0] =>
      array(2) {
        'time' =>
        string(8) "08:00:00"
        'target' =>
        int(19)
      }
      [1] =>
      array(2) {
        'time' =>
        string(8) "13:00:00"
        'target' =>
        int(14)
      }
      [2] =>
      NULL
      [3] =>
      array(2) {
        'time' =>
        string(8) "22:00:00"
        'target' =>
        int(14)
      }
    }
  }
}
```

Known Issues
------------

* The DCB object returned by put_dcb() always has heating_on set to 0. This seems to be a bug with the thermostat and not really something
we can work around. Re-read the DCB following a write if you need to reliably get this value.


Requirements
------------

* PHP >= 5.4
* A HeatMiser WiFi Thermostat. Tested on a PRT-TS thermostat. Will possibly work on others or indeed act as a base for your own development.



License
-------

Copyright (c) 2013, Phil Lavin  
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met: 

1. Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer. 
2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution. 

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are those
of the authors and should not be interpreted as representing official policies, 
either expressed or implied, of the FreeBSD Project.
