#!/bin/bash -l
## Resets various caches and configs

## NOTE: This script assumes it is in protected/scripts. If you move it then relative paths will not work!

# Find fuill folder path where this script is located, then find root folder
SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do # resolve $SOURCE until the file is no longer a symlink
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE" # if $SOURCE was a relative symlink, we need to resolve it relative to the path where the symlink file was located
done
# Determine root folder for site - all relative paths will be built from here
SCRIPTDIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
WROOT="$( cd -P "$SCRIPTDIR/../../" && pwd )"

testtorun="$WROOT/protected/tests/unit"
phpunitconfigxml="$WROOT/protected/tests/phpunit.xml"
phpunitpath="$WROOT/vendor/phpunit/phpunit/phpunit.php"

while [[ $# -gt 0 ]]
do
    p="$1"

    case $p in
	    --test-file)
        testtorun="$2"
        shift
	      ;;
      --configuration)
        phpunitconfigxml="$2"
        shift
        ;;
	    *)  [ ! -z $p ] && echo "Unknown command line: $p" || :
        ;;
    esac

shift # move to next parameter
done

eval php $phpunitpath --configuration $phpunitconfigxml $testtorun