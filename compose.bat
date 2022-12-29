@ECHO off

@ECHO ====================
@ECHO Composer version
@ECHO:
@ECHO COMPOSER URL: https://getcomposer.org/download/
@ECHO PHP URL:      https://windows.php.net/download
@ECHO ====================
@CALL composer -V

@ECHO:
@ECHO:

@ECHO ====================
@ECHO remove old vendor directory
@ECHO ====================
@CALL RD /S /Q "vendor"

@ECHO:
@ECHO:

@ECHO ====================
@ECHO run composer
@ECHO ====================
@CALL composer install --optimize-autoloader --no-dev

@ECHO:
@ECHO:

@ECHO ====================
@ECHO DONE
@ECHO ====================
PAUSE