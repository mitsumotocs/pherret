# Pherret

PHP MVC framework in a single file. No black screen required.

## Usage

### 1. Load the file

#### ... with Composer

composer.json

    {
        "minimum-stability": "dev",
        "repositories": [
            {
                "type": "vcs",
                "url": "https://github.com/mitsumotocs/pherret"
            }
        ],
        "require": {
            "mitsumotocs/pherret": "dev-master"
        }
    }

index.php

    require_once '/path/to/autoload.php';    

#### ... directly

Simply download Pherret.php and place it somewhere.

index.php

    require_once '/path/to/Pherret.php';

### 2. See if it works

    use Pherret\App;
    echo App::VERSION;

If you get version information, then you are good to go. Can't go any simpler.

### 3. Use

#### Tell ClassLoader where to load classes from

ClassLoader will load class files according to PSR-4. If your directory and namespace is like:

    /path/to/src/
    [Acme]
      [Model]
        YourModel.php \Acme\Model\YourModel
      [View]
        YourView.php \Acme\View\YourView
      [Controller]
        YourController.php \Acme\Controller\YourController
      [Whatever]
        WhateverClass.php \Acme\Whatever\WhateverClass

Then in index.php (or whatever)

    use Pherret\ClassLoader;
    ClassLoader::setDirectory('/path/to/src');

#### Config

Pherret parses a JSON file and load its values as an array.

/path/to/config.json

    {
      "debug": false,
      "database": {
        "directory": "/path/to/storage",
        "file": "db.sqlite"
      }
    }

index.php (or whatever)

    use Pherret\Config;
    Config::load('/path/to/config.json');

Here's one of my favorite features of Pherret. You can merge/overwrite the config values with another JSON file like below: 

/path/to/dev.json

    {
      "debug": true,
      "database": {
        "file": "test.sqlite"
      }
    }

index.php (or whatever)

    use Pherret\Config;
    Config::load('/path/to/config.json');
    Config::load('/path/to/dev.json'); // comment this out on deployment!

By doing this, you will get:

    {
      "debug": true,
      "database": {
        "directory": "/path/to/storage",
        "file": "test.sqlite"
      }
    }

To get the config values, Pherret recognizes dot syntax to access deeper levels.

    use Pherret\Config;
    Config::get('debug');
    Config::get('database.file');
    Config::get('nonExisting'); // returns null

#### Routing

.htaccess

    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]

Routing with Pherret is very straight forward. Regular expression. Just check this out. You will see.

index.php (or whatever)

     use Pherret\App;
     
     // https://example.com/
     App::route('GET', '/^$/', function () {
         echo 'Welcome to my website!';
     });
     
     // https://example.com/hello/
     App::route('GET', '/^hello$/', function () {
         echo 'Hello, world!';
     });
     
     // You can use placeholders!
     // https://example.com/[$action]/
     App::route('GET', '/^(\w+)$/', function ($action) {
         echo 'Doing: ' . $action;
     });

The first parameter or App::route() takes a HTTP request method (GET/POST/...) or null. If you pass null, the router will match any request method.

#### Run

Finally:

index.php (or whatever)

    App::run();