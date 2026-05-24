# CommonMark plugin for Joomla!

CommonMark plugin for Joomla! provides a plugin to render markdown into Joomla!.

# For Linux

## Install

### 1. Open a session and change to the document root of your local webserver.

```
$ cd /var/www/html/
/var/www/html$
```

### 2. Clone the current repository into your webserver root folder

```
/var/www/html$ git clone git@github.com:flygcert/plg-commonmark.git
```

Are you new with github? Here you can find information about setting it up: https://help.github.com/articles/set-up-git/


### 3. Change to the directory plg-commonmark

```
/var/www/html$ cd plg-commonmark
/var/www/html/plg-commonmark$
```

### 4. This files should be in your plg-commonmark folder.

```
/var/www/html/plg-commonmark$ ls
LICENSE	RoboFile.dist.ini
composer.json	 manifest.xml	RoboFile.php
composer.lock	 jorobo.dist.ini  README.md	src
```

### 5. Optional: Have a look into composer.json for information what software you will install via composer.

```
/var/www/html/plg-commonmark$ cat composer.json
```

Read more about [how to install composer](https://getcomposer.org/doc/00-intro.md) here.

### 6. Optional: If you have problems using composer set a timeout.

```
/var/www/html/plg-commonmark$export COMPOSER_PROCESS_TIMEOUT=1500;
```

### 7. Install PHP dependencies via composer

```
/var/www/html/plg-commonmark$ composer install
```

### 8. Optional: Prepare the database

If you use MySQL or PostgreSQL as database and your user has create database privileges the Database is automatically created by the Joomla installer.
But the safest way is to create the database before running Joomla's web installer.

```
/var/www/html/plg-commonmark$ mysql -u root -p

mysql> create database joomla;
Query OK, 1 row affected (0,00 sec)

mysql> quit;
Bye
```

### 9. Optional: Set use owner of the project to your user.

```
/var/www/html/plg-commonmark$ sudo chown -R username:usergroup /var/www
```

## Development and Package Building

### Build Package

```
/var/www/html/plg-commonmark$ vendor/bin/robo build
```

This command creates an installable Joomla package of the extension, outputting a .zip archive to the dist/ directory.

### Development Mapping

```
/var/www/html/plg-commonmark$ vendor/bin/robo map /path/to/joomla-cms
```

This command create symbolic links between the extension's source files and the target Joomla CMS installation. This allows for immediate visualization of code modifications within the Joomla environment, eliminating manual extension installs. Please provide the **absolute** path to the Joomla installation's root directory when running this command.
