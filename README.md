# RibsCronBundle
RibsCronBundle is a bunlde to use cron via curl a url of you website with cron based time

## Create you first method called by ribs_cron

Craete a new Controller class and extends it to RibsCronController.
Create a method with the name you want like this example : 
```PHP
<?php

namespace App\Controller;

use PiouPiou\RibsCronBundle\Controller\RibsCronController;

class TestCronController extends RibsCronController
{
    public function testCronCall()
    {
        // do action called by /ribs-cron
    }
}
```

Now to make this method called by /ribs-cron url you must add it to ribs_cron.yaml config file like this : 
```YML
parameters:
  data_directory: '%kernel.project_dir%/data/'
  ribs_cron:
    testCronCall: "* * * * *"
```

After that each time you have in your crontab file a curl to /ribs-cron url testCronCall method will be executed each minute.
Parameters in quote run like standard cron time system.

 ## How to call your cron url at any time with external url
 
 In you .env file you can add two parameters : 
IP_CRON_EXTERNAL to add external IP that can call your cron
IP_CRON_INTERNAL= internal IP of the server that can call your cron
