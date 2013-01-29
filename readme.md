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
