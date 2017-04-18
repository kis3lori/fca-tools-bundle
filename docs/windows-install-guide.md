# FCA-Tools-Bundle

## Windows Installation Guide

Note: Paths and versions may differ from environment to environment. Default paths were used for all the examples.

1. Install [WAMP](http://www.wampserver.com/en)  
  Pre-requirement for WAMP: [Visual C++ Redistributable for Visual Studio 2012 Update 4](https://www.microsoft.com/en-us/download/details.aspx?id=30679)  
  Make sure the PHP 5.6 version is enabled. We will use this one in this guide.

2. Add PHP to the environment path variable.  
  The path should be `C:\Extra\wamp64\bin\php\php5.6.25`.

3. Install [MongoDB](https://www.mongodb.com)  
  Don't forget to create the `C:\data\db\` directory needed to run MongoDB.  
  Start MongoDB by running the `mongod` command in `C:`  
  You will have to start MongoDB whenever you work on this project.  

4. Install the PHP MongoDB and Memcache extension  
    1. Download the extension files of [MongoDB](https://pecl.php.net/package/mongo/1.6.14/windows) and [Memcache](https://pecl.php.net/package/memcache/3.0.8/windows).
    2. Put the extension dll files in `C:\wamp64\bin\php\php5.6.25\ext\`.
    3. Open the php configuration file `C:\wamp64\bin\php\php5.6.25\php.ini`.
    4. Around line 920 or so, below all the other extensions, add the lines `extension=php_mongo.dll` and `extension=php_memcache.dll`.
    5. Open the php configuration file `C:\wamp64\bin\apache\apache2.4.23\bin\php.ini'. 
    6. Repeat step 4.
    7. Restart all WAMP services.
  
5. Increase cache size  
    1. Open the php configuration file `C:\wamp64\bin\php\php5.6.25\php.ini`.
    2. Find the line containing `realpath_cache_size` and replace it with `realpath_cache_size = 5M`.
    3. Open the php configuration file `C:\wamp64\bin\apache\apache2.4.23\bin\php.ini'.
    4. Repeat step 2.

6. Install [Memcached](https://commaster.net/content/installing-memcached-windows)  
  Use the last one from the list ([http://downloads.northscale.com/memcached-1.4.5-amd64.zip](http://downloads.northscale.com/memcached-1.4.5-amd64.zip)).  
  Run Memcached after you are done. Leave the terminal open. You will have to start this service whenever you are working on this project.  
  This cache can be disabled in the project but it speeds up the page loads a lot and is very useful for development.
  
7. Install [Composer](https://getcomposer.org/doc/00-intro.md#installation-windows)  
  Simply use the Composer setup.

8. Install [Git](https://git-scm.com/download/win)  
  I'll leave you to figure out this part :P.

9. Checkout project from GIT.  
    1. Open command prompt in admin mode.
    2. Run `cd "C:\Extra\wamp64\www`.
    3. Run `git checkout https://github.com/kis3lori/fca-tools-bundle.git`.

10. Install project dependencies  
  In the terminal opened before (make sure you're in administrator mode), run `composer install`.  
  After the installation is done run `php app\console assets:install --symlink`.

11. Load the MongoDB database  
  Run `mongorestore --db fca-tools-bundle dump/fca-tools-bundle` in the project root directory.

12. Open your newly installed <a href="http://localhost/fca-tools-bundle/web/app_dev.php" target="_blank">website</a>

## Install other programming languages used by the website.

### ASP Programming Language TBD

## Optional

### Create virtual host TBD

### Deprecated
* Python - A python script used to generate the concept lattice but now it has been replaced with PHP.
  The script is still there and can be used for reference or for debugging.
