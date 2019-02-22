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

If you get version information, then you are good to go.

### 3. Use

I'm too lazy to write about it.